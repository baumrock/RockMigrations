<?php namespace RockMigrations;
class WireArray extends \ProcessWire\WireArray {

  /**
   * WireArray does not sort float values correctly
   * See https://github.com/processwire/processwire-issues/issues/1528
   */
  public function sortFloat($p) {
    $list = $this->getArray();
    usort($list, function($a, $b) use($p) {
      return $b->$p > $a->$p ? 1 : -1;
    });
    $this->removeAll()->import($list);
  }

  public function __debugInfo() {
    $arr = [];
    foreach($this->data as $item) {
      $arr[$item->path] = $item->getArray();
    }
    return $arr;
  }

}
