<?php namespace RockMigrations;

use ProcessWire\RockMigrations;

trait PageClass {

  /**
   * Get pageclass
   * @return string
   */
  public function pageClass($namespace = true) {
    if($namespace) return get_class($this);
    return $this->className;
  }

  /**
   * @return RockMigrations
   */
  public function rockmigrations() {
    return $this->wire->modules->get('RockMigrations');
  }

}
