<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Central configuration for BG3 path handling and file-type policy.
 * Reads from .env (with optional dev fallbacks) and exposes
 * final resolved properties: $GameData, $MyMods, $UnpackedMods.
 */
class BG3Paths extends BaseConfig
{
    // .env keys (override in .env)
    public string $envGameData     = 'bg3LinuxHelper.GameData';
    public string $envMyMods       = 'bg3LinuxHelper.MyMods';
    public string $envUnpackedMods = 'bg3LinuxHelper.UnpackedMods';

    // Optional dev fallbacks (keep null in prod)
    public ?string $defaultGameData     = null;
    public ?string $defaultMyMods       = null;
    public ?string $defaultUnpackedMods = null;

    // Final computed paths (nullable if not configured)
    public ?string $GameData     = null;
    public ?string $MyMods       = null;
    public ?string $UnpackedMods = null;

    // Policy: readable/editable extensions (lower-case)
    public array $allowedExtensions = ['xml', 'lsx', 'txt', 'khn', 'png', 'dds'];

    public function __construct()
    {
        parent::__construct();

        $this->GameData     = $this->normalize($this->envOrDefault($this->envGameData, $this->defaultGameData));
        $this->MyMods       = $this->normalize($this->envOrDefault($this->envMyMods, $this->defaultMyMods));
        $this->UnpackedMods = $this->normalize($this->envOrDefault($this->envUnpackedMods, $this->defaultUnpackedMods));
    }

    private function envOrDefault(string $key, ?string $fallback): ?string
    {
        $v = env($key, null);
        if (is_string($v) && $v !== '') return $v;
        return (is_string($fallback) && $fallback !== '') ? $fallback : null;
    }

    private function normalize(?string $p): ?string
    {
        if ($p === null || $p === '') return null;
        return rtrim($p, DIRECTORY_SEPARATOR);
    }
}
?>
