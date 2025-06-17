<?php

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
    // Optional: alias for compatibility (safe no-op in your codebase)
    public static function init(...$args): void {}
}
?>
