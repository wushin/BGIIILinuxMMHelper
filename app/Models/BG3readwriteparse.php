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

namespace App\Models;

use CodeIgniter\Model;

use App\Helpers\XmlHelper;
use App\Helpers\ArrayHelper;
use App\Helpers\FormatHelper;
use App\Helpers\FilePathHelper;
use App\Helpers\TextParserHelper;

class BG3readwriteparse extends Model {

  protected $_Lang = array();

  protected $_Data = array();

  public function __construct($moddir) {
    $this->buildDataSet($moddir);
  }

  public function __call($method, $arguments) {
    $arguments = array_merge(array("stdObject" => $this), $arguments);
    if (isset($this->{$method}) && is_callable($this->{$method})) {
      return call_user_func_array($this->{$method}, $arguments);
    } else {
      throw new Exception("Fatal error: Call to undefined method stdObject::{$method}()");
    }
  }

  private function parseLang($langfile) {
    if (FilePathHelper::testFile($langfile)) {
      $xml = file_get_contents($langfile);
      $this->_Lang[$langfile] = XmlHelper::createArray($xml);
    } else {
      $this->_Lang[$langfile] = "Empty";
    }
  }

  private function parseLsx($filepath) {
    $xml = file_get_contents($filepath);
    $this->_Data[$filepath] = XmlHelper::createArray($xml);
  }

  private function parseImg($type, $filepath) {
    $this->_Data[$filepath]['type'] = strtolower($type);
  }

  private function parseKhn($filepath) {
    $this->_Data[$filepath][] = file_get_contents($filepath);
  }

