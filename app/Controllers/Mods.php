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

use App\Models\BG3readwriteparse;
use CodeIgniter\Files\File;
use CodeIgniter\Files\FileCollection;
use App\Helpers\FormatHelper;
use App\Helpers\FilePathHelper;

class Mods extends BaseController
{
    public function index(?string $path, ?string $slug = null, ?string $file = null)
    {
        $data['title'] = $slug;
        $data['bginfo']['path'] = FilePathHelper::getUrlPath($path,$slug);
        $data['Mods'] = FormatHelper::directoryTreeToHtml($path, FilePathHelper::getUrlPath($path, $slug), true);
        return view('templates/header', $data)
            . view('mods/show')
            . view('templates/footer');
    }

    public function display($path, $slug, $file)
    {
        $bgdata = new BG3readwriteparse(FilePathHelper::getAbsPath($path,$slug));
        if (!empty($file) && !str_ends_with($file, 'loca') && !str_ends_with($file, 'lsf'))
        {
            $filename = FilePathHelper::getAbsFileName($path,$slug,$file);
            if (strtolower(substr($file, -3)) != "xml") {
               return FormatHelper::formatForm($path,$slug,$filename).$bgdata->draw($filename)." </form>";
            } else {
               return FormatHelper::formatForm($path,$slug,$filename).FormatHelper::langToForm($bgdata->getLang()[$filename])." </form>";
            }
        } else {
            return "<div id='fileName' class='flash-red'>".$file." is unsupported or empty file type</div>";
        }
    }

    public function search($path, $slug, $term)
    {
        $files = array();
        $bgdata = new BG3readwriteparse(FilePathHelper::getAbsPath($path,$slug));
        foreach ($bgdata->searchData($term) as $result) {
          $files[$result[0]] = true;
        }
        $htmlout = "";
        foreach ($files as $key => $value) {
          $htmlout .= "<div id='fileName'>".FilePathHelper::getFileName($path,$key)." <button id=\"viewFile\" onclick=\"display('".FilePathHelper::getFileUrlPath($path,$key)."')\">Open</button></div>".$bgdata->draw($key,$term);
        }
        if (strlen($htmlout) == 0) {
          $htmlout = "<div id='fileName'>No Results Found!</div>";
        }
        $data['results'] = $htmlout;
        return view('mods/search', $data);
    }

    public function replace($path, $slug, $searchValue, $replaceValue)
    {
        $files = array();
        $bgdata = new BG3readwriteparse(FilePathHelper::getAbsPath($path,$slug));
        $bgdata->findNReplace($searchValue, $replaceValue);
        return true;
    }

    public function save()
    {
        $file = $this->request->getPost('fileName');
        $path = $this->request->getPost('path');
        $slug = $this->request->getPost('slug');
        $data = $this->request->getPost('data');
        $bgdata = new BG3readwriteparse(FilePathHelper::getAbsPath($path,$slug));
        $bgdata->saveFile($file, $data);
    }
}
