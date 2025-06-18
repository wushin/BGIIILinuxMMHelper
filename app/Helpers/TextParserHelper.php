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
    public static function txt2Array(array $data): array
    {
        $result = [];
        $current = null;

        foreach ($data as $line) {
            $line = trim($line);
            if (stripos($line, 'new entry') === 0) {
                if ($current) $result[] = $current;
                $id = self::stripQuotes(trim(str_replace('new entry', '', $line)));
                $current = ['type' => 'entry', 'id' => $id, 'properties' => []];
            } elseif (stripos($line, 'new equipment') === 0) {
                if ($current) $result[] = $current;
                $id = self::stripQuotes(trim(str_replace('new equipment', '', $line)));
                $current = ['type' => 'equipment', 'id' => $id, 'initialWeaponSet' => null, 'equipmentGroups' => []];
            } elseif (stripos($line, 'new treasuretable') === 0) {
                if ($current) $result[] = $current;
                $id = self::stripQuotes(trim(str_replace('new treasuretable', '', $line)));
                $current = ['type' => 'treasuretable', 'id' => $id, 'subtables' => []];
            } elseif ($current && $current['type'] === 'equipment') {
                if (stripos($line, 'add initialweaponset') === 0) {
                    $current['initialWeaponSet'] = self::stripQuotes(trim(str_replace('add initialweaponset', '', $line)));
                } elseif (stripos($line, 'add equipment entry') === 0) {
                    $entry = self::stripQuotes(trim(str_replace('add equipment entry', '', $line)));
                    $lastGroupIndex = count($current['equipmentGroups']) - 1;
                    if ($lastGroupIndex < 0) $current['equipmentGroups'][] = [];
                    $current['equipmentGroups'][$lastGroupIndex][] = $entry;
                } elseif (stripos($line, 'add equipmentgroup') === 0) {
                    $current['equipmentGroups'][] = [];
                }
            } elseif ($current && $current['type'] === 'treasuretable') {
                if (stripos($line, 'new subtable') === 0) {
                    $weight = self::stripQuotes(trim(str_replace('new subtable', '', $line)));
                    $current['subtables'][] = ['type' => 'subtable', 'weight' => $weight, 'items' => []];
                } elseif (preg_match('/^object category\s+"?([^"]+)"?,\d.*$/', $line, $matches)) {
                    $i = count($current['subtables']) - 1;
                    if ($i >= 0) $current['subtables'][$i]['items'][] = self::stripQuotes($matches[1]);
                } elseif (preg_match('/^(StartLevel|EndLevel)\s+"?(\d+)"?$/', $line, $matches)) {
                    $i = count($current['subtables']) - 1;
                    if ($i >= 0) $current['subtables'][$i][$matches[1]] = (int)$matches[2];
                }
            } elseif ($current && in_array($current['type'], ['entry', 'equipment', 'treasuretable'])) {
                if (preg_match('/^(data|using|type)\s+"([^"]+)"\s*"?(.*?)"?$/', $line, $matches)) {
                    $key = $matches[1];
                    $subkey = self::stripQuotes($matches[2]);
                    $value = self::stripQuotes($matches[3]);

                    if (!isset($current['properties'][$key])) {
                        $current['properties'][$key] = [];
                    }

                    if ($key === 'data') {
                        $current['properties'][$key][] = [$subkey => $value];
                    } elseif ($key === 'using') {
                        $current['properties'][$key][] = $subkey;
                    } elseif ($key === 'type') {
                        $current['properties']['declared_type'] = $subkey;
                    }
                } elseif (preg_match('/^(\w+)\s+"([^"]+)"$/', $line, $matches)) {
                    $current['properties'][$matches[1]] = self::stripQuotes($matches[2]);
                }
            }
        }

        if ($current) $result[] = $current;
        return $result;
    }

    public static function string2Array(string $txt): array
    {
        $data = file('data://text/plain,' . urlencode($txt), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        return self::txt2Array($data);
    }

    public static function array2Txt(array $txt): string
    {
        $lines = [];
        foreach ($txt as $entry) {
            switch ($entry['type']) {
                case 'entry':
                    $lines[] = 'new entry "' . $entry['id'] . '"';
                    if (isset($entry['properties']['declared_type'])) {
                        $lines[] = 'type "' . $entry['properties']['declared_type'] . '"';
                    }
                    break;
                case 'equipment':
                    $lines[] = 'new equipment "' . $entry['id'] . '"';
                    if ($entry['initialWeaponSet']) {
                        $lines[] = 'add initialweaponset "' . $entry['initialWeaponSet'] . '"';
                    }
                    foreach ($entry['equipmentGroups'] as $group) {
                        $lines[] = 'add equipmentgroup';
                        foreach ($group as $equipEntry) {
                            $lines[] = 'add equipment entry "' . $equipEntry . '"';
                        }
                    }
                    break;
                case 'treasuretable':
                    $lines[] = 'new treasuretable "' . $entry['id'] . '"';
                    foreach ($entry['subtables'] as $sub) {
                        $lines[] = 'new subtable "' . $sub['weight'] . '"';
                        if (isset($sub['StartLevel'])) {
                            $lines[] = 'StartLevel "' . $sub['StartLevel'] . '"';
                        }
                        if (isset($sub['EndLevel'])) {
                            $lines[] = 'EndLevel "' . $sub['EndLevel'] . '"';
                        }
                        foreach ($sub['items'] as $item) {
                            $lines[] = 'object category "' . $item . '",1';
                        }
                    }
                    break;
            }

            // Shared properties
            if (!empty($entry['properties'])) {
                foreach ($entry['properties'] as $key => $value) {
                    if ($key === 'data' && is_array($value)) {
                        foreach ($value as $pair) {
                            foreach ($pair as $k => $v) {
                                $lines[] = 'data "' . $k . '" "' . $v . '"';
                            }
                        }
                    } elseif ($key === 'using' && is_array($value)) {
                        foreach ($value as $v) {
                            $lines[] = 'using "' . $v . '"';
                        }
                    } elseif ($key !== 'declared_type') {
                        if (is_array($value)) {
                            foreach ($value as $v) {
                                $lines[] = $key . ' "' . $v . '"';
                            }
                        } else {
                            $lines[] = $key . ' "' . $value . '"';
                        }
                    }
                }
            }

            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    private static function stripQuotes(string $str): string
    {
        return trim($str, "\"'");
    }
}
?>
