<?php

namespace App\Helpers;

class ArrayHelper
{
    /**
     * Recursively search for a value and return key paths.
     */
    public static function recursiveSearchKeyMap($needle, $haystack)
    {
        $results = array();
        $iterator = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($haystack));

        foreach ($iterator as $key => $value) {
            if (!is_array($value) && preg_match('/' . $needle . '/', trim($value))) {
                $path = array();
                for ($depth = $iterator->getDepth(); $depth >= 0; $depth--) {
                    $subIterator = $iterator->getSubIterator($depth);
                    $path[] = $subIterator->key();
                }
                $results[] = array_reverse($path);
            }
        }

        return $results;
    }

    /**
     * Retrieve a nested value using a key path.
     */
    public static function getNestedValue($path, $array)
    {
        $value = $array;
        foreach ($path as $key) {
            if (!isset($value[$key])) {
                return null;
            }
            $value = $value[$key];
        }
        return $value;
    }

    /**
     * Set a nested value using a key path.
     */
    public static function setNestedValue(array &$array, $path, $newValue)
    {
        $current = &$array;
        foreach ($path as $key) {
            $current = &$current[$key];
        }
        $current = $newValue;
    }
}
?>
