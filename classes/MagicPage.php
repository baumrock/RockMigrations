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

  public function getTplName(): string
  {
    return (string)$this->template;
  }

  /**
   * Remove submit button from page edit screen
   * Call this method from within editForm() magic method!
   */
  public function removeSaveButton($form)
  {
    $this->wire->addHookAfter("ProcessPageEdit::getSubmitActions", function ($event) {
      $event->return = [];
    });
    $form->remove('submit_save');
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
