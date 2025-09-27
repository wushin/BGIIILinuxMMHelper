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

class TextParserHelper
{
    /**
     * Parse BG3 .txt content into JSON.
     * $input can be a string (full text) or an array of lines.
     */
    public static function txtToJson(string|array $input, bool $pretty = true): string
    {
        $lines = is_array($input)
            ? $input
            : preg_split("/\r\n|\r|\n/", $input, -1, PREG_SPLIT_NO_EMPTY);

        $result  = [];
        $current = null;

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            // ---- New blocks -------------------------------------------------
            if (stripos($line, 'new entry') === 0) {
                if ($current) $result[] = $current;
                $id = self::stripQuotes(trim(substr($line, strlen('new entry'))));
                $current = [
                    'type'       => 'entry',
                    'id'         => $id,
                    'properties' => [],
                ];
                continue;
            }

            if (stripos($line, 'new equipment') === 0) {
                if ($current) $result[] = $current;
                $id = self::stripQuotes(trim(substr($line, strlen('new equipment'))));
                $current = [
                    'type'            => 'equipment',
                    'id'              => $id,
                    'initialWeaponSet'=> null,
                    'equipmentGroups' => [],
                    'properties'      => [],
                ];
                continue;
            }

            if (stripos($line, 'new treasuretable') === 0) {
                if ($current) $result[] = $current;
                $id = self::stripQuotes(trim(substr($line, strlen('new treasuretable'))));
                $current = [
                    'type'       => 'treasuretable',
                    'id'         => $id,
                    'subtables'  => [],
                    'properties' => [],
                ];
                continue;
            }

            if (!$current) {
                // ignore stray lines before first block
                continue;
            }

            // ---- Equipment section lines -----------------------------------
            if ($current['type'] === 'equipment') {
                if (stripos($line, 'add initialweaponset') === 0) {
                    $current['initialWeaponSet'] = self::stripQuotes(
                        trim(substr($line, strlen('add initialweaponset')))
                    );
                    continue;
                }

                if (stripos($line, 'add equipmentgroup') === 0) {
                    $current['equipmentGroups'][] = [];
                    continue;
                }

                if (stripos($line, 'add equipment entry') === 0) {
                    $entry = self::stripQuotes(
                        trim(substr($line, strlen('add equipment entry')))
                    );
                    $lastIdx = \count($current['equipmentGroups']) - 1;
                    if ($lastIdx < 0) {
                        $current['equipmentGroups'][] = [];
                        $lastIdx = 0;
                    }
                    $current['equipmentGroups'][$lastIdx][] = $entry;
                    continue;
                }
            }

            // ---- Treasuretable section lines --------------------------------
            if ($current['type'] === 'treasuretable') {
                if (stripos($line, 'new subtable') === 0) {
                    $weight = self::stripQuotes(
                        trim(substr($line, strlen('new subtable')))
                    );
                    $current['subtables'][] = [
                        'type'   => 'subtable',
                        'weight' => $weight,
                        'items'  => [],
                    ];
                    continue;
                }

                if (preg_match('/^object\s+category\s+"?([^"]+)"?,\d.*$/i', $line, $m)) {
                    $i = \count($current['subtables']) - 1;
                    if ($i >= 0) {
                        $current['subtables'][$i]['items'][] = self::stripQuotes($m[1]);
                    }
                    continue;
                }

                if (preg_match('/^(StartLevel|EndLevel)\s+"?(\d+)"?$/i', $line, $m)) {
                    $i = \count($current['subtables']) - 1;
                    if ($i >= 0) {
                        $current['subtables'][$i][$m[1]] = (int) $m[2];
                    }
                    continue;
                }
            }

            // ---- Shared property lines --------------------------------------
            // e.g., data "Key" "Value"  |  using "SomeId"  |  type "SomeType"
            if (preg_match('/^(data|using|type)\s+"([^"]+)"\s*"?(.*?)"?$/i', $line, $m)) {
                $key    = strtolower($m[1]);
                $subkey = self::stripQuotes($m[2]);
                $value  = self::stripQuotes($m[3]);

                if (!isset($current['properties'][$key])) {
                    $current['properties'][$key] = ($key === 'data') ? [] : (($key === 'using') ? [] : null);
                }

                if ($key === 'data') {
                    // JSON-native: use an object map, last-writer-wins for duplicate keys
                    $current['properties']['data'][$subkey] = $value;
                } elseif ($key === 'using') {
                    $current['properties']['using'][] = $subkey;
                } elseif ($key === 'type') {
                    $current['properties']['declared_type'] = $subkey;
                }
                continue;
            }

            // e.g., AnyKey "Some Value"
            if (preg_match('/^(\w+)\s+"([^"]+)"$/', $line, $m)) {
                $k = $m[1];
                $v = self::stripQuotes($m[2]);
                // If key seen multiple times, turn into array of values
                if (!isset($current['properties'][$k])) {
                    $current['properties'][$k] = $v;
                } else {
                    if (!\is_array($current['properties'][$k])) {
                        $current['properties'][$k] = [$current['properties'][$k]];
                    }
                    $current['properties'][$k][] = $v;
                }
                continue;
            }
        }

