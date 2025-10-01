<?php
namespace App\Services\Parsers;

use App\Services\ParserInterface;

final class GoalsParser implements ParserInterface
{
    public function read(string $bytes, string $absPath): array
    {
        $lines = preg_split('/\R/u', $bytes);
        $outLines = []; // preserve exact order
        foreach ($lines as $i => $raw) {
            $outLines[] = ['i' => $i, 'text' => rtrim($raw, "\r\n")];
        }

        $version = null;
        $combiner = null;
        $sections = [];
        $cur = null;

        foreach ($lines as $raw) {
            $line = trim($raw);
            if ($line === '' || $line[0] === '#') continue;

            if ($version === null && preg_match('~^Version\s+(\d+)~i', $line, $m)) {
                $version = (int)$m[1];
                continue;
            }
            if ($combiner === null && preg_match('~^SubGoalCombiner\s+(\w+)~i', $line, $m)) {
                $combiner = $m[1];
                continue;
            }

            // Section headers are UPPERCASE words ending with SECTION
            if (preg_match('~^([A-Z_]+SECTION)\b~', $line, $m)) {
                if ($cur) $sections[] = $cur;
                $cur = ['name' => $m[1], 'lines' => []];
                continue;
            }

            if ($cur) {
                $cur['lines'][] = $line;
            }
        }
        if ($cur) $sections[] = $cur;

        return [
            'kind' => 'txt.goal',
            'json' => [
                'header' => ['version' => $version, 'combiner' => $combiner],
                'sections' => $sections,
                'source' => ['lines' => $outLines], // preserves original order verbatim
            ],
            'meta' => ['subtype' => 'goal'],
        ];
    }

    public function write(?string $raw, ?string $json, string $absPath): string
    {
        // passthrough (we can add pretty-writer later)
        return (string)($raw ?? ($json ?? ''));
    }
}
?>
