<?php namespace RockMigrations;

use ProcessWire\WireArray;

class WireArrayDump extends WireArray {

  public function __debugInfo() {
    $arr = [];
    foreach($this->data as $item) {
      $arr[$item->path] = $item->getArray();
    }
    return $arr;
  }

}
