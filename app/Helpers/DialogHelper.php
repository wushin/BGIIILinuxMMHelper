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

/**
 * DialogHelper (CI4)
 *
 * Public JSON-only API:
 *  - primaryJson(string $lsxXml, string $englishXml): string
 *  - secondaryJson(string $lsxXml): string
 *  - bothJson(string $lsxXml, string $englishXml): string
 *  - buildLsxFromPrimaryJson(string $primaryJson): string   // returns LSX XML text
 *
 * Notes:
 *  - Primary JSON mirrors LSX structure:
 *      { "name": "<tag>", "attrs": { ... }, "children": [ ... ] }
 *    and resolves <attribute type="TranslatedString" handle="..."> into attrs["text"].
 *  - Secondary JSON:
 *      { "roots": [...], "graph": { "<node-uuid>": ["<child-uuid>", ...], ... } }
 *    where graph includes ONLY <node id="node"> entries; children are the UUIDs under:
 *      <node id="children">...<node id="child"><attribute id="UUID" .../></node>
 *    Nodes with no children are kept with an empty array.
 */
class DialogHelper
{
    /* ===================== Public API ===================== */

    /** Primary JSON string from LSX + english XML strings. */
    public function primaryJson(string $lsxXml, string $englishXml): string
    {
        $lsx     = $this->loadXmlString($lsxXml);
        $english = $this->loadXmlString($englishXml);
        $handles = $this->buildHandleMapFromXml($english);

        $primary = $this->elementToArrayFromXml($lsx, $handles);
        $json = json_encode($primary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \RuntimeException("JSON encode failed: " . json_last_error_msg());
        }
        return $json;
    }

    /** Secondary JSON string ({roots, graph}) from LSX XML string. */
    public function secondaryJson(string $lsxXml): string
    {
        $lsx = $this->loadXmlString($lsxXml);

        $payload = [
            'roots' => $this->collectRootUuidsFromXml($lsx),
            'graph' => $this->buildGraphNodesOnlyFromXml($lsx),
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \RuntimeException("JSON encode failed: " . json_last_error_msg());
        }
        return $json;
    }

    /** One-shot: returns a JSON object string with both primary & secondary. */
    public function bothJson(string $lsxXml, string $englishXml): string
    {
        $lsx     = $this->loadXmlString($lsxXml);
        $english = $this->loadXmlString($englishXml);
        $handles = $this->buildHandleMapFromXml($english);

        $primaryArr = $this->elementToArrayFromXml($lsx, $handles);
        $secondaryArr = [
            'roots' => $this->collectRootUuidsFromXml($lsx),
            'graph' => $this->buildGraphNodesOnlyFromXml($lsx),
        ];

        $json = json_encode(
            ['primary' => $primaryArr, 'secondary' => $secondaryArr],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );
        if ($json === false) {
            throw new \RuntimeException("JSON encode failed: " . json_last_error_msg());
        }
        return $json;
    }

    /**
     * Build LSX (XML string) from a primary JSON string.
     * (JSON in → XML out; this one intentionally returns XML, not JSON.)
     */
    public function buildLsxFromPrimaryJson(string $primaryJson): string
    {
        $data = json_decode($primaryJson, true);
        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException("Invalid JSON: " . json_last_error_msg());
        }
        if (!isset($data['name'])) {
            throw new \InvalidArgumentException("Primary JSON root missing 'name'.");
        }