  private function parseTxt($filepath) {
    $data = file($filepath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $this->_Data[$filepath] = TextParserHelper::txt2Array($data);
  }

  private function buildDataSet($moddir) {
    $directory = new \RecursiveDirectoryIterator($moddir,\RecursiveDirectoryIterator::FOLLOW_SYMLINKS); 
    $iterator = new \RecursiveIteratorIterator($directory);

    foreach ($iterator as $info) {
       
      if ($info->isFile()) {
        switch (strtolower($info->getExtension())) {
          case 'lsx':
            $this->parseLsx($info->getPathname());
            break;;
          case 'xml':
            if (strtolower($info->getBasename()) == "english.xml") {
              $this->parseLang($info->getPathname());
            }
            break;;
          case 'khn':
            $this->parseKhn($info->getPathname());
            break;;
          case 'txt':
            $this->parseTxt($info->getPathname());
            break;;
          case 'png':
          case 'dds':
            $this->parseImg($info->getExtension(), $info->getPathname());
            break;;
        }
      }
    }
  }

  public function getLang() {
    return $this->_Lang;
  }

  public function getData() {
    return $this->_Data;
  }

  public function setDataTxt($filepath, $data) {
    file_put_contents($filepath,preg_replace("/\r\n?/", "\n", $data));
    $this->_Data[$filepath][0] = file_get_contents($filepath);
  }

  public function setDataBySearch($newvalue,$locations) {
    foreach ($locations as $path) {
      ArrayHelper::setNestedValue($this->_Data, $path, $newvalue);
      $this->saveFile($path[0]);
    }
  }

  public function setLangBySearch($newvalue,$locations) {
    foreach ($locations as $path) {
      ArrayHelper::setNestedValue($this->_Lang, $path, $newvalue);
      $this->saveFile($path[0]);
    }
  }

  public function searchData($key) {
    return ArrayHelper::recursiveSearchKeyMap($key, $this->_Data);
  }

  public function searchLang($key) {
    return ArrayHelper::recursiveSearchKeyMap($key, $this->_Lang);
  }

  public function findNReplace($oldvalue,$newvalue) {
    $searchResults = $this->searchData($oldvalue);
    if (!empty($searchResults) && end($searchResults[0]) === "handle" && strpos($newvalue, ";") !== false) {
      $searchDup[] = $searchResults[0];
      $searchDup[0][count($searchResults[0]) - 1] = 'version';
      $handleInfo = explode(";",$newvalue);
      $this->setDataBySearch($handleInfo[0], $searchResults);
      $this->setDataBySearch($handleInfo[1], $searchDup);
    } else {
      $this->setDataBySearch($newvalue, $this->searchData($oldvalue));
    }
    $langResults = $this->searchLang($oldvalue);
    if (!empty($langResults) && end($langResults[0]) === "contentuid" && strpos($newvalue, ";") !== false) {
      $versionDup[] = $langResults[0];
      $versionDup[0][count($langResults[0]) - 1] = 'version';
      $contentuidInfo = explode(";",$newvalue);
      $this->setLangBySearch($contentuidInfo[0], $langResults);
      $this->setLangBySearch($contentuidInfo[1], $versionDup);
    } else {
      $this->setLangBySearch($newvalue, $this->searchLang($oldvalue));
    }
  }

  public function draw($file, $term=null) {
    switch (substr($file, -3)) {
      case "xml":
        return $this->writeLang($file);
        break;;
      case "lsx":
        return FormatHelper::wrapEditableContent($this->writeXml($file), $term);
        break;;
      case "txt":
        return FormatHelper::wrapEditableContent($this->writeTxt($file), $term);
        break;;
      case "khn":
        return FormatHelper::wrapEditableContent($this->writeKhn($file), $term);
        break;;
      case "png":
       case "DDS":
       case "dds":
       return $this->displayImg($file);
       break;;
     default:
       return "<div class='display flash-red'>Unsupported file type: " . htmlspecialchars($file) . "</div>";
    }
  }

  public function saveFile($file, $data=false) {
    if (is_null($data)){
      $data = $this->_Data[$file];
    } elseif (!is_array($data)) {
      $data = html_entity_decode($data, ENT_QUOTES | ENT_XML1);
    }
    switch (strtolower(substr($file, -3))) {
      case "xml":
        $this->writeLang($file, $data, true);
        break;;
      case "lsx":
        $this->writeXml($file, $data, true);
        break;;
      case "txt":
        $this->writeTxtRaw($file, $data, true);
        break;;
      case "khn":
        $this->setDataTxt($file, $data);
        break;;
      case "png":
      case "dds":
        return $this->displayImg($file);
        break;;
    }
  }

  public function writeKhn($filepath, $save=false) {
    $lines = "";
    foreach($this->_Data[$filepath] as $txt) {
      $lines .= $txt;
    }
    if ($save) {
      file_put_contents($filepath,preg_replace("/\r\n?/", "\n", $lines));
    }
    return $lines;
  }

  public function displayImg($filepath, $save=false) {
    $img = new \Imagick($filepath);
    $img->setImageFormat('png');
    $imageDataUri = 'data:' . $img->getFormat() . ';base64,' . base64_encode($img->getimageblob());
    return '<img class="dynImg" src="' . $imageDataUri . '" alt="Dynamic Image">';
  }

  public function writeXml($filepath, $data=false, $save=false) {
    if($data) {
      $xml_string = XmlHelper::createXML('save',XmlHelper::createArray($data)['save'])->saveXML();
    } else {
      $xml_string = XmlHelper::createXML('save',$this->_Data[$filepath]['save'])->saveXML();
    }
    $dom = XmlHelper::createFormattedDOM(XmlHelper::minifyEmptyTags($xml_string));
    if ($save) {
      $xml = $dom->saveXML();
      file_put_contents($filepath, html_entity_decode($xml));
    }
    return $dom->saveXML();
  }

  public function writeLang($filepath, $data=false, $save=false) {
    if (FilePathHelper::testFile($filepath)) {
      if($data) {
        $xml_string = XmlHelper::createXML('contentList',$data)->saveXML();
      } else {
        $xml_string = XmlHelper::createXML('contentList',$this->_Lang[$filepath]['contentList'])->saveXML();
      }
      $dom = XmlHelper::createFormattedDOM(XmlHelper::minifyEmptyTags($xml_string));
      if ($save) {
        $xml = $dom->saveXML();
        $xml = XmlHelper::injectXmlNamespaces($xml, 'contentList', ['xsd' => 'http://www.w3.org/2001/XMLSchema','xsi' => 'http://www.w3.org/2001/XMLSchema-instance']);
        file_put_contents($filepath, $xml);
      }
      return $dom->saveXML();
    } else {
      return "Empty";
    }
  }

  public function writeTxt($filepath, $save=false) {
    $data = TextParserHelper::array2Txt($this->_Data[$filepath]);
    if ($save) {
      file_put_contents($filepath, preg_replace("/\r\n?/", "\n", $data));
    }
    return $data;
  }

  public function writeTxtRaw($filepath, $txt, $save=false) {
    $data = preg_replace("/\r\n?/", "\n", TextParserHelper::array2Txt(TextParserHelper::string2Array($txt)));
    if ($save) {
      file_put_contents($filepath, $data);
    }
    return $data;
  }
}
?>
