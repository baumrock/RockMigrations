<?php namespace RockMigrations;

use Spyc;

class YAML extends Spyc {

  /**
   * Load YAML file
   */
  public function load($data) {
    return self::YAMLLoad($data);
  }

  /**
   * Save data to YAML file
   */
  public function save($path, $data) {
    $yaml = self::YAMLDump($data);
    file_put_contents($path, $yaml);
  }

}
