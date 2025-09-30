<?php
namespace App\Services\Parsers;

final class PassthroughParser implements ParserInterface
{
    public function read(string $bytes, string $absPath): array
    {
        return ['kind' => 'unknown', 'raw' => $bytes, 'meta' => []];
    }

    public function write(?string $raw, ?string $json, string $absPath): string
    {
        // For unknown/binary kinds you should not accept JSON; the ContentService enforces this.
        return (string)($raw ?? '');
    }
}
?>
