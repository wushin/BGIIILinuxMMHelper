<?php

namespace App\Libraries;
use Config\LsxRegions;


/**
 * LsxHelper — class-based utility for LSX region & grouping detection.
 *
 * Usage:
 *   use App\Libraries\LsxHelper;
 *   $region = LsxHelper::detectRegionFromHead($xml);
 *   $group  = LsxHelper::regionGroupFromId($region);
 */
class LsxHelper
{
    /**
     * Peek the first N bytes of an LSX/XML string and extract <region id="…"> or <region name="…">.
     * Accepts single or double quotes, any attribute order, and mixed case.
     */
    public static function detectRegionFromHead(string $xml, int $bytes = 16384): ?string
    {
        if ($xml === '') return null;
        $head = substr($xml, 0, max(0, $bytes));
        if ($head === '') return null;

        // id="..." (or id='...')
        if (preg_match('/<\s*region\b[^>]*\bid\s*=\s*("|\')([^"\']+)\1/i', $head, $m)) {
            return $m[2];
        }
        // name="..." fallback
        if (preg_match('/<\s*region\b[^>]*\bname\s*=\s*("|\')([^"\']+)\1/i', $head, $m)) {
            return $m[2];
        }
        return null;
    }

    /**
     * Map a region id/name to a coarse group: dialog|gameplay|assets|meta|unknown.
     * Includes explicit map + light heuristics (case-insensitive).
     */
    function regionGroupFromId(?string $region): string
    {
        if (!$region) {
            return 'unknown';
        }
        $r = trim($region);
        if ($r === '') {
            return 'unknown';
        }

        // Single source of truth: Config\LsxRegions
        $cfg   = config(LsxRegions::class);
        $group = $cfg->groupFor($r);

        if ($group !== 'unknown') {
            return $group;
        }

        // Heuristics fallback for unmapped regions (keep your current patterns)
        if (preg_match('/dialog|timeline|scene/i', $r))  return 'dialog';
        if (preg_match('/texture|material|visual|icon|mesh|skeleton|lighting|anim/i', $r)) return 'assets';
        if (preg_match('/progress|spell|feat|ruleset|equip|status|class|skill|quest|tag|template|reward/i', $r)) return 'gameplay';
        if (preg_match('/meta|config|depend/i', $r))     return 'meta';

        return 'unknown';
    }

}
?>
