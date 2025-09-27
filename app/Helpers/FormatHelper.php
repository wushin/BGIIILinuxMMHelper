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

use App\Helpers\FilePathHelper;

class FormatHelper
{

    protected static array $ignore = ['.', '..', '*.lsf'];

    public static function directoryTreeToHtml(string $path, string $dir, bool $isRoot = true, ?array $ignoreRules = null): string
    {
        // Use provided rules or fall back to a static default if present, else basic dot entries
        if ($ignoreRules === null) {
            $ignoreRules = property_exists(static::class, 'ignore') ? (static::$ignore ?? ['.', '..']) : ['.', '..'];
        }

        $contents = scandir($dir, SCANDIR_SORT_ASCENDING);
        if (!$contents) return '';

        $html = $isRoot ? '<ul>' : '<ul class="nested">';

        foreach ($contents as $node) {
            $fullPath     = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $node;
            $relativePath = preg_replace('#^' . preg_quote(rtrim($path, '/\\') . DIRECTORY_SEPARATOR, '#') . '#', '', $fullPath);

            // Apply ignore rules to both basename and relative path
            if (self::isIgnoredNode($node, $relativePath, $ignoreRules)) {
                continue;
            }

            if (is_dir($fullPath)) {
                // If the directory itself matches ignore, it was already skipped above.
                $html .= '<li><span class="caret">' . htmlspecialchars($node) . '</span>';
                $html .= self::directoryTreeToHtml($path, $fullPath, false, $ignoreRules);
                $html .= '</li>';
            } else {
                $encodedPath = htmlspecialchars(\App\Helpers\FilePathHelper::getFileUrlPath($path, $relativePath));
                $html .= '<li>' . htmlspecialchars($node) .
                         ' <button id="viewFile" class="appSystem" onclick="display(\'' . $encodedPath . '\')">Open</button></li>';
            }
        }

        $html .= '</ul>';
        return $html;
    }

    private static function isIgnoredNode(string $name, string $relPath, array $rules): bool
    {
        foreach ($rules as $rule) {
            $rule = (string)$rule;
            if ($rule === '') continue;

            if (strcasecmp($rule, $name) === 0 || strcasecmp($rule, $relPath) === 0) {
                return true;
            }

            $first = $rule[0];
            $last  = substr($rule, -1);
            if ($first === $last && in_array($first, ['/', '~', '#'], true)) {
                if (@preg_match($rule, $name) || @preg_match($rule, $relPath)) {
                    return true;
                }
                continue;
            }

            $rx = self::globToRegex($rule);
            if (preg_match($rx, $name) || preg_match($rx, $relPath)) {
                return true;
            }
        }
        return false;
    }

    private static function globToRegex(string $glob): string
    {
        if ($glob !== '' && $glob[0] === '.') {
            $glob = '*' . $glob;
        }

        $quoted = preg_quote($glob, '~');

        $quoted = preg_replace_callback('/(?<!\\\\)\\[(.*?)]/u', function ($m) {
            $inner = $m[1];
            // handle leading ! -> ^
            if ($inner !== '' && $inner[0] === '!') {
                $inner = '^' . substr($inner, 1);
            }
            return '[' . $inner . ']';
        }, $quoted) ?? $quoted;

        $quoted = strtr($quoted, [
            '\*' => '.*',
            '\?' => '.',
        ]);

        $quoted = str_replace('\/', '[/\\\\]', $quoted);

        return '~^' . $quoted . '$~i';
    }


    public static function wrapEditableContent(string $content, ?string $term = null): string
    {
        $wrapper = '<div contenteditable="true" spellcheck="false" class="display" id="data" name="data">';
        $closer = '</div>';

        return $wrapper . self::formatData($content, $term) . $closer;
    }

    public static function formatForm(string $path, string $slug, string $key): string
    {
        $html = "<div id='fileName'>" . FilePathHelper::getFileName($path, $key) . 
                " <button id=\"saveFile\" class=\"appSystem\" onclick=\"submitMainForm()\">Save</button>";

        if (substr($key, -3) === "xml") {
            $html .= "<button id=\"saveFile\" class=\"appSystem\" onclick=\"addRowToForm('mainForm')\">AddNewRow</button>";
        }

        $html .= "</div><form id=\"mainForm\">";
        $html .= "<input type=\"hidden\" name=\"fileName\" value=\"" . FilePathHelper::getFileSavePath($path, $key) . "\"/>";
        $html .= "<input type=\"hidden\" name=\"path\" value=\"" . $path . "\"/>";
        $html .= "<input type=\"hidden\" name=\"slug\" value=\"" . $slug . "\"/>";
        return $html;
    }

    public static function formatData(string $data, ?string $term = null): string
    {
        if (!is_null($term)) {
            $escapedTerm = htmlspecialchars($term, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5);
            return preg_replace(
                '/' . preg_quote($escapedTerm, '/') . '/i',
                '<span class="highlight">$0</span>',
                htmlspecialchars($data, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5)
            );
        } else {
            return htmlspecialchars($data, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5);
        }
    }

    public static function langToForm(array|string $lang): string
    {
        if (!is_array($lang)) {
            return "Empty";
        }

        $html = "";
        foreach ($lang['contentList']['content'] as $key => $item) {
            $html .= '<div class="langline" id="' . $key . '">';
            $html .= '<input type="text" spellcheck="false" class="contentuid" name="data[content][' . $key . '][@attributes][contentuid]" value="' . htmlspecialchars($item['@attributes']['contentuid']) . '">';
            $html .= '<input type="text" spellcheck="false" class="version" name="data[content][' . $key . '][@attributes][version]" value="' . htmlspecialchars($item['@attributes']['version']) . '">';
            $html .= '<textarea spellcheck="false" name="data[content][' . $key . '][@value]">' . htmlspecialchars($item['@value']) . '</textarea>';
            $html .= '<button class="rmDiv" onclick="removeDivById(\'' . $key . '\')">X</button>';
            $html .= "</div>";
       }

       $html .= '<input type="hidden" name="nextKey" value="' . (count($lang['contentList']['content'])) . '" />';
       return $html;
    }

    public static function stripQuotes(string $str): string
    {
        return trim($str, "\"'");
    }
}
?>
