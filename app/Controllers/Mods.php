<?php

namespace App\Controllers;

use App\Models\BG3readwriteparse;
use CodeIgniter\Files\File;
use CodeIgniter\Files\FileCollection;
helper('BG3');

class Mods extends BaseController
{
    public function index(?string $path, ?string $slug = null, ?string $file = null)
    {
        $data['title'] = $slug;
        $data['bginfo']['path'] = getUrlPath($path,$slug);
        $modPathsArray = $this->dirToArray($path, getUrlPath($path,$slug));
        $data['Mods'] = $this->olLiTree($modPathsArray, getUrlPath($path, $slug), True);
        return view('templates/header', $data)
            . view('mods/show')
            . view('templates/footer');
    }

    public function display($path, $slug, $file)
    {
        $bgdata = new BG3readwriteparse(getAbsPath($path,$slug));
        if (!$file == "" && substr($file, -4) != "loca" && substr($file, -3) != "lsf") {
            $filename = getAbsFileName($path,$slug,$file);
            if (substr($file, -3) != "xml") {
               return formatForm($path,$slug,$filename).$bgdata->draw($filename)." </form>";
            } else {
               return formatForm($path,$slug,$filename).$this->langToForm($bgdata->getLang()[$filename])." </form>";
            }
        }
    }

    public function search($path, $slug, $term)
    {
        $files = array();
        $bgdata = new BG3readwriteparse(getAbsPath($path,$slug));
        foreach ($bgdata->searchData($term) as $result) {
          $files[$result[0]] = "1";
        }
        $htmlout = "";
        foreach ($files as $key => $value) {
          $htmlout .= "<div id='fileName'>".getFileName($path,$key)." <button id=\"viewFile\" onclick=\"display('".getFileUrlPath($path,$key)."')\">Edit</button></div>".$bgdata->draw($key,$term);
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
        $bgdata = new BG3readwriteparse(getAbsPath($path,$slug));
        $bgdata->findNReplace($searchValue, $replaceValue);
        return true;
    }

    public function save()
    {
        $file = $this->request->getPost('fileName');
        $path = $this->request->getPost('path');
        $slug = $this->request->getPost('slug');
        $data = $this->request->getPost('data');
        $bgdata = new BG3readwriteparse(getAbsPath($path,$slug));
        $bgdata->saveFile($file, $data);
    }

    private function langToForm($lang)
    {
        if (is_array($lang)) {
          $html = "";
          foreach ($lang['contentList']['content'] as $key => $item) {
            $html .= '<div class="langline" id="'.$key.'">';
            $html .= '<input type="text" class="contentuid" name="data[content]['.$key.'][@attributes][contentuid]" value="'.$item['@attributes']['contentuid'].'">';
            $html .= '<input type="text" class="version" name="data[content]['.$key.'][@attributes][version]" value="'.$item['@attributes']['version'].'">';
            $html .= '<textarea name="data[content]['.$key.'][@value]">'.$item['@value'].'</textarea>';
            $html .= '<button class="rmDiv" onclick="removeDivById(\''.$key.'\')">X</button>';
            $html .= "</div>";
          }
          $html .= '<input type="hidden" name="nextKey" value="'.($key + 1).'" />';
          return $html;
       } else {
          return "Empty";
       }
    }

    private function olLiTree($tree, $path, $first=False)
    {
      if ($first) {
        $menu = '<ul>';
      } else {
        $menu = '<ul class="nested">';
      }
      foreach ($tree as $key => $item) {
          if (is_array($item)) {
              if (!is_numeric($key)) {
                $menu .= "<li><span class=\"caret\">$key</span>";
                $menu .= $this->olLiTree($item, $path);
                $menu .= "</li>";
              } else {
                foreach ($item as $Ikey => $Iitem) {
                  $menu .= "<li>$Iitem<button id=\"viewFile\" onclick=\"display('".getFileUrlPath($path,$Ikey.'/'.$Iitem)."')\">Edit</button></li>";
                }
              }
          } 
      }
      $menu .= '</ul>';
      return $menu;
    }

    private function dirToArray($path, $dir)
    {
        $contents = array();
        foreach (scandir($dir) as $node) {
            if ($node == '.' || $node == '..') continue;
            if (is_dir($dir.'/'.$node)) {
                $contents[$node] = $this->dirToArray($path, $dir.'/'.$node);
            } else {
                $contents[][preg_replace('/'.$path.'\/(.*?)\//','',$dir)] = $node;
            }
        }
        return $contents;
    }
}
