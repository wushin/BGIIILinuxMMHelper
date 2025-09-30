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

    public function write(?string $raw, ?string $json, string $absPath): string
    {
        // Current policy: JSON passthrough if provided; else raw.
        if ($json !== null) {
            return $json; // already JSON string from the UI
        }
        return (string) ($raw ?? '');
    }

}
?>
