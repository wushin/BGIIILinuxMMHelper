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

class FilePathHelper
{
    /**
     * Join a project root with a relative path using PathResolver.
     * $root is one of: GameData|MyMods|UnpackedMods (legacy "AllMods" is mapped to UnpackedMods).
     */
    public static function join(string $root, string $rel): string
    {
        $root = self::normalizeRoot($root);
        return service('pathResolver')->join($root, $rel);
    }

    /**
     * Absolute path to a mod folder (kept for callers).
     * Equivalent to: join($root, $slug)
     */
    public static function getAbsPath(string $root, string $slug): string
    {
        return self::join($root, $slug);
    }

    /**
     * Absolute file path inside a mod (kept for callers).
     * Equivalent to: join($root, $slug . '/' . $rel)
     */
    public static function getAbsFileName(string $root, string $slug, string $rel): string
    {
        $rel = ltrim($rel, '/');
        return self::join($root, rtrim($slug, '/').'/'.$rel);
    }

    /**
     * Test if a file is "non-trivial" (at least 3 non-empty lines) via ContentService.
     */
    public static function testFile(string $absFile): bool
    {
        try {
            $bytes = service('contentService')->read($absFile);
        } catch (\Throwable $e) {
            return false;
        }
        if ($bytes === '' || $bytes === null) return false;
        $lines = preg_split("/\r\n|\r|\n/", $bytes);
        $nonEmpty = 0;
        foreach ($lines as $l) {
            if (trim($l) !== '') $nonEmpty++;
            if ($nonEmpty > 3) return true;
        }
        return false;
    }

    private static function normalizeRoot(string $root): string
    {
        $r = strtolower($root);
        return match ($r) {
            'gamedata'                 => 'GameData',
            'mymods', 'mods'           => 'MyMods',
            'allmods', 'unpackedmods'  => 'UnpackedMods',
            default                    => $root, // assume canonical already
        };
    }
}
?>
