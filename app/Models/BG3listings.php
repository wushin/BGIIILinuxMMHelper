<?php

namespace App\Models;

use CodeIgniter\Model;

class BG3listings extends Model {

  protected $_MyMods = array();

  protected $_GameData = array();

  protected $_AllMods = array();

  protected $_Ignore = array('..', '.', '.git', 'ToDo', '.gitignore', 'tools', 'BG3LinuxHelper');

  public function __construct() {
    $this->_MyMods = array_diff(scandir(getenv('bg3LinuxHelper.MyMods')), $this->_Ignore);
    $this->_AllMods = array_diff(scandir(getenv('bg3LinuxHelper.AllMods')), $this->_Ignore);
    $this->_GameData = array_diff(scandir(getenv('bg3LinuxHelper.GameData')), $this->_Ignore);
  }

  public function __call($method, $arguments) {
    $arguments = array_merge(array("stdObject" => $this), $arguments);
    if (isset($this->{$method}) && is_callable($this->{$method})) {
      return call_user_func_array($this->{$method}, $arguments);
    } else {
      throw new Exception("Fatal error: Call to undefined method stdObject::{$method}()");
    }
  }

  public function getMyMods() {
    return $this->_MyMods;
  }

  public function getAllMods() {
    return $this->_AllMods;
  }

  public function getGameData() {
    return $this->_GameData;
  }

  public function getAll() {
    return array("MyMods" => $this->_MyMods, "AllMods" => $this->_AllMods, "GameData" => $this->_GameData);
  }
}
?>
