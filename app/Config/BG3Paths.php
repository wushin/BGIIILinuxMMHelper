<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Central configuration for BG3 path handling and file-type policy.
 * Values default from environment but allow sane fallbacks for dev.
 */
class BG3Paths extends BaseConfig
{
    /**
     * Environment variable names (override in .env):
     *   bg3LinuxHelper.GameData
     *   bg3LinuxHelper.MyMods
     *   bg3LinuxHelper.UnpackedMods
     */
    public string $envGameData     = 'bg3LinuxHelper.GameData';
    public string $envMyMods       = 'bg3LinuxHelper.MyMods';
    public string $envUnpackedMods = 'bg3LinuxHelper.UnpackedMods';

    /**
     * Optional hard-coded fallbacks when env is absent (keep null for prod).
     * Example dev paths (commented out) you could use on your machine:
     *
     * public ?string $defaultGameData     = '/data/BG3/GameData';
     * public ?string $defaultMyMods       = '/data/BG3/MyMods';
     * public ?string $defaultUnpackedMods = '/data/BG3/UnpackedMods';
     */
    public ?string $defaultGameData     = null;
    public ?string $defaultMyMods       = null;
    public ?string $defaultUnpackedMods = null;

    /**
     * File types we consider “readable/editable” in the app (lower-case).
     */
    public array $allowedExtensions = [
        'xml', 'lsx', 'txt', 'khn', 'png', 'dds',
    ];
}
?>
