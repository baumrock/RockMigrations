<?php

namespace RockMigrations\Tweaks;

use ProcessWire\RockMigrations;
use ProcessWire\WireData;

abstract class Tweak extends WireData
{
  public $description = "";

  public function init()
  {
  }

  public function ready()
  {
  }

  public function rockmigrations(): RockMigrations
  {
    return $this->wire->modules->get('RockMigrations');
  }

  public function __debugInfo()
  {
    return [
      'name' => $this->name,
      'enabled' => $this->enabled,
      'description' => $this->description,
    ];
  }
}
