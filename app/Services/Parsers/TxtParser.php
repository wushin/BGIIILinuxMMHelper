<?php
namespace App\Services\Parsers;
use App\Services\Parsers\ParserInterface;

final class TxtParser implements ParserInterface
{
    public function read(string $bytes, string $absPath): array
    {
        return ['kind' => 'text', 'raw' => $bytes, 'meta' => []];
    }

    public function write(?string $raw, ?string $json, string $absPath): string
    {
        return (string)($raw ?? '');
    }
}
?>
