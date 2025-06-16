<?php

namespace App\Models;

use CodeIgniter\Model;

use LaLit\XML2Array;
use LaLit\Array2XML;
helper('BG3');

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
    $this->_Data[$filepath] = $this->txt2Array($data);
  }

  private function string2Array($txt) {
    $data = file('data://text/plain,' . urlencode($txt), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    return $this->txt2Array($data);
  }

  private function txt2Array($data) {
    $result = [];
    $current = null;

    foreach ($data as $line) {
      $line = trim($line);
      if (stripos($line, 'new entry') === 0) {
          if ($current) $result[] = $current;
          $id = strip_quotes(trim(str_replace('new entry', '', $line)));
          $current = ['type' => 'entry', 'id' => $id, 'properties' => []];
      } elseif (stripos($line, 'new equipment') === 0) {
          if ($current) $result[] = $current;
          $id = strip_quotes(trim(str_replace('new equipment', '', $line)));
          $current = ['type' => 'equipment', 'id' => $id, 'initialWeaponSet' => null, 'equipmentGroups' => []];
      } elseif (stripos($line, 'new treasuretable') === 0) {
          if ($current) $result[] = $current;
          $id = strip_quotes(trim(str_replace('new treasuretable', '', $line)));
          $current = ['type' => 'treasuretable', 'id' => $id, 'subtables' => []];
      } elseif ($current && $current['type'] === 'equipment') {
          if (stripos($line, 'add initialweaponset') === 0) {
              $current['initialWeaponSet'] = strip_quotes(trim(str_replace('add initialweaponset', '', $line)));
          } elseif (stripos($line, 'add equipment entry') === 0) {
              $entry = strip_quotes(trim(str_replace('add equipment entry', '', $line)));
              $lastGroupIndex = count($current['equipmentGroups']) - 1;
              if ($lastGroupIndex < 0) $current['equipmentGroups'][] = [];
              $current['equipmentGroups'][$lastGroupIndex][] = $entry;
          } elseif (stripos($line, 'add equipmentgroup') === 0) {
              $current['equipmentGroups'][] = [];
          }
      } elseif ($current && $current['type'] === 'treasuretable') {
          if (stripos($line, 'new subtable') === 0) {
              $weight = strip_quotes(trim(str_replace('new subtable', '', $line)));
              $current['subtables'][] = ['type' => 'subtable', 'weight' => $weight, 'items' => []];
          } elseif (preg_match('/^object category\s+"?([^"]+)"?,\d.*$/', $line, $matches)) {
              $i = count($current['subtables']) - 1;
              if ($i >= 0) $current['subtables'][$i]['items'][] = strip_quotes($matches[1]);
          } elseif (preg_match('/^(StartLevel|EndLevel)\s+"?(\d+)"?$/', $line, $matches)) {
              $i = count($current['subtables']) - 1;
              if ($i >= 0) $current['subtables'][$i][$matches[1]] = (int)$matches[2];
          }
      } elseif ($current && in_array($current['type'], ['entry', 'equipment', 'treasuretable'])) {
          if (preg_match('/^(data|using|type)\s+"([^"]+)"\s*"?(.*?)"?$/', $line, $matches)) {
              $key = $matches[1];
              $subkey = strip_quotes($matches[2]);
              $value = strip_quotes($matches[3]);

              if (!isset($current['properties'][$key])) {
                  $current['properties'][$key] = [];
              }

              if ($key === 'data') {
                  $current['properties'][$key][] = [$subkey => $value];
              } elseif ($key === 'using') {
                  $current['properties'][$key][] = $subkey;
              } elseif ($key === 'type') {
                  $current['properties']['declared_type'] = $subkey;
              }
          } elseif (preg_match('/^(\w+)\s+"([^"]+)"$/', $line, $matches)) {
              $current['properties'][$matches[1]] = strip_quotes($matches[2]);
          }
       }
    }

    if ($current) $result[] = $current;
    return $result;
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
    $data = $this->array2Txt($this->_Data[$filepath]);
    if ($save) {
      file_put_contents($filepath, preg_replace("/\r\n?/", "\n", $data));
    }
    return $data;
  }

  public function writeTxtRaw($filepath, $txt, $save=false) {
    $data = preg_replace("/\r\n?/", "\n", $this->array2Txt($this->string2Array($txt)));
    if ($save) {
      file_put_contents($filepath, $data);
    }
    return $data;
  }

  private function array2Txt($txt) {
    $lines = [];
    foreach ($txt as $entry) {
      switch ($entry['type']) {
          case 'entry':
              $lines[] = 'new entry "' . $entry['id'] . '"';
              if (isset($entry['properties']['declared_type'])) {
                  $lines[] = 'type "' . $entry['properties']['declared_type'] . '"';
              }
              break;
          case 'equipment':
              $lines[] = 'new equipment "' . $entry['id'] . '"';
              if ($entry['initialWeaponSet']) {
                  $lines[] = 'add initialweaponset "' . $entry['initialWeaponSet'] . '"';
              }
              foreach ($entry['equipmentGroups'] as $group) {
                  $lines[] = 'add equipmentgroup';
                  foreach ($group as $equipEntry) {
                      $lines[] = 'add equipment entry "' . $equipEntry . '"';
                  }
              }
              break;
          case 'treasuretable':
              $lines[] = 'new treasuretable "' . $entry['id'] . '"';
              foreach ($entry['subtables'] as $sub) {
                  $lines[] = 'new subtable "' . $sub['weight'] . '"';
                  if (isset($sub['StartLevel'])) {
                      $lines[] = 'StartLevel "' . $sub['StartLevel'] . '"';
                  }
                  if (isset($sub['EndLevel'])) {
                      $lines[] = 'EndLevel "' . $sub['EndLevel'] . '"';
                  }
                  foreach ($sub['items'] as $item) {
                      $lines[] = 'object category "' . $item . '",1';
                  }
              }
              break;
      }

      // Shared property block
      if (!empty($entry['properties'])) {
          foreach ($entry['properties'] as $key => $value) {
              if ($key === 'data' && is_array($value)) {
                  foreach ($value as $pair) {
                      foreach ($pair as $k => $v) {
                          $lines[] = 'data "' . $k . '" "' . $v . '"';
                      }
                  }
              } elseif ($key === 'using' && is_array($value)) {
                  foreach ($value as $v) {
                      $lines[] = 'using "' . $v . '"';
                  }
              } elseif ($key !== 'declared_type') {
                  if (is_array($value)) {
                      foreach ($value as $v) {
                          $lines[] = $key . ' "' . $v . '"';
                      }
                  } else {
                      $lines[] = $key . ' "' . $value . '"';
                  }
               }
          }
      }

      $lines[] = ''; // Separate entries with a blank line
    }

    return implode("\n", $lines);
  }
}
?>
