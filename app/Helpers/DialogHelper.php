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
 * DialogHelper (CI4) — Array-first, uses XmlHelper for all XML parsing/building.
 *
 * Primary array shape (same as before):
 *   [
 *     'name'     => '<tag>',
 *     'attrs'    => ['id'=>'...', 'type'=>'...', ...],
 *     'children' => [ <childNode> ... ]
 *   ]
 *
 * Secondary array shape:
 *   [
 *     'roots' => [ '<uuid>', ... ],
 *     'graph' => [ '<node-uuid>' => ['<child-uuid>', ...], ... ]
 *   ]
 *
 * Public API (all return arrays except builders to XML):
 *   - primaryDialogMulti(string $lsxXml, array $englishXmlStrings, bool $laterWins=true): array
 *   - bothDialogMulti(string $lsxXml, array $englishXmlStrings, bool $laterWins=true): array
 *   - primaryDialogFromHandles(string $lsxXml, array $handleMap): array
 *   - bothDialogFromHandles(string $lsxXml, array $handleMap): array
 *   - primaryDialog(string $lsxXml, string $englishXml): array                 // single english.xml
 *   - bothDialog(string $lsxXml, string $englishXml): array                    // single english.xml
 *   - secondaryDialog(string $lsxXml): array
 *   - buildLsxFromPrimaryArray(array $primary): string                       // ARRAY → LSX XML
 *   - buildLsxFromPrimaryDialog(string $primaryDialog): string                   // JSON  → LSX XML (convenience)
 */
class DialogHelper
{
    /* ===================== Public API (multi-XML & handles) ===================== */

    /** Primary ARRAY from LSX + MANY english.xml-like strings. */
    public function primaryDialogMulti(string $lsxXml, array $englishXmlStrings, bool $laterWins = true): array
    {
        [$rootName, $lsxArr] = $this->loadXmlAsArr($lsxXml);                   // via XmlHelper
        $handles             = $this->buildHandleMapFromEnglishStrings($englishXmlStrings, $laterWins);

        return $this->xmlHelperTreeToPrimary($rootName, $lsxArr, $handles);
    }

    /** Both arrays (primary + secondary) using MANY english.xml-like strings. */
    public function bothDialogMulti(string $lsxXml, array $englishXmlStrings, bool $laterWins = true): array
    {
        [$rootName, $lsxArr] = $this->loadXmlAsArr($lsxXml);
        $handles             = $this->buildHandleMapFromEnglishStrings($englishXmlStrings, $laterWins);

        return [
            'primary'   => $this->xmlHelperTreeToPrimary($rootName, $lsxArr, $handles),
            'secondary' => $this->secondaryFromXmlHelperTree($lsxArr),
        ];
    }

    /** Primary ARRAY using a prebuilt handle map (handle => text). */
    public function primaryDialogFromHandles(string $lsxXml, array $handleMap): array
    {
        [$rootName, $lsxArr] = $this->loadXmlAsArr($lsxXml);
        return $this->xmlHelperTreeToPrimary($rootName, $lsxArr, $handleMap);
    }

    /** Both ARRAYS using a prebuilt handle map. */
    public function bothDialogFromHandles(string $lsxXml, array $handleMap): array
    {
        [$rootName, $lsxArr] = $this->loadXmlAsArr($lsxXml);
        return [
            'primary'   => $this->xmlHelperTreeToPrimary($rootName, $lsxArr, $handleMap),
            'secondary' => $this->secondaryFromXmlHelperTree($lsxArr),
        ];
    }

    /* ===================== Back-compat (single english.xml) ===================== */

    /** Primary ARRAY from LSX + a single english.xml string. */
    public function primaryDialog(string $lsxXml, string $englishXml): array
    {
        [$rootName, $lsxArr] = $this->loadXmlAsArr($lsxXml);
        $handles             = $this->buildHandleMapFromEnglishStrings([$englishXml], true);
        return $this->xmlHelperTreeToPrimary($rootName, $lsxArr, $handles);
    }

