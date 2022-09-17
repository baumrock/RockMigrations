<?php

namespace RockMigrations;

use ProcessWire\RockMigrations;

trait MagicPage
{
  public $isMagicPage = true;

  public function rockmigrations(): RockMigrations
  {
    return $this->wire->modules->get('RockMigrations');
  }
}
