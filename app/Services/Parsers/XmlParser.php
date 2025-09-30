<?php
namespace App\Services\Parsers;

final class XmlParser implements ParserInterface
{
    public function read(string $bytes, string $absPath): array
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        @$dom->loadXML($bytes);
        $dom->formatOutput = true;
        $pretty = $dom->saveXML() ?: $bytes;

        return ['kind' => 'xml', 'raw' => $pretty, 'meta' => []];
    }

    public function write(?string $raw, ?string $json, string $absPath): string
    {
        $text = (string)($raw ?? '');
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        if (@$dom->loadXML($text)) {
            return (string)$dom->saveXML();
        }
        // If invalid XML, write as-is (or throw if you prefer strict)
        return $text;
    }
}
?>