    /** Both ARRAYS (primary + secondary) from a single english.xml string. */
    public function bothDialog(string $lsxXml, string $englishXml): array
    {
        [$rootName, $lsxArr] = $this->loadXmlAsArr($lsxXml);
        $handles             = $this->buildHandleMapFromEnglishStrings([$englishXml], true);

        return [
            'primary'   => $this->xmlHelperTreeToPrimary($rootName, $lsxArr, $handles),
            'secondary' => $this->secondaryFromXmlHelperTree($lsxArr),
        ];
    }

    /** Secondary ARRAY ({roots, graph}) from LSX XML string (no localization needed). */
    public function secondaryDialog(string $lsxXml): array
    {
        [, $lsxArr] = $this->loadXmlAsArr($lsxXml);
        return $this->secondaryFromXmlHelperTree($lsxArr);
    }

    /* ===================== Builders (ARRAY → XML) ===================== */

    /** ARRAY (primary shape) → LSX XML. */
    public function buildLsxFromPrimaryArray(array $primary): string
    {
        if (!isset($primary['name']) || !\is_string($primary['name']) || $primary['name'] === '') {
            throw new \InvalidArgumentException("Primary array root missing valid 'name'.");
        }
        $rootName      = $primary['name'];
        $xmlHelperData = $this->primaryToXmlHelperTree($primary); // includes '@attributes' and child tags

        // XmlHelper::createXML expects the root tag name and the subtree data beneath it
        $dom = XmlHelper::createXML($rootName, $xmlHelperData);
        // Optional minify of empty tags for consistency with your writer
        $xml = XmlHelper::minifyEmptyTags($dom->saveXML());
        return $xml;
    }

    /** JSON (primary) → LSX XML (convenience). */
    public function buildLsxFromPrimaryDialog(string $primaryDialog): string
    {
        $arr = json_decode($primaryDialog, true);
        if ($arr === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException("Invalid JSON: " . json_last_error_msg());
        }
        return $this->buildLsxFromPrimaryArray($arr);
    }

    /* ===================== Internal: XML via XmlHelper ===================== */

    /** Load XML text using XmlHelper; return [rootName, rootSubtreeArray] */
    private function loadXmlAsArr(string $xmlText): array
    {
        $xmlText = trim($xmlText);
        if ($xmlText === '') {
            throw new \InvalidArgumentException('Empty XML string.');
        }
        $docArr = XmlHelper::createArray($xmlText); // [rootName => subtree]
        $rootName = array_key_first($docArr);
        if ($rootName === null) {
            throw new \RuntimeException('XmlHelper parser returned empty document.');
        }
        return [$rootName, $docArr[$rootName]];
    }

    /** Merge MANY english.xml-like strings → [handle => text]. */
    private function buildHandleMapFromEnglishStrings(array $englishXmlStrings, bool $laterWins = true): array
    {
        $map = [];
        foreach ($englishXmlStrings as $xml) {
            if (!\is_string($xml) || trim($xml) === '') {
                continue;
            }
            // Parse with XmlHelper and extract <content contentuid="...">@value
            [$rootName, $subtree] = $this->loadXmlAsArr($xml);
            // Typically 'contentList' root; robustly scan for 'content'
            $contents = $this->collectAllByTag($subtree, 'content');
            foreach ($contents as $c) {
                $uid  = $c['@attributes']['contentuid'] ?? '';
                $text = isset($c['@value']) ? trim((string)$c['@value']) : '';
                if ($uid === '') {
                    continue;
                }
                if ($laterWins || !array_key_exists($uid, $map)) {
                    $map[$uid] = $text;
                }
            }
        }
        return $map;
    }

    /* ===================== Internal: Convert XmlHelper array → Primary ===================== */

