<?php

require __DIR__ . '/vendor/autoload.php';

use LaLit\XML2Array;
use LaLit\Array2XML;

class dataset {

  protected $_Lang = array();

  protected $_Data = array();

  public function __construct($langfile, $moddir) {
    $this->parseLang($langfile);
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
    $xml = file_get_contents($langfile);
    $this->_Lang[$langfile] = XML2Array::createArray($xml);
  }

  private function parseLsx($filepath) {
    $xml = file_get_contents($filepath);
    $this->_Data[$filepath] = XML2Array::createArray($xml);
  }

  private function parseTxt($filepath) {
    $data = file($filepath);
    foreach ($data as $line) {
      if (!ctype_space($line)) {
        $words = preg_split('/("[^"]*")|\h+/', $line, -1, PREG_SPLIT_NO_EMPTY|PREG_SPLIT_DELIM_CAPTURE);
        switch ($words[0]) {
          case 'new':
            $pathone = $words[0];
            $pathtwo = $words[1];
            $paththr = $words[2];
            break;;
          default:
            $pathfor = $words[0];
            unset($words[0]);
            switch (count($words)) {
              case '0':
              case '1':
              case '2':
                $this->_Data[$filepath][$pathone][$pathtwo][$paththr][$pathfor][] = implode(" ",$words);
                break;;
              case '3':
              case '4':
                $pathfiv = $words[1];
                unset($words[1]);
                $this->_Data[$filepath][$pathone][$pathtwo][$paththr][$pathfor][][$pathfiv] = implode(" ",$words);
                break;;
            }
            break;;
        } 
      }
    }
  }

  private function buildDataSet($moddir) {
    $directory = new RecursiveDirectoryIterator($moddir); 
    $iterator = new RecursiveIteratorIterator($directory);

    foreach ($iterator as $info) {
      if ($info->isFile() && $info->getExtension() === 'lsx') {
        $this->parseLsx($info->getPathname());
      } elseif ($info->isFile() && $info->getExtension() === 'txt') {
        $this->parseTxt($info->getPathname());
      }
    }
  }

  private function array_recursive_search_key_map($needle, $haystack) {
    $results = array();
    $iterator = new RecursiveIteratorIterator(new RecursiveArrayIterator($haystack));
    foreach ($iterator as $key => $value) {
      if (!is_array($value) && trim($value) == trim($needle)) {
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

  public function setData($newvalue,$locations) {
   foreach ($locations as $path) {
      $this->set_nested_array_value($this->_Data, $path, $newvalue);
    }
  }

  public function setLang($newvalue,$locations) {
   foreach ($locations as $path) {
      $this->set_nested_array_value($this->_Lang, $path, $newvalue);
    }
  }

  public function searchData($key) {
    return $this->array_recursive_search_key_map($key, $this->_Data);
  }

  public function searchLang($key) {
    return $this->array_recursive_search_key_map($key, $this->_Lang);
  }

  public function findNReplaceData($oldvalue,$newvalue) {
    $this->setData($newvalue, $this->searchData($oldvalue));
  }

  public function findNReplaceLang($oldvalue,$newvalue) {
    $this->setLang($newvalue, $this->searchLang($oldvalue));
  }

  public function writeTxt($filepath) {
    $lines = "";
    foreach($this->_Data[$filepath]['new']['entry'] as $name => $newentry) {
      $lines .= "new entry ".$name."\n";
      foreach($newentry as $key => $value) {
        if ($key == "type") {
          $lines .= "type ".trim($value[0])."\n";
        } else {
          foreach($value as $line) {
            foreach($line as $param => $data) {
              $lines .="data ".$param." ".trim($data)."\n";
            }
          }
        }
      }
      $lines .= "\n";
    }
    file_put_contents($filepath,$lines);
  }

  public function writeXml($filepath) {
    Array2XML::init(null,null,null,false);
    $xml_string = Array2XML::createXML('save',$this->_Data[$filepath]['save'])->saveXML();
    $pattern = '/"><\\/(.*?)\\>/';
    $replacement = '"/>';
    $new_xml_string = preg_replace($pattern, $replacement, $xml_string);
    $dom = new DOMDocument();
    $dom->preserveWhiteSpace = false; // set before loading or creating
    $dom->loadXML($new_xml_string);
    $dom->formatOutput = true;
    $dom->saveXML($filepath);
  }

}

$dataset = new dataset($argv[1], $argv[2]);
#print_r($dataset->getLang());
#print_r($dataset->getData());

?>
