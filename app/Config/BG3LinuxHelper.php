<?php
namespace Config;

use CodeIgniter\Config\BaseConfig;

class BG3LinuxHelper extends BaseConfig
{
    /** Directory label or path for "My Mods" (string) */
    public string $MyMods   = 'MyMods';

    /** Directory label or path for "All Mods" (string) */
    public string $AllMods  = 'AllMods';

    /** Directory label or path for "Game Data" (string) */
    public string $GameData = 'GameData';

    public function __construct()
    {
        parent::__construct();
        // Map from .env (keys as requested)
        $this->MyMods   = env('bg3LinuxHelper.MyMods',   $this->MyMods);
        $this->AllMods  = env('bg3LinuxHelper.AllMods',  $this->AllMods);
        $this->GameData = env('bg3LinuxHelper.GameData', $this->GameData);
    }
}
?>
