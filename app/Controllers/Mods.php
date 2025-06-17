<?php

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
