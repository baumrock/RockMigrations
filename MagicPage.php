<?php

namespace RockMigrations;

use ProcessWire\RockFrontend;
use ProcessWire\RockMigrations;
use ProcessWire\Site;

trait MagicPage
{
  public $isMagicPage = true;

  public function createOnTop()
  {
    return $this->rockmigrations()->createOnTop($this->template);
  }

  /**
   * Get instance of RockFrontend
   */
  public function rockfrontend(): RockFrontend
  {
    return $this->wire->modules->get('RockFrontend');
  }

  /**
   * Get instance of RockMigrations
   */
  public function rockmigrations(): RockMigrations
  {
    return $this->wire->modules->get('RockMigrations');
  }

  public function setPageNameFromField($fields = 'title')
  {
    return $this->rockmigrations()->setPageNameFromField(
      $this->template,
      $fields
    );
  }

  public function setPageNameFromTitle()
  {
    return $this->rockmigrations()->setPageNameFromTitle($this->template);
  }

  public function site(): Site
  {
    return $this->wire->modules->get('Site');
  }
}
