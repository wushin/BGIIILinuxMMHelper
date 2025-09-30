<?php
namespace App\Services;

/**
 * Canonical LSX (XML) → JSON normalizer.
 *
 * Shape:
 *  {
 *    "tag": "nodeName",
 *    "attr": { "k": "v", ... },        // only if attributes exist
 *    "text": "trimmed text",           // only if non-empty meaningful text
 *    "children": [ {...}, {...} ]      // only if children exist
 *  }
 *
 * Notes:
 *  - Preserves order of children.
 *  - Collapses whitespace-only text.
 *  - Safe limits (node/depth) built-in to avoid pathological files.
 *  - These limits can later move to Config\Lsx.
 */
final class LsxNormalizer
{
    private int $maxNodes = 200_000; // safety ceiling
    private int $maxDepth = 256;

    /** Parse XML bytes → canonical JSON tree. Throws on malformed XML or limit breach. */
    public function normalize(string $xml): array
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false; // coalesce text nodes
        $dom->formatOutput = false;

        if (!@$dom->loadXML($xml, LIBXML_NONET | LIBXML_NOENT)) {
            throw new \RuntimeException('Malformed LSX (XML) content');
        }

        $root = $dom->documentElement;
        if (!$root) {
            throw new \RuntimeException('Empty LSX document');
        }

        $nodeCount = 0;
        $tree = $this->walk($root, 1, $nodeCount);

        if ($nodeCount > $this->maxNodes) {
            throw new \RuntimeException("LSX too large (nodes={$nodeCount})");
        }
        return $tree;
    }

    private function walk(\DOMNode $n, int $depth, int &$nodeCount): array
    {
        if ($depth > $this->maxDepth) {
            throw new \RuntimeException("LSX too deep (depth={$depth})");
        }
        $nodeCount++;

        $out = [
            'tag' => $n->nodeName,
        ];

        // Attributes
        if ($n instanceof \DOMElement && $n->hasAttributes()) {
            $attrs = [];
            foreach ($n->attributes as $a) {
                $attrs[$a->nodeName] = $a->nodeValue;
            }
            if ($attrs) $out['attr'] = $attrs;
        }

        // Children and text
        $children = [];
        $textBuf  = [];

        foreach ($n->childNodes ?? [] as $child) {
            if ($child->nodeType === XML_TEXT_NODE || $child->nodeType === XML_CDATA_SECTION_NODE) {
                $txt = trim($child->nodeValue ?? '');
                if ($txt !== '') {
                    $textBuf[] = $txt;
                }
                continue;
            }
            if ($child->nodeType === XML_ELEMENT_NODE) {
                $children[] = $this->walk($child, $depth + 1, $nodeCount);
            }
        }

        if ($children)  $out['children'] = $children;
        if ($textBuf)   $out['text']     = implode(' ', $textBuf);

        return $out;
    }
}
?>
