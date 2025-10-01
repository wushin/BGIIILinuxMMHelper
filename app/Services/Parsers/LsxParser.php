<?php
namespace App\Services\Parsers;

use App\Services\LsxService;

final class LsxParser implements ParserInterface
{
    public function __construct(private LsxService $lsx) {}

    public function read(string $bytes, string $absPath): array
    {
        $parsed = $this->lsx->parse($bytes, $absPath);
        return [
            'kind' => 'lsx',
            'json' => $parsed['payload'] ?? [],   // <- was ['json']
            'meta' => $parsed['meta']    ?? [],
        ];
    }

    public function write(?string $raw, ?string $json, string $abs): string
    {
        $cfg = function_exists('config') ? config('Lsx') : null;
        $pretty     = $cfg?->prettyXml ?? true;
        $normalize  = $cfg?->normalizeLF ?? true;
        $emitDecl   = $cfg?->emitDecl ?? true;

        // We REQUIRE the normalized JSON tree to build canonical LSX XML.
        if ($json === null) {
            throw new \RuntimeException('LSX write: JSON is required to emit XML (raw edits are not supported).');
        }

        $tree = is_string($json) ? json_decode($json, true) : $json;
        if (!is_array($tree)) {
            throw new \RuntimeException('LSX write: invalid JSON payload (expected object/array).');
        }

        $xml = $this->jsonTreeToXml($tree, $pretty, $emitDecl);

        return $normalize ? str_replace(["\r\n", "\r"], "\n", $xml) : $xml;
    }

    /**
     * Build an XML document from our normalized LSX JSON tree.
     * Accepts either a single node (assoc array) or a list of nodes.
     */
    private function jsonTreeToXml(array $nodeOrList, bool $pretty, bool $emitDecl): string
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = $pretty;

        // If the normalizer returns a list of top-level nodes, wrap them in a neutral root.
        // Prefer NOT to do this if your normalizer already includes the true LSX root.
        $isList = array_is_list($nodeOrList);

        if ($isList) {
            // If your LSX always has a known root (e.g., "save"), you can create that explicitly here.
            $root = $dom->createElement('root'); // change to the canonical LSX root if you have one
            foreach ($nodeOrList as $child) {
                if (is_array($child)) {
                    $root->appendChild($this->buildElement($dom, $child));
                }
            }
            $dom->appendChild($root);
        } else {
            $dom->appendChild($this->buildElement($dom, $nodeOrList));
        }

        // Save with or without XML declaration
        $xml = $emitDecl ? $dom->saveXML() : $dom->saveXML($dom->documentElement);
        return $xml !== false ? $xml : '';
    }

    /** Build a DOMElement from one normalized node: { tag, attr, text, children[] } */
    private function buildElement(\DOMDocument $dom, array $n): \DOMElement
    {
        $tag = isset($n['tag']) && $n['tag'] !== '' ? (string)$n['tag'] : 'Node';
        $el  = $dom->createElement($tag);

        if (!empty($n['attr']) && is_array($n['attr'])) {
            foreach ($n['attr'] as $k => $v) {
                $el->setAttribute((string)$k, (string)$v);
            }
        }

        // Text node (only if explicitly provided and non-empty)
        if (array_key_exists('text', $n) && $n['text'] !== null && $n['text'] !== '') {
            $el->appendChild($dom->createTextNode((string)$n['text']));
        }

        if (!empty($n['children']) && is_array($n['children'])) {
            foreach ($n['children'] as $child) {
                if (is_array($child)) {
                    $el->appendChild($this->buildElement($dom, $child));
                }
            }
        }

        return $el;
    }


}
?>
