<?php
namespace App\Services\Parsers;

use App\Services\ParserInterface;

final class StatsTreasureParser implements ParserInterface
{
    public function read(string $bytes, string $absPath): array
    {
        $lines = preg_split('/\R/u', $bytes);
        $outLines = [];
        foreach ($lines as $i => $raw) $outLines[] = ['i' => $i, 'text' => rtrim($raw, "\r\n")];

        $itemtypes = [];
        $tables = [];
        $curTable = null;
        $curSub = null;

        foreach ($lines as $raw) {
            $line = trim($raw);
            if ($line === '' || $line[0] === '#') continue;

            if (!$itemtypes && preg_match('~^treasure\s+itemtypes\s+(.+)$~i', $line, $m)) {
                // expect quoted, comma-separated
                preg_match_all('~"([^"]+)"~', $m[1], $mm);
                $itemtypes = $mm[1] ?? [];
                continue;
            }

            if (preg_match('~^new\s+treasuretable\s+"([^"]+)"~i', $line, $m)) {
                if ($curTable) { if ($curSub) { $curTable['subtables'][] = $curSub; $curSub = null; }
                    $tables.append($curTable); }
                $curTable = ['name' => $m[1], 'subtables' => []];
                $curSub = null;
                continue;
            }

            if (preg_match('~^new\s+subtable\s+"([^"]+)"~i', $line, $m)) {
                if ($curSub) $curTable['subtables'][] = $curSub;
                $curSub = ['range' => $m[1], 'objects' => []];
                continue;
            }

            if (preg_match('~^object\s+category\s+"([^"]+)"\s*,\s*([0-9,\s]+)\s*$~i', $line, $m)) {
                $cat = $m[1];
                $nums = array_values(array_filter(array_map('trim', explode(',', $m[2])), 'strlen'));
                $ints = array_map('intval', $nums);
                $obj = ['category' => $cat, 'numbers' => $ints];
                if ($curSub) $curSub['objects'][] = $obj;
                continue;
            }
        }
        if ($curSub)   $curTable['subtables'][] = $curSub;
        if ($curTable) $tables[] = $curTable;

        $json = [
            'itemtypes' => $itemtypes,
            'tables'    => $tables,
            'source'    => ['lines' => $outLines],
        ];
        return ['kind' => 'txt.stats', 'json' => $json, 'meta' => ['subtype' => 'treasure']];
    }

    public function write(?string $raw, ?string $json, string $absPath): string
    {
        return (string)($raw ?? ($json ?? ''));
    }
}
?>
