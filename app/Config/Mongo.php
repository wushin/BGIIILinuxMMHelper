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

class Mongo extends BaseConfig
{
    /** Default connection info */
    public string $defaultUri = 'mongodb://bg3mmh-mongo:27017';
    public string $defaultDb  = 'bg3mmh';

    public function __construct()
    {
        parent::__construct();
        $this->defaultUri = env('mongo.default.uri', $this->defaultUri);
        $this->defaultDb  = env('mongo.default.db',  $this->defaultDb);
    }
}
?>
