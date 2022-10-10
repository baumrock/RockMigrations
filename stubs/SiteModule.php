<?php

namespace ProcessWire;

class Site extends WireData implements Module
{

  public static function getModuleInfo()
  {
    return [
      'title' => 'Site',
      'version' => '0.0.1',
      'summary' => 'Site Module',
      'autoload' => true,
      'singular' => true,
      'icon' => 'bolt',
      'requires' => [
        'RockMigrations>=2.0.10',
      ],
    ];
  }

  public function init()
  {
    $this->wire('site', $this);
    /** @var RockMigrations $rm */
    $rm = $this->wire->modules->get('RockMigrations');
    $rm->watch($this);
  }

  public function migrate()
  {
    /** @var RockMigrations $rm */
    $rm = $this->wire->modules->get('RockMigrations');
  }
}