        $xml = '<?xml version="1.0" encoding="utf-8"?>' . "\n";
        $xml .= $this->renderElement($data, 0);
        return $xml;
    }

    /* ===================== Internal: XML & Maps ===================== */

    private function loadXmlString(string $xml): \SimpleXMLElement
    {
        $xml = trim($xml);
        if ($xml === '') {
            throw new \InvalidArgumentException('Empty XML string.');
        }
        \libxml_use_internal_errors(true);
        $sx = \simplexml_load_string(
            $xml,
            "SimpleXMLElement",
            LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET | LIBXML_BIGLINES | LIBXML_NOCDATA
        );
        if ($sx === false) {
            $errs = array_map(fn($e) => trim($e->message ?? ''), \libxml_get_errors() ?: []);
            \libxml_clear_errors();
            throw new \RuntimeException('Failed to parse XML: ' . implode(' | ', array_filter($errs)));
        }
        return $sx;
    }

    /** <content contentuid="...">text</content> → [handle => text] */
    private function buildHandleMapFromXml(\SimpleXMLElement $english): array
    {
        $map = [];
        $contents = $english->xpath("//content");
        if ($contents) {
            foreach ($contents as $c) {
                $uid  = (string)($c['contentuid'] ?? '');
                $text = trim((string)$c);
                if ($uid !== '') {
                    $map[$uid] = $text;
                }
            }
        }
        return $map;
    }

    /* ===================== Internal: Primary Parse ===================== */

    /**
     * Convert any XML element to the primary-array node:
     *   [ 'name' => tag, 'attrs' => [...], 'children' => [...] ]
     * For <attribute type="TranslatedString" handle="..."> add attrs['text'] with resolved value.
     */
    private function elementToArrayFromXml(\SimpleXMLElement $el, array $handleMap): array
    {
        $node = [
            'name'     => $el->getName(),
            'attrs'    => [],
            'children' => []
        ];

        foreach ($el->attributes() as $k => $v) {
            $node['attrs'][(string)$k] = (string)$v;
        }

        if (\strcasecmp($node['name'], 'attribute') === 0) {
            $type   = $node['attrs']['type']   ?? '';
            $handle = $node['attrs']['handle'] ?? '';
            if (\strcasecmp($type, 'TranslatedString') === 0 && $handle !== '') {
                $node['attrs']['text'] = array_key_exists($handle, $handleMap) ? $handleMap[$handle] : null;
            }
        }

        foreach ($el->children() as $child) {
            $node['children'][] = $this->elementToArrayFromXml($child, $handleMap);
        }

        return $node;
    }

    /* ===================== Internal: Secondary (roots + graph) ===================== */

    /** Collect RootNodes UUIDs (attribute form + list form). */
    private function collectRootUuidsFromXml(\SimpleXMLElement $xml): array
    {
        $roots = [];
        foreach ($xml->xpath("//attribute[@id='RootNodes' and @value]") as $a) {
            $roots[] = (string)$a['value'];
        }
        $listAttrs = $xml->xpath("//node[@id='RootNodes']//attribute[@id='UUID' and @value]");
        if ($listAttrs) {
            foreach ($listAttrs as $ra) {
                $roots[] = (string)$ra['value'];
            }
        }
        return array_values(array_unique(array_filter($roots, fn($x) => $x !== '')));
    }

    /**
     * Graph for ONLY <node id="node">:
     *   key = node UUID
     *   val = UUIDs under <node id="children">...<node id="child"><attribute id="UUID" .../></node>
     * Nodes with no children are kept as [].
     */
    private function buildGraphNodesOnlyFromXml(\SimpleXMLElement $xml): array
    {
        $graph = [];
        $nodes = $xml->xpath(".//node[@id='node']");
        if ($nodes) {
            foreach ($nodes as $entry) {
                // Node UUID
                $uuidAttr = $entry->xpath("attribute[@id='UUID' and @value]");
                if (!$uuidAttr || !isset($uuidAttr[0])) {
                    continue;
                }
                $selfUuid = (string)$uuidAttr[0]['value'];
                if ($selfUuid === '') {
                    continue;
                }

                // Immediate children
                $children = [];
                $uuidAttrs = $entry->xpath(".//node[@id='children']//node[@id='child']/attribute[@id='UUID' and @value]");
                if ($uuidAttrs) {
                    foreach ($uuidAttrs as $a) {
                        $val = (string)$a['value'];
                        if ($val !== '' && \strcasecmp($val, $selfUuid) !== 0) {
                            $children[] = $val;
                        }
                    }
                }

                // De-dupe, preserve order
                $seen = [];
                $flat = [];
                foreach ($children as $c) {
                    if (isset($seen[$c])) {
                        continue;
                    }
                    $seen[$c] = true;
                    $flat[] = $c;
                }
                $graph[$selfUuid] = $flat;
            }
        }
        return $graph;
    }

    /* ===================== Internal: Serializer ===================== */

    private function xmlAttrEscape(string $s): string
    {
        return \htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    /** Deterministic attribute order (nicer diffs). */
    private function orderAttrs(array $attrs): array
    {
        $priority = ['id', 'key', 'type', 'handle', 'value', 'version'];
        $out = [];
        foreach ($priority as $k) {
            if (array_key_exists($k, $attrs)) {
                $out[$k] = $attrs[$k];
                unset($attrs[$k]);
            }
        }
        \ksort($attrs, SORT_STRING);
        foreach ($attrs as $k => $v) {
            $out[$k] = $v;
        }
        return $out;
    }

    /** Drop attrs['text'] on <attribute type="TranslatedString" ...>. */
    private function sanitizeAttrsForNode(array $node): array
    {
        $attrs = $node['attrs'] ?? [];
        if (
            isset($node['name']) &&
            \strtolower($node['name']) === 'attribute' &&
            isset($attrs['type']) &&
            \strcasecmp($attrs['type'], 'TranslatedString') === 0 &&
            array_key_exists('text', $attrs)
        ) {
            unset($attrs['text']);
        }
        return $attrs;
    }

    private function renderElement(array $node, int $indentLevel = 0): string
    {
        $name     = $node['name']     ?? '';
        $children = $node['children'] ?? [];
        if ($name === '') {
            return '';
        }

        $indent   = \str_repeat("\t", $indentLevel);
        $attrsArr = $this->orderAttrs($this->sanitizeAttrsForNode($node));

        $attrsStr = '';
        foreach ($attrsArr as $k => $v) {
            $attrsStr .= ' ' . $k . '="' . $this->xmlAttrEscape((string)$v) . '"';
        }

        if (empty($children)) {
            return "{$indent}<{$name}{$attrsStr} />\n";
        }

        $buf = "{$indent}<{$name}{$attrsStr}>\n";
        foreach ($children as $child) {
            if (!is_array($child)) {
                continue;
            }
            $buf .= $this->renderElement($child, $indentLevel + 1);
        }
        $buf .= "{$indent}</{$name}>\n";
        return $buf;
    }
}

