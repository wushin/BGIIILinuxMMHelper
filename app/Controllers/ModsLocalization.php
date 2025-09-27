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

use App\Libraries\LocalizationScanner;

class ModsLocalization extends BaseController
{
    // GET /mods/localizations/manifest
    public function manifest()
    {
        $restrict = $this->request->getGet('restrict') !== '0';
        $validate = $this->request->getGet('validate') === '1';
        $root     = $this->request->getGet('root') ?: null;

        $scanner = new LocalizationScanner($restrict, $validate);
        $data    = $scanner->scanMyModsManifest($root);
        return $this->response->setJSON($data);
    }

    // POST /mods/localizations/parse
    // body: mod (string), lsx (string), [paths[]=... | languages[]=... | all=1], [restrict, validate]
    public function parse()
    {
        $restrict = $this->request->getPost('restrict') !== '0';
        $validate = $this->request->getPost('validate') === '1';
        $root     = $this->request->getPost('root') ?: null;

        $mod      = (string)$this->request->getPost('mod');
        $lsx      = (string)$this->request->getPost('lsx'); // LSX XML content
        $paths    = $this->request->getPost('paths');       // array or null
        $langs    = $this->request->getPost('languages');   // array or null
        $all      = $this->request->getPost('all') ? true : false;

        $scanner  = new LocalizationScanner($restrict, $validate);
        $manifest = $scanner->scanMyModsManifest($root);

        $json = $scanner->parseLsxForModToJson(
            $manifest,
            $mod,
            $lsx,
            $paths ?: null,
            is_array($langs) ? $langs : [],
            $all
        );

        // return parsed primary JSON (as object)
        return $this->response->setJSON(json_decode($json, true));
    }
}

?>
