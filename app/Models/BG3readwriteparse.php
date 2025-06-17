<?php

namespace App\Models;

use CodeIgniter\Model;

use App\Helpers\XmlHelper as Array2XML;
use App\Helpers\XmlHelper as XML2Array;
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

  private function strip_quotes($str) {
    return trim($str, "\"'");
  }

  private function testFile($file) {
    $test = file($file);
    $x = 0;
    foreach($test as $line) {
      $x++;
    }
    return ($x > 3) ? true : false;
  }

  private function parseLang($langfile) {
    if ($this->testFile($langfile)) {
      $xml = file_get_contents($langfile);
      $this->_Lang[$langfile] = XML2Array::createArray($xml);
    } else {
      $this->_Lang[$langfile] = "Empty";
    }
  }

  private function parseLsx($filepath) {
    $xml = file_get_contents($filepath);
    $this->_Data[$filepath] = XML2Array::createArray($xml);
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
        switch ($info->getExtension()) {
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
          case 'DDS':
          case 'dds':
            $this->parseImg($info->getExtension(), $info->getPathname());
            break;;
        }
      }
    }
  }

  private function array_recursive_search_key_map($needle, $haystack) {
    $results = array();
    $iterator = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($haystack));
    foreach ($iterator as $key => $value) {
      if (!is_array($value) && preg_match('/'.$needle.'/',trim($value))) {
        $path = array();
        for ($depth = $iterator->getDepth(); $depth >= 0; $depth--) {
          $subIterator = $iterator->getSubIterator($depth);
          $path[] = $subIterator->key();
        }
        $path = array_reverse($path);
        $results[] = $path;
      }
    }
    return $results;
  }

  private function array_get_nested_value($results, $array) {
    foreach ($results as $keymap) {
      $nest_depth = sizeof($keymap);
      $value = $array;
      for ($i = 0; $i < $nest_depth; $i++) {
        $value = $value[$keymap[$i]];
      }
      return $value;
    }
  }

  private function set_nested_array_value(&$array, $path, $newvalue) {
    $current = &$array;
    foreach ($path as $key => $value) {
      $current = &$current[$value];
    }
    $current = $newvalue;
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
      $this->set_nested_array_value($this->_Data, $path, $newvalue);
      $this->saveFile($path[0]);
    }
  }

  public function setLangBySearch($newvalue,$locations) {
    foreach ($locations as $path) {
      $this->set_nested_array_value($this->_Lang, $path, $newvalue);
      $this->saveFile($path[0]);
    }
  }

  public function searchData($key) {
    return $this->array_recursive_search_key_map($key, $this->_Data);
  }

  public function searchLang($key) {
    return $this->array_recursive_search_key_map($key, $this->_Lang);
  }

  public function findNReplace($oldvalue,$newvalue) {
    $this->setDataBySearch($newvalue, $this->searchData($oldvalue));
    $langResults = $this->searchLang($oldvalue);
    if(end($langResults[0]) == "contentuid" && preg_match("/\;/",$newvalue)) {
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
    $wrapper = '<div contenteditable="true" class="display" id="data" name="data">';
    $closer = '</div>';
    switch (substr($file, -3)) {
      case "xml":
        return $this->writeLang($file);
        break;;
      case "lsx":
	$data = $this->writeXml($file);
        return $wrapper.formatData($data,$term).$closer;
        break;;
      case "txt":
        $data = $this->writeTxt($file);
        return $wrapper.formatData($data,$term).$closer;
        break;;
      case "khn":
        $data = $this->writeKhn($file);
        return $wrapper.formatData($data,$term).$closer;
        break;;
      case "png":
       case "DDS":
       case "dds":
       return $this->displayImg($file);
       break;;
    }
  }

  public function saveFile($file, $data=false) {
    if (is_null($data)){
      $data = $this->_Data[$file];
    } elseif (!is_array($data)) {
      $data = html_entity_decode($data, ENT_QUOTES | ENT_XML1);
    }
    switch (substr($file, -3)) {
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
      case "DDS":
      case "dds":
        print_r($this->getData()[$file]);
#        return $this->displayImg($file);
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
    Array2XML::init(null,null,null,false);
    if($data) {
      $xml_string = Array2XML::createXML('save',XML2Array::createArray($data)['save'])->saveXML();
    } else {
      $xml_string = Array2XML::createXML('save',$this->_Data[$filepath]['save'])->saveXML();
    }
    $pattern = '/"><\\/(.*?)\\>/';
    $replacement = '"/>';
    $new_xml_string = preg_replace($pattern, $replacement, $xml_string);
    $dom = new \DOMDocument();
    $dom->preserveWhiteSpace = false;
    $dom->loadXML($new_xml_string);
    $dom->formatOutput = true;
    if ($save) {
      $xml = $dom->saveXML();
      file_put_contents($filepath, html_entity_decode($xml));
    }
    return $dom->saveXML();
  }

  public function writeLang($filepath, $data=false, $save=false) {
    if ($this->testFile($filepath)) {
      Array2XML::init(null,null,null,false);
      if($data) {
        $xml_string = Array2XML::createXML('contentList',$data)->saveXML();
      } else {
        $xml_string = Array2XML::createXML('contentList',$this->_Lang[$filepath]['contentList'])->saveXML();
      }
      $pattern = '/"><\\/(.*?)\\>/';
      $replacement = '"/>';
      $new_xml_string = preg_replace($pattern, $replacement, $xml_string);
      $dom = new \DOMDocument();
      $dom->preserveWhiteSpace = false;
      $dom->loadXML($new_xml_string);
      $dom->formatOutput = true;
      if ($save) {
        $xml = $dom->saveXML();
        $xml = preg_replace('/<contentList>/', '<contentList xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">', $xml);
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
