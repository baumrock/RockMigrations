<?php

namespace RockMigrationsApi;

use ProcessWire\RockMigrations;

class Ping extends RockMigrations
{

  /**
   * Returns pong!
   */
  public function ping(): string
  {
    return "pong!";
  }
}
