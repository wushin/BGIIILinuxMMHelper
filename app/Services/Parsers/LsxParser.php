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

    public function write(array $json, string $absPath): string
    {
        // Expect canonical LSX XML write in other component; here we pass through
        if (isset($json['raw']) && is_string($json['raw'])) return $json['raw'];

        // Optionally rebuild DOM from normalized tree
        if (isset($json['payload']) && is_array($json['payload'])) {
            $dom = new \DOMDocument('1.0', 'UTF-8');
            $dom->formatOutput = true;
            $el = $this->buildElement($dom, $json['payload']);
            $dom->appendChild($el);
            return $dom->saveXML() ?: '';
        }
        return '';
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
