# DealNews\Filter

Starting with PHP 8.1, the filter type FILTER_SANITIZE_STRING was
deprecated. To avoid deprecated errors, this class implements similar
behavior as the FILTER_SANITIZE_STRING filter. This is done by changing
filters using `\DealNews\Filter\Filter::FILTER_SANITIZE_STRING` to use
`FILTER_CALLBACK` to a closure implementing behavior similar to what
`\FILTER_SANITIZE_STRING` provides.

This class is a drop in replacement for `filter_var`, `filter_var_array`,
`filter_input`, and `filter_input_array`. The only filter that is is modified
are ones using `\DealNews\Filter\Filter::FILTER_SANITIZE_STRING`.

## Example

### PHP <= 8.0

This is how you used `FILTER_SANITIZE_STRING` in PHP <=8.0.

```php
<?php

$input = filter_input_array(
    INPUT_GET,
    [
        'id' => FILTER_VALIDATE_INT,
        'search' => FILTER_SANITIZE_STRING
    ]
);
```

### PHP >=8.1

In PHP 8.1 or higher, you can use DealNews\Filter instead like so.

```php
<?php

use DealNews\Filter\Filter;

$filter = new Filter();

$input = $filter->inputArray(
    INPUT_GET,
    [
        'id' => FILTER_VALIDATE_INT,
        'search' => Filter::FILTER_SANITIZE_STRING
    ]
);
```
