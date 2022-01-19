<?php namespace ProcessWire;
/**
 * @author Bernhard Baumrock, 19.01.2022
 * @license COMMERCIAL DO NOT DISTRIBUTE
 * @link https://www.baumrock.com
 */
class RockMigrations extends WireData implements Module {

  public static function getModuleInfo() {
    return [
      'title' => 'RockMigrations',
      'version' => '0.0.1',
      'summary' => 'Brings easy Migrations/GIT support to ProcessWire',
      'autoload' => 2,
      'singular' => true,
      'icon' => 'magic',
      'requires' => [],
      'installs' => [],
    ];
  }

  public function init() {
    $this->wire('rockmigrations', $this);
    $this->rm1(); // load RM1 (install it)
  }

  /**
   * Proxy all failing method calls to this version of RM to RM1
   */
  public function __call($method, $args) {
    if(!method_exists($this, $method)) {
      $rm1 = $this->rm1();
      if($rm1) return $rm1->$method(...$args);
      return false;
    }
    return self::$method(...$args);
  }

  /**
   * @return RockMigrations1
   */
  public function rm1() {
    return $this->wire->modules->get("RockMigrations1");
  }

}
