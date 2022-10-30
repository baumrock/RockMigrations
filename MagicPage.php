<?php

namespace RockMigrations;

use ProcessWire\RockMigrations;

trait MagicPage
{
  public $isMagicPage = true;

  public function createOnTop()
  {
    return $this->rockmigrations()->createOnTop($this->template);
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
}
