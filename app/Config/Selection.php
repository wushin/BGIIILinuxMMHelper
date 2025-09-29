<?php

namespace App\Config;

use CodeIgniter\Config\BaseConfig;

class Selection extends BaseConfig
{
    /**
     * Backend for persistence: session | cookie | db
     * Only "session" is implemented in this step.
     */
    public string $backend = 'session';

    /**
     * Key prefix used in storage.
     */
    public string $keyPrefix = 'bg3mmh.sel.';

    /**
     * Scope mode:
     *  - global   : one selection across everything
     *  - perRoot  : one selection per root (e.g., MyMods, UnpackedMods, GameData)
     *  - perMod   : one selection per root+slug
     */
    public string $scopeMode = 'perMod';

    /**
     * Optional whitelist of allowed roots. Leave empty to skip enforcement.
     * Example: ['MyMods', 'UnpackedMods', 'GameData']
     */
    public array $allowedRoots = ['MyMods', 'UnpackedMods', 'GameData'];

    /**
     * Whether to store derived fields alongside the selection.
     */
    public bool $includeKindInPayload = true;
    public bool $includeExtInPayload  = true;

    /**
     * Cookie/DB expiries would go here in future; session uses server defaults.
     */
    public int $expirySeconds = 0; // 0 = use backend default
}
?>
