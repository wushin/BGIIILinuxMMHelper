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
use Config\BG3Paths as PathsCfg;
use Config\Mongo as MongoCfg;

class Settings extends BaseController
{
    public function index(): ResponseInterface
    {
        /** @var PathsCfg $pathsCfg */
        $pathsCfg = config(PathsCfg::class) ?? new PathsCfg();
        /** @var MongoCfg $mongoCfg */
        $mongoCfg = config(MongoCfg::class) ?? new MongoCfg();

        // Resolved roots from CI4 services (if configured)
        $paths = service('pathResolver');

        return $this->response->setBody(view('settings/index', [
            'pageTitle' => 'Settings',
            'activeTab' => 'settings',

            // Raw config objects for template access (env keys, etc.)
            'bg3'   => $pathsCfg,
            'mongo' => $mongoCfg,

            // Resolved, normalized paths (may be null if not set)
            'resolved' => [
                'GameData'     => $paths->gameData(),
                'MyMods'       => $paths->myMods(),
                'UnpackedMods' => $paths->unpackedMods(),
            ],
        ]));
    }

    public function save(): ResponseInterface
    {
        // Accept both legacy AllMods and new UnpackedMods
        $myMods        = $this->postString('bg3LinuxHelper_MyMods');
        $unpackedMods  = $this->postString('bg3LinuxHelper_UnpackedMods');
        $allModsLegacy = $this->postString('bg3LinuxHelper_AllMods'); // legacy alias for UnpackedMods
        if ($unpackedMods === '' && $allModsLegacy !== '') {
            $unpackedMods = $allModsLegacy;
        }

        $gameData = $this->postString('bg3LinuxHelper_GameData');
        $mongoUri = $this->postString('mongo_default_uri');
        $mongoDb  = $this->postString('mongo_default_db');

        // Persist to .env using the new canonical keys used by BG3Paths
        $pairs = [
            'bg3LinuxHelper.MyMods'       => $myMods,
            'bg3LinuxHelper.UnpackedMods' => $unpackedMods, // replaces legacy AllMods
            'bg3LinuxHelper.GameData'     => $gameData,
            'mongo.default.uri'           => $mongoUri,
            'mongo.default.db'            => $mongoDb,
        ];

        (new EnvWriter())->setMany($pairs);

        return $this->response->setJSON([
            'ok'    => true,
            'saved' => $pairs,
        ]);
    }

    // --------- helpers ---------

    private function postString(string $key): string
    {
        $v = (string)($this->request->getPost($key) ?? '');
        return trim($v);
    }
}