    /**
     * Convert XmlHelper element subtree to the “primary” shape.
     * $nodeName is the tag name, $nodeArr is XmlHelper’s element array.
     */
    private function xmlHelperTreeToPrimary(string $nodeName, array $nodeArr, array $handleMap): array
    {
        $out = [
            'name'     => $nodeName,
            'attrs'    => [],
            'children' => [],
        ];

        // Attributes
        if (isset($nodeArr['@attributes']) && \is_array($nodeArr['@attributes'])) {
            $out['attrs'] = $nodeArr['@attributes'];
        }

        // If this is <attribute type="TranslatedString" handle="...">, attach resolved text
        if (
            \strcasecmp($nodeName, 'attribute') === 0 &&
            isset($out['attrs']['type']) &&
            \strcasecmp($out['attrs']['type'], 'TranslatedString') === 0 &&
            isset($out['attrs']['handle'])
        ) {
            $h = (string)$out['attrs']['handle'];
            $out['attrs']['text'] = array_key_exists($h, $handleMap) ? $handleMap[$h] : null;
        }

        // XmlHelper groups children under tag keys; restore to children[] list
        foreach ($nodeArr as $key => $val) {
            if ($key === '@attributes' || $key === '@value') {
                continue;
            }

            if (\is_array($val) && $this->isList($val)) {
                // multiple children with same tag
                foreach ($val as $childSub) {
                    if (!\is_array($childSub)) continue;
                    $out['children'][] = $this->xmlHelperTreeToPrimary($key, $childSub, $handleMap);
                }
            } elseif (\is_array($val)) {
                // single child with tag = $key
                $out['children'][] = $this->xmlHelperTreeToPrimary($key, $val, $handleMap);
            } else {
                // Scalar child content under a named element (rare in LSX) – represent as element with value
                $out['children'][] = [
                    'name'     => $key,
                    'attrs'    => [],
                    'children' => [],
                ];
            }
        }

        return $out;
    }

    /* ===================== Internal: Secondary (roots + graph) ===================== */

    /** Build secondary {roots, graph} from the XmlHelper LSX subtree. */
    private function secondaryFromXmlHelperTree(array $lsxRoot): array
    {
        return [
            'roots' => $this->collectRootUuids($lsxRoot),
            'graph' => $this->buildGraphNodesOnly($lsxRoot),
        ];
    }

    /** Collect RootNodes UUIDs from:
     *   - <attribute id="RootNodes" value="..."/>
     *   - <node id="RootNodes">...<attribute id="UUID" value="..."/></node>
     */
    private function collectRootUuids(array $tree): array
    {
        $roots = [];

        // 1) attribute form
        foreach ($this->collectAllByTag($tree, 'attribute') as $attr) {
            $a = $attr['@attributes'] ?? [];
            if (($a['id'] ?? null) === 'RootNodes' && !empty($a['value'])) {
                $roots[] = (string)$a['value'];
            }
        }

        // 2) node form: <node id="RootNodes"> ... UUID attributes inside
        foreach ($this->collectAllByTag($tree, 'node') as $node) {
            $na = $node['@attributes'] ?? [];
            if (($na['id'] ?? null) !== 'RootNodes') {
                continue;
            }
            foreach ($this->collectAllByTag($node, 'attribute') as $attr) {
                $a = $attr['@attributes'] ?? [];
                if (($a['id'] ?? null) === 'UUID' && !empty($a['value'])) {
                    $roots[] = (string)$a['value'];
                }
            }
        }

        // unique, keep order
        $seen = [];
        $out  = [];
        foreach ($roots as $r) {
            if ($r === '' || isset($seen[$r])) continue;
            $seen[$r] = true;
            $out[] = $r;
        }
        return $out;
    }

    /**
     * Graph: ONLY <node id="node"> entries.
     *  key = node's own UUID (from immediate child attribute id="UUID")
     *  val = UUIDs found under any <node id="children"> ... <node id="child"> ... <attribute id="UUID" value="..."/>
     */
    private function buildGraphNodesOnly(array $tree): array
    {
        $graph = [];

        foreach ($this->collectAllByTag($tree, 'node') as $entry) {
            $attrs = $entry['@attributes'] ?? [];
            if (($attrs['id'] ?? null) !== 'node') continue;

            // Self UUID from immediate <attribute id="UUID" .../> children of this node
            $selfUuid = $this->firstImmediateAttributeValue($entry, 'UUID');
            if ($selfUuid === null || $selfUuid === '') {
                continue;
            }

            // Find <node id="children"> descendants directly under this node
            $childUuids = [];
            $childContainers = $this->immediateChildrenByTagId($entry, 'node', 'children');
            foreach ($childContainers as $container) {
                // Inside container, look for <node id="child"> ... then its immediate attribute UUID
                $childNodes = $this->immediateChildrenByTagId($container, 'node', 'child');
                foreach ($childNodes as $childNode) {
                    $uuid = $this->firstImmediateAttributeValue($childNode, 'UUID');
                    if ($uuid !== null && $uuid !== '' && strcasecmp($uuid, $selfUuid) !== 0) {
                        $childUuids[] = $uuid;
                    }
                }
            }

            // De-dupe, order-preserving
            $seen = [];
            $list = [];
            foreach ($childUuids as $u) {
                if (isset($seen[$u])) continue;
                $seen[$u] = true;
                $list[] = $u;
            }
            $graph[$selfUuid] = $list;
        }

        return $graph;
    }

