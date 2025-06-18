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
    public static function getUrlPath(string $path, string $slug): string
    {
        return $path . "/" . $slug;
    }

    public static function getAbsPath(string $path, string $slug): string
    {
        return FCPATH . $path . "/" . $slug;
    }

    public static function getFileUrlPath(string $path, string $key): string
    {
        return "/display/" . $path . "/" . preg_replace('/' . str_replace("/", "\/", FCPATH) . '(.*?)\//', '', $key);
    }

    public static function getFileSavePath(string $path, string $key): string
    {
        return FCPATH . $path . "/" . preg_replace('/' . str_replace("/", "\/", FCPATH) . '(.*?)\//', '', $key);
    }

    public static function getFileName(string $path, string $key): string
    {
        return preg_replace('/' . str_replace("/", "\/", FCPATH) . $path . '\/(.*?)\//', '', $key);
    }

    public static function getAbsFileName(string $path, string $slug, string $key): string
    {
        return FCPATH . $path . "/" . addslashes(urldecode($slug)) . "/" . $key;
    }

    public static function testFile(string $file): bool
    {
        $lines = file($file);
        return count($lines) > 3;
    }
}

