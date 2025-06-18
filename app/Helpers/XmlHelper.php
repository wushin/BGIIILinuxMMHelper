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

class XmlHelper
{
    public static function minifyEmptyTags(string $xml): string {
        return preg_replace('/"><\\/(.*?)\\>/', '"/>', $xml);
    }

    public static function createFormattedDOM(string $xml): \DOMDocument {
        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xml);
        return $dom;
    }

    public static function injectXmlNamespaces(string $xml, string $tagName, array $namespaces): string
    {
        $namespaceString = '';
        foreach ($namespaces as $prefix => $url) {
            $namespaceString .= " xmlns:$prefix=\"$url\"";
        }

        return preg_replace(
            "/<$tagName>/",
            "<$tagName$namespaceString>",
            $xml
        );
    }

    public static function createArray(string $xmlContent): array
    {
        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->loadXML($xmlContent, LIBXML_NOCDATA);
        $root = $dom->documentElement;

        return [ $root->nodeName => self::elementToArray($root) ];
    }

    public static function createXML(string $rootElement, array $data): \DOMDocument
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $root = $dom->createElement($rootElement);
        $dom->appendChild($root);

        self::arrayToXmlRecursive($data, $root, $dom);

        return $dom;
    }

    private static function elementToArray(\DOMElement $element): array
    {
        $result = [];

        // Attributes
        if ($element->hasAttributes()) {
            foreach ($element->attributes as $attr) {
                $result['@attributes'][$attr->nodeName] = $attr->nodeValue;
            }
        }

        // Children and text
        $children = [];
        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMText) {
                $text = trim($child->nodeValue);
                if ($text !== '') {
                    $result['@value'] = $text;
                }
            } elseif ($child instanceof \DOMElement) {
                $childName = $child->nodeName;
                $childArray = self::elementToArray($child);

                if (!isset($children[$childName])) {
                    $children[$childName] = [];
                }

                $children[$childName][] = $childArray;
            }
        }

        foreach ($children as $key => $value) {
            $result[$key] = count($value) === 1 ? $value[0] : $value;
        }

        return $result;
    }

    private static function arrayToXmlRecursive(array $data, \DOMElement $parent, \DOMDocument $dom): void
    {
        foreach ($data as $key => $value) {
            if ($key === '@attributes') {
                foreach ($value as $attrName => $attrValue) {
                    $parent->setAttribute($attrName, $attrValue);
                }
            } elseif ($key === '@value') {
                $parent->appendChild($dom->createTextNode($value));
            } elseif (is_array($value) && is_numeric(array_key_first($value))) {
                foreach ($value as $subElement) {
                    $child = $dom->createElement($key);
                    $parent->appendChild($child);
                    if (is_array($subElement)) {
                        self::arrayToXmlRecursive($subElement, $child, $dom);
                    } else {
                        $child->appendChild($dom->createTextNode($subElement));
                    }
                }
            } elseif (is_array($value)) {
                $child = $dom->createElement($key);
                $parent->appendChild($child);
                self::arrayToXmlRecursive($value, $child, $dom);
            } else {
                $child = $dom->createElement($key, htmlspecialchars($value));
                $parent->appendChild($child);
            }
        }
    }
}
?>
