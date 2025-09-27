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

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use App\Libraries\EnvWriter;
use Config\BG3LinuxHelper as Bg3Cfg;
use Config\Mongo as MongoCfg;

class Settings extends BaseController
{
    public function index(): ResponseInterface
    {
        $bg3   = config(Bg3Cfg::class) ?? new Bg3Cfg();
        $mongo = config(MongoCfg::class) ?? new MongoCfg();

        return $this->response->setBody(view('settings/index', [
            'pageTitle' => 'Settings',
            'activeTab' => 'settings',    // useful if your nav highlights the active tab
            'bg3'       => $bg3,
            'mongo'     => $mongo,
        ]));
    }

    public function save(): ResponseInterface
    {
        $pairs = [
            'bg3LinuxHelper.MyMods'   => (string)$this->request->getPost('bg3LinuxHelper_MyMods'),
            'bg3LinuxHelper.AllMods'  => (string)$this->request->getPost('bg3LinuxHelper_AllMods'),
            'bg3LinuxHelper.GameData' => (string)$this->request->getPost('bg3LinuxHelper_GameData'),
            'mongo.default.uri'       => (string)$this->request->getPost('mongo_default_uri'),
            'mongo.default.db'        => (string)$this->request->getPost('mongo_default_db'),
        ];

        (new EnvWriter())->setMany($pairs);

        return $this->response->setJSON(['ok' => true, 'saved' => $pairs]);
    }
}
?>
