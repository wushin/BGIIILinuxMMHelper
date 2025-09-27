<?php
/**
 * This file is part of BGIII Mod Manager Linux Helper.
 *
 * Copyright (C) 2025 Wushin.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

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
