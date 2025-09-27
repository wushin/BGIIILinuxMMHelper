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
use App\Helpers\FormatHelper;
use App\Helpers\FilePathHelper;

class Mods extends BaseController
{
    public function index(?string $path, ?string $slug = null, ?string $file = null)
    {
        $data['title'] = $slug;
        $data['bginfo']['path'] = FilePathHelper::getUrlPath($path, $slug);
        // Show directories & files; tree rendering is unchanged
        $data['Mods'] = FormatHelper::directoryTreeToHtml($path, FilePathHelper::getUrlPath($path, $slug), true);

        return view('templates/header', $data)
            . view('mods/show')
            . view('templates/footer');
    }

    public function display($path, $slug, $file)
    {
        $absModPath = FilePathHelper::getAbsPath($path, $slug);
        $bgdata     = new BG3readwriteparse($absModPath);

        if (empty($file) || str_ends_with($file, 'loca') || str_ends_with($file, 'lsf')) {
            return "<div id='fileName' class='flash-red'>".$file." is unsupported or empty file type</div>";
        }

        $filename = FilePathHelper::getAbsFileName($path, $slug, $file);
        $ext3     = strtolower(substr($file, -3));

        // Images / txt / khn / lsx handled as before
        if ($ext3 !== 'xml') {
            return FormatHelper::formatForm($path, $slug, $filename)
                 . $bgdata->draw($filename)
                 . " </form>";
        }

        // XML: with new localization scanning, mods can have 1..N localization XMLs.
        // Only render as language form if this XML was detected/parsed as localization.
        $langMap = $bgdata->getLang();

        if (isset($langMap[$filename])) {
            return FormatHelper::formatForm($path, $slug, $filename)
                 . FormatHelper::langToForm($langMap[$filename])
                 . " </form>";
        }

        // Non-localization XMLs are not editable in the lang form; show a friendly notice.
        return "<div id='fileName' class='flash-red'>Not a localization/TranslatedString XML: "
             . htmlspecialchars(FilePathHelper::getFileName($path, $filename))
             . "</div>";
    }

    public function search($path, $slug, $term)
    {
        $bgdata = new BG3readwriteparse(FilePathHelper::getAbsPath($path, $slug));

        // Build set of files that matched
        $files = [];
        foreach ($bgdata->searchData($term) as $result) {
            // $result[0] is the file path per ArrayHelper::recursiveSearchKeyMap contract
            $files[$result[0]] = true;
        }

        $htmlout = "";
        foreach ($files as $filePath => $_) {
            $htmlout .= "<div id='fileName'>"
                     .  FilePathHelper::getFileName($path, $filePath)
                     .  " <button id=\"viewFile\" class=\"appSystem\" onclick=\"display('"
                     .  FilePathHelper::getFileUrlPath($path, $filePath)
                     .  "')\">Open</button></div>"
                     .  $bgdata->draw($filePath, $term);
        }

        if ($htmlout === "") {
            $htmlout = "<div id='fileName'>No Results Found!</div>";
        }

        return view('mods/search', ['results' => $htmlout]);
    }

    public function replace($path, $slug, $searchValue, $replaceValue)
    {
        $bgdata = new BG3readwriteparse(FilePathHelper::getAbsPath($path, $slug));
        $bgdata->findNReplace($searchValue, $replaceValue);
        return true;
    }

    public function save()
    {
        $file = $this->request->getPost('fileName');
        $path = $this->request->getPost('path');
        $slug = $this->request->getPost('slug');
        $data = $this->request->getPost('data');

        $bgdata = new BG3readwriteparse(FilePathHelper::getAbsPath($path, $slug));
        $bgdata->saveFile($file, $data);
    }
}

