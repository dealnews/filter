<?php

namespace DealNews\Filter\Tests;

use DealNews\Filter\Filter;

class FilterTest extends \PHPUnit\Framework\TestCase {
    /**
     * Test the input methods that they don't throw errors
     */
    public function testInput() {
        $f     = Filter::init();
        $value = $f->input(INPUT_POST, 'foo', FILTER_VALIDATE_INT);
        $this->assertFalse($value);
    }

    /**
     * Test the input methods that they don't throw errors
     */
    public function testInputArray() {
        $f     = Filter::init();
        $value = $f->inputArray(INPUT_GET, ['foo' => FILTER_VALIDATE_INT]);
        $this->assertNull($value);
        $value = $f->inputArray(INPUT_POST, ['foo' => FILTER_VALIDATE_INT]);
        $this->assertNull($value);
        $value = $f->inputArray(INPUT_COOKIE, ['foo' => FILTER_VALIDATE_INT]);
        $this->assertNull($value);

        $expect     = array_slice($_SERVER, 0, 5);
        $keys       = [];
        $expect_bug = [];
        foreach (array_keys($expect) as $key) {
            $keys[$key] = FILTER_UNSAFE_RAW;
            // https://bugs.php.net/bug.php?id=49184
            // filtering on INPUT_SERVER yields an array with null values
            $expect_bug[$key] = null;
        }

        $value = $f->inputArray(INPUT_SERVER, $keys);
        $this->assertEquals($expect_bug, $value);

        $value = $f->inputArray(INPUT_ENV, $keys);
        $this->assertEquals($expect, $value);
    }

    /**
     * @dataProvider varArrayData
     */
    public function testVarArray($array, $filter, $expect) {
        $f     = Filter::init();
        $value = $f->varArray($array, $filter);
        $this->assertSame($expect, $value);
    }

    public static function varArrayData() {
        return [

            'One filter applied to all' => [
                [
                    'type'  => 'deals',
                    'ids'   => '1,2,3,4,5,6,7,8',
                    'e'     => 1,
                    'count' => 20,
                    'h'     => "<a href='test'>\"Test\" & 'Check'</a>",
                ],
                Filter::FILTER_SANITIZE_STRING,
                [
                    'type'  => 'deals',
                    'ids'   => '1,2,3,4,5,6,7,8',
                    'e'     => '1',
                    'count' => '20',
                    'h'     => '&quot;Test&quot; & &apos;Check&apos;',
                ],
            ],

            'Some Basic Stuff' => [
                [
                    'type'  => 'deals',
                    'ids'   => '1,2,3,4,5,6,"7",8',
                    'e'     => 1,
                    'count' => 20,
                    'h'     => "<a href='test'>\"Test\" & 'Check'</a>",
                ],
                [
                    'type' => [
                        'filter'  => FILTER_VALIDATE_REGEXP,
                        'options' => [
                            'regexp' => '!(deal|deals|coupon|offers|features)!i',
                        ],
                    ],
                    'ids'   => [
                        'filter' => Filter::FILTER_SANITIZE_STRING,
                        'flags'  => FILTER_FLAG_NO_ENCODE_QUOTES,
                    ],
                    'e'     => FILTER_VALIDATE_INT,
                    'count' => FILTER_VALIDATE_INT,
                    'h'     => Filter::FILTER_SANITIZE_STRING,
                ],
                [
                    'type'  => 'deals',
                    'ids'   => '1,2,3,4,5,6,"7",8',
                    'e'     => 1,
                    'count' => 20,
                    'h'     => '&quot;Test&quot; & &apos;Check&apos;',
                ],
            ],

            'Flags as an array' => [
                [
                    'type'  => 'deals',
                    'ids'   => '1,2,3,4,5,6,"7",8' . chr(199),
                    'e'     => 1,
                    'count' => 20,
                    'h'     => "<a href='test'>\"Test\" & 'Check'</a>",
                ],
                [
                    'ids'   => [
                        'filter' => Filter::FILTER_SANITIZE_STRING,
                        'flags'  => [
                            FILTER_FLAG_STRIP_LOW,
                            FILTER_FLAG_STRIP_HIGH,
                            FILTER_FLAG_NO_ENCODE_QUOTES,
                         ],
                    ],
                ],
                [
                    'ids'   => '1,2,3,4,5,6,"7",8',
                ],
            ],

        ];
    }

    /**
     * @dataProvider varData
     */
    public function testVar($value, $filter, $options, $expect) {
        $f     = new Filter();
        $value = $f->var($value, $filter, $options);
        $this->assertSame($expect, $value);
    }

    public static function varData() {
        return [

            'no change' => [
                'foo',
                FILTER_UNSAFE_RAW,
                0,
                'foo',
            ],

            'Sanitize String' => [
                "<a href='test'>\"Test\" & 'Check'</a>",
                Filter::FILTER_SANITIZE_STRING,
                0,
                '&quot;Test&quot; & &apos;Check&apos;',
            ],

            'Sanitize String No Quotes' => [
                "<a href='test'>\"Test\" & 'Check'</a>",
                Filter::FILTER_SANITIZE_STRING,
                FILTER_FLAG_NO_ENCODE_QUOTES,
                "\"Test\" & 'Check'",
            ],

            'Sanitize String Enocde Ampersand' => [
                "<a href='test'>\"Test\" & 'Check'</a>",
                Filter::FILTER_SANITIZE_STRING,
                FILTER_FLAG_ENCODE_AMP,
                '&quot;Test&quot; &amp; &apos;Check&apos;',
            ],

            'Sanitize String Enocde Ampersand, No Quotes' => [
                "<a href='test'>\"Test\" & 'Check'</a>",
                Filter::FILTER_SANITIZE_STRING,
                FILTER_FLAG_ENCODE_AMP | FILTER_FLAG_NO_ENCODE_QUOTES,
                '"Test" &amp; \'Check\'',
            ],

            'Sanitize String Strip High' => [
                "<a href='test'>\"Test\" & 'Check' " . chr(128) . '</a>',
                Filter::FILTER_SANITIZE_STRING,
                FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_NO_ENCODE_QUOTES,
                '"Test" & \'Check\' ',
            ],

            'Sanitize String Encode High' => [
                "<a href='test'>\"Test\" & 'Check' " . chr(128) . '</a>',
                Filter::FILTER_SANITIZE_STRING,
                FILTER_FLAG_ENCODE_HIGH | FILTER_FLAG_NO_ENCODE_QUOTES,
                '"Test" & \'Check\' &#128;',
            ],

            'Validate Int (passthru)' => [
                '1',
                FILTER_VALIDATE_INT,
                0,
                1,
            ],

            'Validate BOOL (passthru)' => [
                '1',
                FILTER_VALIDATE_BOOL,
                0,
                true,
            ],

            'Validate BOOL Null On Failure (passthru)' => [
                'not a bool',
                FILTER_VALIDATE_BOOL,
                FILTER_NULL_ON_FAILURE,
                null,
            ],

        ];
    }
}