    /* ===================== Internal: Primary → XmlHelper array ===================== */

    /** Convert primary node → XmlHelper subtree (array with '@attributes' and tag-keyed children). */
    private function primaryToXmlHelperTree(array $node): array
    {
        $name     = $node['name']     ?? '';
        $attrs    = $node['attrs']    ?? [];
        $children = $node['children'] ?? [];

        // sanitize: drop 'text' on TranslatedString attributes (not part of LSX)
        if (
            \is_string($name) && \strcasecmp($name, 'attribute') === 0 &&
            isset($attrs['type']) && \strcasecmp((string)$attrs['type'], 'TranslatedString') === 0 &&
            array_key_exists('text', $attrs)
        ) {
            unset($attrs['text']);
        }

        $out = [];
        if (!empty($attrs)) {
            $out['@attributes'] = $attrs;
        }

        if (\is_array($children)) {
            // group by tag name to fit XmlHelper’s structure
            $grouped = [];
            foreach ($children as $child) {
                if (!\is_array($child) || !isset($child['name'])) continue;
                $tag = (string)$child['name'];
                $grouped[$tag] = $grouped[$tag] ?? [];
                $grouped[$tag][] = $this->primaryToXmlHelperTree($child);
            }
            // collapse singletons
            foreach ($grouped as $tag => $list) {
                $out[$tag] = (count($list) === 1) ? $list[0] : $list;
            }
        }

        return $out;
    }

    /* ===================== Internal: XmlHelper traversal utils ===================== */

    /** Return true if the array is a numerically-indexed list. */
    private function isList(array $arr): bool
    {
        return array_keys($arr) === range(0, count($arr) - 1);
    }

    /** Collect all elements with a given tag from a subtree produced by XmlHelper. */
    private function collectAllByTag(array $subtree, string $tag): array
    {
        $found = [];

        $stack = [$subtree];
        while ($stack) {
            $cur = array_pop($stack);
            foreach ($cur as $k => $v) {
                if ($k === '@attributes' || $k === '@value') continue;

                if ($k === $tag) {
                    if (\is_array($v) && $this->isList($v)) {
                        foreach ($v as $one) if (\is_array($one)) $found[] = $one;
                    } elseif (\is_array($v)) {
                        $found[] = $v;
                    }
                }

                if (\is_array($v)) {
                    if ($this->isList($v)) {
                        foreach ($v as $one) if (\is_array($one)) $stack[] = $one;
                    } else {
                        $stack[] = $v;
                    }
                }
            }
        }

        return $found;
    }

    /** Get immediate child <attribute id="$id" value="..."> of a given XmlHelper node array. */
    private function firstImmediateAttributeValue(array $node, string $id): ?string
    {
        if (!isset($node['attribute'])) return null;
        $attrsList = $node['attribute'];
        $list = $this->isList($attrsList) ? $attrsList : [$attrsList];
        foreach ($list as $attr) {
            $a = $attr['@attributes'] ?? [];
            if (($a['id'] ?? null) === $id && isset($a['value'])) {
                return (string)$a['value'];
            }
        }
        return null;
    }

    /** Immediate children by tag+id: e.g., tag='node', id='children' returns list of those subtrees. */
    private function immediateChildrenByTagId(array $node, string $tag, string $id): array
    {
        $out = [];
        if (!isset($node[$tag])) return $out;

        $list = $node[$tag];
        $list = $this->isList($list) ? $list : [$list];
        foreach ($list as $child) {
            $a = $child['@attributes'] ?? [];
            if (($a['id'] ?? null) === $id) {
                $out[] = $child;
            }
        }
        return $out;
    }
}