        if ($current) {
            $result[] = $current;
        }

        return json_encode(
            $result,
            ($pretty ? JSON_PRETTY_PRINT : 0) | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
    }

    /**
     * Convert the JSON representation back to BG3 .txt format.
     */
    public static function jsonToTxt(string $json): string
    {
        $doc = json_decode($json, true);
        if (!\is_array($doc)) {
            throw new \InvalidArgumentException('jsonToTxt: invalid JSON document');
        }

        $lines = [];

        foreach ($doc as $entry) {
            if (!\is_array($entry) || !isset($entry['type'], $entry['id'])) {
                // skip malformed entries
                continue;
            }

            $type = strtolower((string) $entry['type']);
            $id   = (string) $entry['id'];
            $props = \is_array($entry['properties'] ?? null) ? $entry['properties'] : [];

            switch ($type) {
                case 'entry':
                    $lines[] = 'new entry "' . $id . '"';
                    if (isset($props['declared_type'])) {
                        $lines[] = 'type "' . $props['declared_type'] . '"';
                    }
                    break;

                case 'equipment':
                    $lines[] = 'new equipment "' . $id . '"';
                    if (!empty($entry['initialWeaponSet'])) {
                        $lines[] = 'add initialweaponset "' . $entry['initialWeaponSet'] . '"';
                    }
                    foreach (($entry['equipmentGroups'] ?? []) as $group) {
                        $lines[] = 'add equipmentgroup';
                        foreach ($group as $equipEntry) {
                            $lines[] = 'add equipment entry "' . $equipEntry . '"';
                        }
                    }
                    break;

                case 'treasuretable':
                    $lines[] = 'new treasuretable "' . $id . '"';
                    foreach (($entry['subtables'] ?? []) as $sub) {
                        $lines[] = 'new subtable "' . ($sub['weight'] ?? '') . '"';
                        if (isset($sub['StartLevel'])) $lines[] = 'StartLevel "' . (int)$sub['StartLevel'] . '"';
                        if (isset($sub['EndLevel']))   $lines[] = 'EndLevel "' . (int)$sub['EndLevel'] . '"';
                        foreach (($sub['items'] ?? []) as $item) {
                            $lines[] = 'object category "' . $item . '",1';
                        }
                    }
                    break;

                default:
                    // Unknown type: still attempt to dump properties
                    $lines[] = 'new ' . $type . ' "' . $id . '"';
                    break;
            }

            // Shared property serialization
            if (!empty($props)) {
                // data map
                if (isset($props['data']) && \is_array($props['data'])) {
                    foreach ($props['data'] as $k => $v) {
                        $lines[] = 'data "' . $k . '" "' . $v . '"';
                    }
                    unset($props['data']);
                }
                // using list
                if (isset($props['using']) && \is_array($props['using'])) {
                    foreach ($props['using'] as $v) {
                        $lines[] = 'using "' . $v . '"';
                    }
                    unset($props['using']);
                }
                // declared_type (already emitted for entry)
                if (isset($props['declared_type'])) {
                    // for other types we include it explicitly
                    if ($type !== 'entry') {
                        $lines[] = 'type "' . $props['declared_type'] . '"';
                    }
                    unset($props['declared_type']);
                }
                // remaining props
                foreach ($props as $k => $v) {
                    if (\is_array($v)) {
                        foreach ($v as $vv) {
                            $lines[] = $k . ' "' . $vv . '"';
                        }
                    } else {
                        $lines[] = $k . ' "' . $v . '"';
                    }
                }
            }

            $lines[] = ''; // blank line between entries
        }

        return implode("\n", $lines);
    }

    /* ------------------------------- utils ------------------------------- */

    private static function stripQuotes(string $str): string
    {
        return trim($str, " \t\n\r\0\x0B\"'");
    }
}
?>
