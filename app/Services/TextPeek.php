<?php
namespace App\Services;

/**
 * Classifies BG3 .txt files by peeking the first chunk of text.
 * Order matters; we test the most-specific grammars first.
 */
final class TextPeek
{
    public int $peekBytes = 65536; // ~64KB

    public function classify(string $absPath): string
    {
        $peek = @file_get_contents($absPath, false, null, 0, $this->peekBytes);
        if ($peek === false) return 'txt.unknown';

        // Normalize newlines to simplify multi-line regex
        $s = str_replace("\r\n", "\n", $peek);

        // 1) Goals (Story/RawFiles/Goals)
        if (
            preg_match('~^Version\s+\d+~mi', $s) &&
            preg_match('~^SubGoalCombiner\s+\w+~mi', $s) &&
            preg_match('~^INITSECTION~mi', $s)
        ) {
            return 'txt.goal';
        }

        // 2) Stats - Equipment
        if (
            preg_match('~^\s*new\s+equipment\s+"[^"]+"~mi', $s) ||
            preg_match('~^\s*add\s+initialweaponset\b~mi', $s) ||
            preg_match('~^\s*add\s+equipmentgroup\b~mi', $s) ||
            preg_match('~^\s*add\s+equipment\s+entry\s+"[^"]+"~mi', $s)
        ) {
            return 'txt.stats.equipment';
        }

        // 3) Stats - Treasure tables
        if (
            preg_match('~^\s*treasure\s+itemtypes\s+"[^"]+"~mi', $s) ||
            preg_match('~^\s*new\s+treasuretable\s+"[^"]+"~mi', $s) ||
            preg_match('~^\s*object\s+category\s+"[^"]+",\s*\d+~mi', $s)
        ) {
            return 'txt.stats.treasure';
        }

        // 4) Stats - Generic entries
        if (
            preg_match('~^\s*new\s+entry\s+"[^"]+"~mi', $s) &&
            preg_match('~^\s*type\s+"(Armor|Character|InterruptData|Object|PassiveData|SpellData|StatusData|Weapon)"~mi', $s)
        ) {
            return 'txt.stats.generic';
        }

        // 5) Fallback
        return 'txt.unknown';
    }
}
?>
