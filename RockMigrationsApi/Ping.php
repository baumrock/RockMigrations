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

  /**
   * Returns the "foo" property of the rockmigrations instance
   *
   * Usage: Add this to /site/ready.php
   * $rm = $rockmigrations;
   * $rm->foo = 'I am foo!';
   * bd($rm->foo());
   */
  public function foo()
  {
    // return $this->foo; // this would return null!
    return $this->rm()->foo;
  }
}
