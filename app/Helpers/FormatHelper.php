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

use App\Helpers\XmlHelper;

class FormatHelper
{
    /**
     * Wrap editable content with a consistent chrome and optional term highlight.
     */
    public static function wrapEditableContent(string $body, ?string $term = null): string
    {
        if ($term !== null && $term !== '') {
            $body = self::highlight($body, $term);
        }

        return <<<HTML
<div class="display editable">
  {$body}
</div>
HTML;
    }

    /**
     * Render a <div>-based editor for a Localization XML provided as JSON.
     * Accepts JSON like: {"contentList":{"content":[{"@attributes":{"contentuid":"..."},"@value":"Text"}, ...] }}
     * You can obtain this via XmlHelper::createJson($xml).
     */
    public static function renderLangEditorFromJson(string $langJson): string
    {
        $data = json_decode($langJson, true);
        if (!is_array($data)) {
            return '<div class="display flash-red">Invalid language JSON</div>';
        }

        // Accept either wrapped ({"contentList":{...}}) or the inner object directly
        if (count($data) === 1 && isset($data[array_key_first($data)])) {
            $data = $data[array_key_first($data)];
        }

        $contentList = $data['content'] ?? ($data['contentList']['content'] ?? []);
        // Normalize to array-of-items
        if (!is_array($contentList)) {
            $contentList = [];
        } elseif (isset($contentList['@attributes']) || isset($contentList['@value'])) {
            $contentList = [ $contentList ];
        }

        $html = '<div class="lang-editor">';
        $i = 0;
        foreach ($contentList as $item) {
            $uid = $item['@attributes']['contentuid'] ?? '';
            $val = $item['@value'] ?? '';

            $key = 'content_' . $i;
            $html .= '<div class="lang-row" id="' . htmlspecialchars($key) . '" data-uid="' . htmlspecialchars($uid) . '">';
            $html .=   '<span class="uid">' . htmlspecialchars($uid) . '</span>';
            $html .=   '<textarea name="content[' . $i . ']" class="content-text" rows="2">' . htmlspecialchars($val) . '</textarea>';
            $html .=   '<button type="button" class="rmDiv" onclick="removeDivById(\'' . htmlspecialchars($key) . '\')">X</button>';
            $html .= '</div>';
            $i++;
        }
        $html .= '<input type="hidden" name="nextKey" value="' . $i . '" />';
        $html .= '</div>';

        return $html;
    }

    /**
     * Convenience wrapper when you have raw XML instead of JSON.
     */
    public static function renderLangEditorFromXml(string $xml): string
    {
        $json = XmlHelper::createJson($xml, true);
        return self::renderLangEditorFromJson($json);
    }

    /**
     * Very simple substring highlighter; escapes HTML.
     */
    private static function highlight(string $html, string $term): string
    {
        $safeTerm = htmlspecialchars($term, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $parts = explode($term, $html);
        if (count($parts) === 1) {
            return $html;
        }
        return implode('<mark>' . $safeTerm . '</mark>', $parts);
    }

    public static function stripQuotes(string $str): string
    {
        return trim($str, "\"'");
    }
}
?>

