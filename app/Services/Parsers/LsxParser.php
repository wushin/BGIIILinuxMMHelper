<?php
namespace App\Services\Parsers;

use App\Services\LsxService;

final class LsxParser implements ParserInterface
{
    public function __construct(private LsxService $lsx) {}

    public function read(string $bytes, string $absPath): array
    {
        // Let LsxService detect region/group and parse to normalized JSON
        $parsed = $this->lsx->parse($bytes, $absPath);
        // Expect $parsed = ['json' => ..., 'meta' => ['region'=>..., 'group'=>...]]
        return ['kind' => 'lsx', 'json' => $parsed['json'] ?? [], 'meta' => $parsed['meta'] ?? []];
    }

    public function write(?string $raw, ?string $json, string $absPath): string
    {
        // Keep your current LSX “JSON passthrough” policy (or call to build canonical LSX)
        return $this->lsx->serialize($raw, $json, $absPath);
    }
}
?>
