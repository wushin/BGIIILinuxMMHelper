<?php

namespace App\Libraries;

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
    public static function regionGroupFromId(?string $region): string
    {
        if (!$region) return 'unknown';
        $r = trim($region);
        if ($r === '') return 'unknown';

        static $map = null;
        if ($map === null) {
            // Canonical mapping (extend anytime). Keys are lowercased.
            $map = array_change_key_case([
                // dialog / narrative
                'dialog' => 'dialog',
                'dialogbank' => 'dialog',
                'timelinebank' => 'dialog',
                'timelinecontent' => 'dialog',
                'tlscene' => 'dialog',
                'tlscenebank' => 'dialog',
                'tlsystemconfig' => 'dialog',
                'sceneconfig' => 'dialog',
                'voices' => 'dialog',
                'voicebarkbank' => 'dialog',

                // assets / visuals
                'texturebank' => 'assets',
                'textureatlasinfo' => 'assets',
                'materialbank' => 'assets',
                'charactervisualbank' => 'assets',
                'visualbank' => 'assets',
                'iconuvlist' => 'assets',
                'lightingbank' => 'assets',
                'lightingdetails' => 'assets',
                'virtualtexturebank' => 'assets',
                'meshproxybank' => 'assets',
                'skeletonbank' => 'assets',
                'animationbank' => 'assets',
                'animationsetbank' => 'assets',
                'animationsetpriorities' => 'assets',
                'colourgradings' => 'assets',

                // gameplay / rules
                'abilitydistributionpresets' => 'gameplay',
                'actionresourcedefinitions'  => 'gameplay',
                'backgrounds'                => 'gameplay',
                'charactercreationappearancevisuals' => 'gameplay',
                'charactercreationpresets'   => 'gameplay',
                'classdescriptions'          => 'gameplay',
                'conditionerrors'            => 'gameplay',
                'defaultvalues'              => 'gameplay',
                'effect'                     => 'gameplay',
                'effectbank'                 => 'gameplay',
                'enterphasesoundevents'      => 'gameplay',
                'entersoundevents'           => 'gameplay',
                'equipmentlists'             => 'gameplay',
                'exitphasesoundevents'       => 'gameplay',
                'exitsoundevents'            => 'gameplay',
                'factioncontainer'           => 'gameplay',
                'factionmanager'             => 'gameplay',
                'flags'                      => 'gameplay',
                'levelmapvalues'             => 'gameplay',
                'multieffectinfos'           => 'gameplay',
                'origins'                    => 'gameplay',
                'passivelists'               => 'gameplay',
                'progressiondescriptions'    => 'gameplay',
                'progressions'               => 'gameplay',
                'races'                      => 'gameplay',
                'skilllists'                 => 'gameplay',
                'spelllists'                 => 'gameplay',
                'tags'                       => 'gameplay',
                'templates'                  => 'gameplay',
                'tooltipextratexts'          => 'gameplay',
                'quests'                     => 'gameplay',
                'questcategories'            => 'gameplay',
                'feats'                      => 'gameplay',
                'featdescriptions'           => 'gameplay',
                'rulesets'                   => 'gameplay',
                'rulesetmodifiers'           => 'gameplay',
                'rulesetmodifieroptions'     => 'gameplay',
                'rulesetselectionpresets'    => 'gameplay',
                'rulesetvalues'              => 'gameplay',
                'experiencerewards'          => 'gameplay',
                'longrestcosts'              => 'gameplay',
                'tadpolepowerstree'          => 'gameplay',
                'gods'                       => 'gameplay',

                // meta
                'config'        => 'meta',
                'dependencies'  => 'meta',
                'metadata'      => 'meta',
                'modulesettings'=> 'meta',
            ], CASE_LOWER);
        }

        $key = strtolower($r);
        if (isset($map[$key])) {
            return $map[$key];
        }

        // Heuristics (in case region not in the explicit map)
        if (preg_match('/dialog|timeline|scene/i', $r))  return 'dialog';
        if (preg_match('/texture|material|visual|icon|mesh|skeleton|lighting|anim/i', $r)) return 'assets';
        if (preg_match('/progress|spell|feat|ruleset|equip|status|passive|race|class|skill|quest|tag|template|reward/i', $r)) return 'gameplay';
        if (preg_match('/meta|config|depend/i', $r))     return 'meta';

        return 'unknown';
    }
}
?>
