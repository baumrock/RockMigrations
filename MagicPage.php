<?php

namespace RockMigrations;

use ProcessWire\RockMigrations;

trait MagicPage
{
  public $isMagicPage = true;

  /** ##### rockmigrations tools shortcuts ##### */

  public function createOnTop()
  {
    return $this->rockmigrations()->createOnTop($this->template);
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

  /** ##### end rockmigrations tools shortcuts ##### */

  public function rockmigrations(): RockMigrations
  {
    return $this->wire->modules->get('RockMigrations');
  }
}
