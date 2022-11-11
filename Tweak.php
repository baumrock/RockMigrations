<?php

namespace RockMigrations\Tweaks;

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

  public function __debugInfo()
  {
    return [
      'name' => $this->name,
      'enabled' => $this->enabled,
      'description' => $this->description,
    ];
  }
}
