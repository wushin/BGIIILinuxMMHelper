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
    /* ---------------------------- small utilities ---------------------------- */

    public static function minifyEmptyTags(string $xml): string
    {
        return preg_replace('/"><\\/(.*?)\\>/', '"/>', $xml);
    }

    public static function createFormattedDOM(string $xml): \DOMDocument
    {
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

    /* ------------------------------ JSON-centric API ------------------------------ */

    /**
     * Parse XML and return a JSON string.
     * The JSON shape mirrors the previous array structure:
     * - Attributes under "@attributes"
     * - Text content under "@value"
     * - Child elements grouped by name; singletons are objects, multiples are arrays
     */
    public static function createJson(string $xmlContent, bool $pretty = true): string
    {
        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->loadXML($xmlContent, LIBXML_NOCDATA);
        $root = $dom->documentElement;

        $assoc = [$root->nodeName => self::elementToAssoc($root)];
        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        if ($pretty) {
            $flags |= JSON_PRETTY_PRINT;
        }

        $json = json_encode($assoc, $flags);
        if ($json === false) {
            throw new \RuntimeException('JSON encode failed: ' . json_last_error_msg());
        }
        return $json;
    }

    /**
     * Create an XML DOM from JSON (or from an array for backward-compat).
     * $data may be:
     *   - JSON string with the same structure produced by createJson()
     *   - An associative array using "@attributes", "@value", etc.
     */
    public static function createXML(string $rootElement, array|string $data): \DOMDocument
    {
        if (is_string($data)) {
            $decoded = json_decode($data, true);
            if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
                throw new \InvalidArgumentException('Invalid JSON: ' . json_last_error_msg());
            }
            // If the JSON was produced by createJson(), it likely has a single root key.
            // If that single root key differs from $rootElement, we still honor $rootElement
            // as the tag name and use the value as the payload.
            if (is_array($decoded) && count($decoded) === 1 && isset($decoded[array_key_first($decoded)])) {
                $decoded = $decoded[array_key_first($decoded)];
            }
            $data = $decoded ?? [];
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $root = $dom->createElement($rootElement);
        $dom->appendChild($root);

        self::arrayToXmlRecursive($data, $root, $dom);

        return $dom;
    }

    /* ---------------------------- Back-compat shim ---------------------------- */

    /**
     * Backward compatibility for code that expects an array.
     * Now implemented via createJson() so the JSON structure is the source of truth.
     */
    public static function createArray(string $xmlContent): array
    {
        $json = self::createJson($xmlContent, false);
        $assoc = json_decode($json, true);
        if (!is_array($assoc)) {
            throw new \RuntimeException('Failed to convert XML to array via JSON pipeline.');
        }
        return $assoc;
    }

    /* ------------------------------- internals ------------------------------- */

    private static function elementToAssoc(\DOMElement $element): array
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
                $childName  = $child->nodeName;
                $childAssoc = self::elementToAssoc($child);

                if (!isset($children[$childName])) {
                    $children[$childName] = [];
                }
                $children[$childName][] = $childAssoc;
            }
        }

        // Collapse singletons
        foreach ($children as $key => $value) {
            $result[$key] = (count($value) === 1) ? $value[0] : $value;
        }

        return $result;
    }

    private static function arrayToXmlRecursive(array $data, \DOMElement $parent, \DOMDocument $dom): void
    {
        foreach ($data as $key => $value) {
            if ($key === '@attributes') {
                foreach ($value as $attrName => $attrValue) {
                    $parent->setAttribute($attrName, (string) $attrValue);
                }
                continue;
            }

            if ($key === '@value') {
                $parent->appendChild($dom->createTextNode((string) $value));
                continue;
            }

            // Numeric list (multiple children with the same tag)
            if (is_array($value) && array_key_exists(0, $value) && is_int(array_key_first($value))) {
                foreach ($value as $subElement) {
                    $child = $dom->createElement($key);
                    $parent->appendChild($child);
                    if (is_array($subElement)) {
                        self::arrayToXmlRecursive($subElement, $child, $dom);
                    } else {
                        $child->appendChild($dom->createTextNode((string) $subElement));
                    }
                }
                continue;
            }

            // Nested object
            if (is_array($value)) {
                $child = $dom->createElement($key);
                $parent->appendChild($child);
                self::arrayToXmlRecursive($value, $child, $dom);
                continue;
            }

            // Scalar child
            $child = $dom->createElement($key);
            $parent->appendChild($child);
            $child->appendChild($dom->createTextNode((string) $value));
        }
    }
}
?>
