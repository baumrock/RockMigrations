<?php

namespace RockMigrations;

use ProcessWire\RockFrontend;

trait MagicHomePage
{
  public function footerlinks()
  {
    return $this->getFormatted(RockFrontend::field_footerlinks);
  }
}
