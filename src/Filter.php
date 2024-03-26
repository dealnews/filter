<?php

namespace DealNews\Filter;

use DealNews\Utilities\Traits\Singleton;

/**
 * Wrapper for PHP built in filter functions
 *
 * Starting with PHP 8.1, the filter type FILTER_SANITIZE_STRING was
 * deprecated. To avoid deprecated errors, this class implements similar
 * behavior as the FILTER_SANITIZE_STRING filter.
 *
 * @author      Brian Moon <brianm@dealnews.com>
 * @copyright   1997-Present DealNews.com, Inc
 * @package     DealNews\Filter
 * @see         https://www.php.net/manual/en/book.filter.php
 */
class Filter {

    /**
     * Value to use instead of \FILTER_SANITIZE_STRING for sanitizing strings
     *
     * @var        int
     */
    public const FILTER_SANITIZE_STRING = 513;

    /**
     * Singleton
     *
     * @return self
     */
    public static function init() {
        static $inst;

        if (empty($inst)) {
            $class = get_called_class();
            $inst  = new $class();
        }

        return $inst;
    }

    /**
     * Wrapper for filter_var_array
     *
     * @see https://www.php.net/manual/en/function.filter-var-array.php
     */
    public function varArray(array $array, array|int $options = FILTER_DEFAULT, bool $add_empty = true): array|false|null {
        $apply_all = is_int($options);
        $options   = $this->fixOptions($options);

        if (is_array($options) && $apply_all) {
            $new_options = [];
            foreach (array_keys($array) as $key) {
                $new_options[$key] = $options;
            }
            $options = $new_options;
        }

        return filter_var_array($array, $options, $add_empty);
    }

    /**
     * Wrapper for filter_var
     *
     * @see https://www.php.net/manual/en/function.filter-var.php
     */
    public function var(mixed $value, int $filter = FILTER_DEFAULT, array|int $options = 0): mixed {
        if ($filter == $this::FILTER_SANITIZE_STRING) {
            $filter  = FILTER_CALLBACK;
            $options = [
                'options' => $this->sanitizeString(is_int($options) ? $options : 0),
            ];
        }

        return  filter_var($value, $filter, $options);
    }

    /**
     * Wrapper for filter_input_array
     *
     * @see https://www.php.net/manual/en/function.filter-input-array.php
     */
    public function inputArray(int $type, array|int $options = FILTER_DEFAULT, bool $add_empty = true): array|false|null {
        $empty = false;
        switch ($type) {
            case INPUT_GET:
                $empty = empty($_GET);
                break;
            case INPUT_POST:
                $empty = empty($_POST);
                break;
            case INPUT_COOKIE:
                $empty = empty($_COOKIE);
                break;
            case INPUT_SERVER:
                $empty = empty($_SERVER);
                break;
            case INPUT_ENV:
                $empty = empty($_ENV);
                break;
        }

        // this is how filter_input_array behaves
        if ($empty) {
            return null;
        }

        return $this->varArray(filter_input_array($type) ?? [], $options, $add_empty);
    }

    /**
     * Wrapper for filter_input
     *
     * @see https://www.php.net/manual/en/function.filter-input.php
     */
    public function input(int $type, string $var_name, int $filter = FILTER_DEFAULT, array|int $options = 0): mixed {
        return $this->var(filter_input($type, $var_name), $filter, $options);
    }

    /**
     * Creates a closure that is returned for use in a FILTER_CALLBACK filter
     * to emulate the old FILTER_SANITIZE_STRING behavior.
     *
     * @param      int     $flags  Bitwise disjunction of filter flags.
     *
     * @return     \Closure
     */
    public function sanitizeString(int $flags = 0): \Closure {
        // @phan-suppress-next-line PhanUnreferencedClosure
        return function (mixed $value = null) use ($flags) {

            // FILTER_UNSAFE_RAW with the FILTER_FLAG_ENCODE_AMP
            // flag will convert a & into a &#38;. htmlspecialchars
            // converts & into &amp;. The latter is prefered. To
            // achieve this output, the flag is removed from the
            // flags if present so that FILTER_UNSAFE_RAW does not
            // encode &. And if it is not set, &amp; is replaced with
            // a bare & at the end after htmlspecialchars runs.

            $encode_amp = (bool)($flags & FILTER_FLAG_ENCODE_AMP);

            if ($encode_amp) {
                $flags -= FILTER_FLAG_ENCODE_AMP;
            }

            $value = strip_tags($value);

            $value = filter_var($value, FILTER_UNSAFE_RAW, $flags);

            $ent_quotes = ($flags & FILTER_FLAG_NO_ENCODE_QUOTES) ? ENT_NOQUOTES : ENT_QUOTES;
            $value      = htmlspecialchars($value, $ent_quotes | ENT_HTML5);

            if (!$encode_amp) {
                $value = str_replace('&amp;', '&', $value);
            }

            return $value;
        };
    }

    /**
     * Converts any FILTER_SANITIZE_STRING filters in the options to a
     * FILTER_CALLBACK filter.
     *
     * @param      array|int  $options  The options
     *
     * @return     array|int  ( description_of_the_return_value )
     */
    public function fixOptions(array|int $options): array|int {
        if ($options === $this::FILTER_SANITIZE_STRING) {
            $options = [
                'filter'  => FILTER_CALLBACK,
                'options' => $this->sanitizeString(),
            ];
        } elseif (is_array($options)) {
            foreach ($options as $key => $filter) {
                if ($filter === $this::FILTER_SANITIZE_STRING) {
                    $options[$key] = [
                        'filter'  => FILTER_CALLBACK,
                        'options' => $this->sanitizeString(),
                    ];
                } elseif (is_array($filter) && $filter['filter'] === $this::FILTER_SANITIZE_STRING) {
                    if (!empty($filter['flags']) && is_array($filter['flags'])) {
                        $flags = 0;
                        foreach ($filter['flags'] as $flag) {
                            $flags = $flags | $flag;
                        }
                        $filter['flags'] = $flags;
                    }

                    $options[$key] = [
                        'filter'  => FILTER_CALLBACK,
                        'options' => $this->sanitizeString($filter['flags'] ?? 0),
                    ];
                }
            }
        }

        return $options;
    }
}
