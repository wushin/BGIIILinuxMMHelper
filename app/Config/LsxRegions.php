<?php
namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Region → group mapping for LSX files.
 * Groups: dialog | gameplay | assets | meta | unknown
 */
class LsxRegions extends BaseConfig
{
    public array $dlg_tags = [
            'ActiveRoll','Alias','Jump','Nested Dialog','PassiveRoll','Pop','RollResult',
            'TagAnswer','TagCinematic','TagGreeting','TagQuestion'
    ];

    public array $flagTypes = [ 
            'Global','Local','Object','User','Tag','Dialog','Script','Quest'
    ];

    /** @var array<string,string> */
    public array $groupByRegion = [
        // dialog-ish
        'dialog'         => 'dialog',
        'DialogBank'     => 'dialog',
        'TimelineBank'   => 'dialog',
        'TimelineContent'=> 'dialog',
        'TLScene'        => 'dialog',

        // gameplay/data
        'AbilityDistributionPresets'   => 'gameplay',
        'ActionResourceDefinitions'    => 'gameplay',
        'Backgrounds'                  => 'gameplay',
        'CharacterCreationAppearanceVisuals' => 'gameplay',
        'CharacterCreationPresets'     => 'gameplay',
        'ClassDescriptions'            => 'gameplay',
        'ConditionErrors'              => 'gameplay',
        'DefaultValues'                => 'gameplay',
        'Effect'                       => 'gameplay',
        'EffectBank'                   => 'gameplay',
        'EnterPhaseSoundEvents'        => 'gameplay',
        'EnterSoundEvents'             => 'gameplay',
        'EquipmentLists'               => 'gameplay',
        'ExitPhaseSoundEvents'         => 'gameplay',
        'ExitSoundEvents'              => 'gameplay',
        'FactionContainer'             => 'gameplay',
        'FactionManager'               => 'gameplay',
        'Flags'                        => 'gameplay',
        'LevelMapValues'               => 'gameplay',
        'MultiEffectInfos'             => 'gameplay',
        'Origins'                      => 'gameplay',
        'PassiveLists'                 => 'gameplay',
        'ProgressionDescriptions'      => 'gameplay',
        'Progressions'                 => 'gameplay',
        'Races'                        => 'gameplay',
        'SkillLists'                   => 'gameplay',
        'SpellLists'                   => 'gameplay',
        'Tags'                         => 'gameplay',
        'Templates'                    => 'gameplay',
        'TooltipExtraTexts'            => 'gameplay',

        // assets
        'TextureBank'                  => 'assets',
        'TextureAtlasInfo'             => 'assets',
        'MaterialBank'                 => 'assets',
        'CharacterVisualBank'          => 'assets',
        'VisualBank'                   => 'assets',
        'IconUVList'                   => 'assets',

        // meta
        'Config'       => 'meta',
        'Dependencies' => 'meta',
        'MetaData'     => 'meta',
    ];

    public function groupFor(string $region): string
    {
        if ($region === '') {
            return 'unknown';
        }
        // Exact match first
        if (isset($this->groupByRegion[$region])) {
            return $this->groupByRegion[$region];
        }
        // Case-insensitive fallback
        $lower = strtolower($region);
        foreach ($this->groupByRegion as $key => $val) {
            if (strtolower($key) === $lower) {
                return $val;
            }
        }
        return 'unknown';
    }

}
?>
