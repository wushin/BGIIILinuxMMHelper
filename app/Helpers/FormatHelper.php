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

    public static function directoryTreeToHtml(string $path, string $dir, bool $isRoot = true): string
    {
        $contents = scandir($dir);
        if (!$contents) return '';

        $html = $isRoot ? '<ul>' : '<ul class="nested">';

        foreach ($contents as $node) {
            if ($node === '.' || $node === '..') continue;

            $fullPath = $dir . '/' . $node;
            $relativePath = preg_replace('#^' . preg_quote($path . '/', '#') . '#', '', $fullPath);

            if (is_dir($fullPath)) {
                $html .= '<li><span class="caret">' . htmlspecialchars($node) . '</span>';
                $html .= self::directoryTreeToHtml($path, $fullPath, false);
                $html .= '</li>';
            } else {
                $encodedPath = htmlspecialchars(FilePathHelper::getFileUrlPath($path, $relativePath));
                $html .= '<li>' . htmlspecialchars($node) .
                         ' <button id="viewFile" onclick="display(\'' . $encodedPath . '\')">Open</button></li>';
            }
        }

        $html .= '</ul>';
        return $html;
    }

    public static function wrapEditableContent(string $content, ?string $term = null): string
    {
        $wrapper = '<div contenteditable="true" class="display" id="data" name="data">';
        $closer = '</div>';

        return $wrapper . self::formatData($content, $term) . $closer;
    }

    public static function formatForm(string $path, string $slug, string $key): string
    {
        $html = "<div id='fileName'>" . FilePathHelper::getFileName($path, $key) . 
                " <button id=\"saveFile\" onclick=\"submitMainForm()\">Save</button>";

        if (substr($key, -3) === "xml") {
            $html .= "<button id=\"saveFile\" onclick=\"addRowToForm('mainForm')\">AddNewRow</button>";
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
            $html .= '<input type="text" class="contentuid" name="data[content][' . $key . '][@attributes][contentuid]" value="' . htmlspecialchars($item['@attributes']['contentuid']) . '">';
            $html .= '<input type="text" class="version" name="data[content][' . $key . '][@attributes][version]" value="' . htmlspecialchars($item['@attributes']['version']) . '">';
            $html .= '<textarea name="data[content][' . $key . '][@value]">' . htmlspecialchars($item['@value']) . '</textarea>';
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
