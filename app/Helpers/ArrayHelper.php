<?php
/**
 * This file is part of BGIII Mod Manager Linux Helper.
 *
 * Copyright (C) 2025 Wushin.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

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
