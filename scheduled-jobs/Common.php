<?php

/**
 * Common functions
 *
 * @author Amit Dugar <amit@deforay.com>
 */
class Common
{


    public function makeFileNameFriendly($str, $toLowerCase = false)
    {
        // Remove special characters except hyphens
        $str = preg_replace('/[^a-zA-Z0-9\-]/', '', trim($str));

        // Convert spaces to hyphens
        $str = str_replace(' ', '-', $str);

        // Convert multiple hyphens into one
        $str = preg_replace('/-+/', '-', $str);

        if ($toLowerCase === true) {
            // Convert to lowercase
            $str = strtolower($str);
        }

        return $str;
    }


    public function fileExists($filePath): bool
    {
        return (!empty($filePath) && file_exists($filePath) && !is_dir($filePath) && filesize($filePath) > 0);
    }

    public function array_group_by(array $array, $key)
    {
        if (!is_string($key) && !is_int($key) && !is_float($key) && !is_callable($key)) {
            trigger_error('array_group_by(): The key should be a string, an integer, or a callback', E_USER_ERROR);
            return null;
        }

        $func = (!is_string($key) && is_callable($key) ? $key : null);
        $_key = $key;

        // Load the new array, splitting by the target key
        $grouped = [];
        foreach ($array as $value) {
            $key = null;

            if (is_callable($func)) {
                $key = call_user_func($func, $value);
            } elseif (is_object($value) && property_exists($value, $_key)) {
                $key = $value->{$_key};
            } elseif (isset($value[$_key])) {
                $key = $value[$_key];
            }

            if ($key === null) {
                continue;
            }

            $grouped[$key][] = $value;
        }

        // Recursively build a nested grouping if more parameters are supplied
        // Each grouped array value is grouped according to the next sequential key
        if (func_num_args() > 2) {
            $args = func_get_args();

            foreach ($grouped as $key => $value) {
                $params = array_merge([$value], array_slice($args, 2, func_num_args()));
                $grouped[$key] = call_user_func_array(['self', 'array_group_by'], $params);
            }
        }

        return $grouped;
    }
}
