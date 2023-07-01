<?php

namespace RockMigrations;

use RockShell\Command;

class RmDemo extends Command
{

  public function config()
  {
    $this->setDescription("Demonstrates how to ship modules with RockShell commands");
  }

  public function handle()
  {
    $this->comment("Just create a folder RockShell and add your commands there!");
    $this->comment("For commands that could be useful to others please send it to me or create a PR in RockShell :)");
    return self::SUCCESS;
  }
}
