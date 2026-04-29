<?php
/**
 * Polyfills for PHP functions removed in PHP 8.0 that are still referenced
 * by bundled third-party PDF libraries (setasign/fpdf, setasign/fpdi).
 *
 * Kept in the module instead of patching vendor/ so composer install/update
 * does not wipe the fix.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

if (!function_exists('get_magic_quotes_runtime')) {
    function get_magic_quotes_runtime()
    {
        return false;
    }
}

if (!function_exists('set_magic_quotes_runtime')) {
    function set_magic_quotes_runtime($new_setting)
    {
        return true;
    }
}

if (!function_exists('get_magic_quotes_gpc')) {
    function get_magic_quotes_gpc()
    {
        return false;
    }
}

if (!function_exists('each')) {
    function each(&$array)
    {
        $key = key($array);
        if ($key === null) {
            return false;
        }
        $value = current($array);
        next($array);

        return array(
            0 => $key,
            'key' => $key,
            1 => $value,
            'value' => $value,
        );
    }
}
