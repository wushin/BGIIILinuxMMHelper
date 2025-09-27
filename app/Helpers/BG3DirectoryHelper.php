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

class BG3DirectoryHelper
{
    protected static array $ignore = ['..', '.', '.git', 'ToDo', '.gitignore', 'tools', 'BG3LinuxHelper'];

    public static function getMyMods(): array
    {
        return self::scanDirectory(getenv('bg3LinuxHelper.MyMods'),true);
    }

    public static function getAllMods(): array
    {
        return self::scanDirectory(getenv('bg3LinuxHelper.AllMods'));
    }

    public static function getGameData(): array
    {
        return self::scanDirectory(getenv('bg3LinuxHelper.GameData'));
    }

    public static function getAll(): array
    {
        return [
            'MyMods' => self::getMyMods(),
            'AllMods' => self::getAllMods(),
            'GameData' => self::getGameData(),
        ];
    }

    protected static function scanDirectory(string $path, bool $dirsOnly = false): array
    {
        if (!is_dir($path)) {
            return [];
        }

        $path    = rtrim($path, DIRECTORY_SEPARATOR);
        $entries = array_values(array_diff(scandir($path), self::$ignore)); // keeps scandir order

        if ($dirsOnly) {
            $entries = array_values(array_filter($entries, function (string $name) use ($path) {
                return is_dir($path . DIRECTORY_SEPARATOR . $name);
            }));
        }

        return $entries;
    }
}
?>
