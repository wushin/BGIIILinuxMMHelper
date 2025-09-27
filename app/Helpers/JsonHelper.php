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

class JsonHelper
{
    /**
     * Recursively search for a string/regex in a JSON document and return JSON array of key paths.
     * Each path is a JSON array of keys/indexes, e.g.:
     *   [["save","region","nodes",0,"attributes","handle"], ...]
     */
    public static function searchPaths(string $json, string $needle, bool $regex = false, bool $caseInsensitive = false): string
    {
        $data = self::decodeToArray($json, __FUNCTION__);

        $delim  = '#';
        $flags  = $caseInsensitive ? 'i' : '';
        $pattern = $regex ? $needle : preg_quote($needle, $delim);
        $pattern = $delim . $pattern . $delim . $flags;

        $results = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($data));

        foreach ($it as $key => $value) {
            // Only test scalar-ish values
            if (!is_array($value) && !is_object($value)) {
                $val = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
                if (preg_match($pattern, trim($val))) {
                    $path = [];
                    for ($depth = $it->getDepth(); $depth >= 0; $depth--) {
                        $sub = $it->getSubIterator($depth);
                        $path[] = $sub->key();
                    }
                    $results[] = array_reverse($path);
                }
            }
        }

        return json_encode($results, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Retrieve a nested value from JSON using a path (PHP array or JSON array).
     * Returns the value encoded as JSON (or "null" if path not found).
     */
    public static function get(string $json, array|string $path): string
    {
        $data = self::decodeToArray($json, __FUNCTION__);
        $keys = self::normalizePath($path);

        $value = $data;
        foreach ($keys as $k) {
            $idx = self::normalizeKey($k);
            if (is_array($value) && array_key_exists($idx, $value)) {
                $value = $value[$idx];
            } else {
                $value = null;
                break;
            }
        }

        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Set a nested value inside a JSON document using a path (PHP array or JSON array).
     * $newValue may be a scalar or a JSON string (object/array/scalar).
     * Returns UPDATED JSON (pretty by default).
     */
    public static function set(string $json, array|string $path, mixed $newValue, bool $pretty = true): string
    {
        $data = self::decodeToArray($json, __FUNCTION__);
        $keys = self::normalizePath($path);

        // Decode JSON payloads automatically
        if (is_string($newValue)) {
            $decoded = json_decode($newValue, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $newValue = $decoded;
            }
        }

        $ref = &$data;
        $last = array_pop($keys);
        foreach ($keys as $k) {
            $idx = self::normalizeKey($k);
            if (!is_array($ref)) {
                $ref = [];
            }
            if (!array_key_exists($idx, $ref) || !is_array($ref[$idx])) {
                $ref[$idx] = [];
            }
            $ref = &$ref[$idx];
        }
        $ref[self::normalizeKey($last)] = $newValue;

        return json_encode($data, self::jsonFlags($pretty));
    }

    /**
     * Delete a nested value; returns UPDATED JSON (pretty by default).
     * If the path does not exist, the original document is returned.
     */
    public static function delete(string $json, array|string $path, bool $pretty = true): string
    {
        $data = self::decodeToArray($json, __FUNCTION__);
        $keys = self::normalizePath($path);

        $stack = [&$data];
        foreach ($keys as $k) {
            $idx = self::normalizeKey($k);

            $top = &$stack[count($stack) - 1];
            if (!is_array($top) || !array_key_exists($idx, $top)) {
                // Path missing -> no-op
                return json_encode($data, self::jsonFlags($pretty));
            }

            $stack[] = &$top[$idx];
        }

        // Unset the final element
        $parent = &$stack[count($stack) - 2];
        $finalKey = self::normalizeKey(end($keys));
        unset($parent[$finalKey]);

        return json_encode($data, self::jsonFlags($pretty));
    }

    /**
     * Check if a path exists. Returns JSON boolean "true" or "false".
     */
    public static function exists(string $json, array|string $path): string
    {
        $data = self::decodeToArray($json, __FUNCTION__);
        $keys = self::normalizePath($path);

        $value = $data;
        foreach ($keys as $k) {
            $idx = self::normalizeKey($k);
            if (is_array($value) && array_key_exists($idx, $value)) {
                $value = $value[$idx];
            } else {
                return 'false';
            }
        }
        return 'true';
    }

    /* ----------------------------- internals ----------------------------- */

    private static function decodeToArray(string $json, string $ctx): array
    {
        $data = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            throw new \InvalidArgumentException("$ctx: invalid JSON input (" . json_last_error_msg() . ')');
        }
        return $data;
    }

    /**
     * Accepts either a PHP array path or a JSON array path string.
     * E.g., ["save","region","nodes",0,"attrs","handle"]
     */
    private static function normalizePath(array|string $path): array
    {
        if (is_array($path)) {
            return $path;
        }
        $decoded = json_decode($path, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            throw new \InvalidArgumentException('normalizePath: path must be a JSON array or PHP array');
        }
        return $decoded;
    }

    /**
     * Convert numeric-looking keys to int indices to better model JSON arrays.
     * Non-numeric keys are kept as strings.
     */
    private static function normalizeKey(mixed $key): int|string
    {
        if (is_int($key)) return $key;
        if (is_string($key) && ctype_digit($key)) {
            // keep leading-zero strings as strings (less surprising)
            if ($key === '0' || $key[0] !== '0') {
                return (int) $key;
            }
        }
        return is_string($key) ? $key : (string) $key;
    }

    private static function jsonFlags(bool $pretty): int
    {
        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        if ($pretty) $flags |= JSON_PRETTY_PRINT;
        return $flags;
    }
}

