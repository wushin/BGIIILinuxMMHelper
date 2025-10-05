<?php
declare(strict_types=1);
namespace App\Services\Parsers;

use App\Services\LsxService;

final class LsxParser implements ParserInterface
{
    public function __construct(private LsxService $lsx) {}

    public function read(string $bytes, string $absPath): array
    {
        // Use LsxService to read/summarize
        $tmp = $this->lsx->read($absPath);

        // Back-compat shape: expose also under "result" for clients that expect it
        return [
            'kind'   => $tmp['kind']   ?? 'lsx',
            'ext'    => $tmp['ext']    ?? 'lsx',
            'meta'   => $tmp['meta']   ?? [],
            'payload'=> $tmp['payload'] ?? null,
            'result' => $tmp
        ];
    }

    public function write(?string $raw, ?string $json, string $absPath): string
    {
        // 1) Prefer structured JSON if provided (matches controller posting data_json)
        if (is_string($json) && $json !== '') {
            $decoded = json_decode($json, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \InvalidArgumentException(
                    'Invalid JSON for LSX write: ' . json_last_error_msg()
                );
            }

            // Accept any of these shapes:
            // { "raw": "<xml...>" }  -> passthrough
            // { "payload": { tag, attr, children } } -> normalized tree
            // { "result":  { ... } }                  -> normalized tree (alt key)
            // { tag, attr, children }                 -> normalized tree directly
            if (isset($decoded['raw']) && is_string($decoded['raw'])) {
                return $decoded['raw'];
            }

            $tree = $decoded['payload'] ?? $decoded['result'] ?? null;
            if ($tree === null && isset($decoded['tag'])) {
                // allow the normalized tree at top-level
                $tree = $decoded;
            }

            if (is_array($tree)) {
                $dom = new \DOMDocument('1.0', 'UTF-8');
                $dom->formatOutput = true;
                $el = $this->buildElement($dom, $tree);
                $dom->appendChild($el);
                return $dom->saveXML() ?: '';
            }

            throw new \InvalidArgumentException(
                'Normalized LSX tree not found in JSON (expected "payload" or "result").'
            );
        }

        // 2) Fallback: if raw XML/LSX bytes were provided, pass them through
        if (is_string($raw) && $raw !== '') {
            return $raw;
        }

        throw new \InvalidArgumentException('Nothing to write: both $raw and $json are empty.');
    }

    private function buildElement(\DOMDocument $dom, array $n): \DOMElement
    {
        $tag = $n['tag'] ?? 'node';
        $el = $dom->createElement($tag);
        foreach (($n['attr'] ?? []) as $k => $v) {
            $el->setAttribute((string)$k, (string)$v);
        }
        foreach (($n['children'] ?? []) as $child) {
            if (isset($child['tag']) && $child['tag'] === '#text') {
                $el->appendChild($dom->createTextNode((string)($child['text'] ?? '')));
            } elseif (is_array($child)) {
                $el->appendChild($this->buildElement($dom, $child));
            }
        }
        return $el;
    }
}
