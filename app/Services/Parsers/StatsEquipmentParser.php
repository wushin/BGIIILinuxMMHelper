<?php
namespace App\Services\Parsers;

use App\Services\Parsers\ParserInterface;

final class StatsEquipmentParser implements ParserInterface
{
    public function read(string $bytes, string $absPath): array
    {
        $lines = preg_split('/\R/u', $bytes);
        $outLines = [];
        foreach ($lines as $i => $raw) $outLines[] = ['i' => $i, 'text' => rtrim($raw, "\r\n")];

        $name = null;
        $initialWeaponSet = null;
        $groups = [];
        $cur = null;

        foreach ($lines as $raw) {
            $line = trim($raw);
            if ($line === '' || $line[0] === '#') continue;

            if ($name === null && preg_match('~^new\s+equipment\s+"([^"]+)"~i', $line, $m)) {
                $name = $m[1];
                continue;
            }
            if ($initialWeaponSet === null && preg_match('~^add\s+initialweaponset\s+"([^"]+)"~i', $line, $m)) {
                $initialWeaponSet = $m[1];
                continue;
            }
            if (preg_match('~^add\s+equipmentgroup\b~i', $line)) {
                if ($cur) $groups[] = $cur;
                $cur = ['entries' => []];
                continue;
            }
            if (preg_match('~^add\s+equipment\s+entry\s+"([^"]+)"~i', $line, $m)) {
                if (!$cur) $cur = ['entries' => []];
                $cur['entries'][] = $m[1];
                continue;
            }
        }
        if ($cur) $groups[] = $cur;

        $json = [
            'equipment' => [
                'name' => $name,
                'initialWeaponSet' => $initialWeaponSet,
                'groups' => $groups,
            ],
            'source' => ['lines' => $outLines],
        ];

        // Optional: scan for handles in strings (rare here)
        $meta = ['subtype' => 'equipment'];
        return ['kind' => 'txt.stats', 'json' => $json, 'meta' => $meta];
    }

    public function write(?string $raw, ?string $json, string $absPath): string
    {
        return (string)($raw ?? ($json ?? ''));
    }
}
?>
