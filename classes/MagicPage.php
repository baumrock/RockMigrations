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
   * Method that returns the pages template name
   * This is to be consistant with RockPageBuilder+RockMigrations snippets
   */
  public function getTplName(): string
  {
    return (string)$this->template;
  }

  /**
   * Renders a badge in the page list
   * Often needed for adding dates to page titles etc.
   * https://i.imgur.com/nB2IYNS.png
   */
  public function pageListBadge($str, $style = '')
  {
    $str = trim($str);
    if (!$str) return;
    return "<span style='
      padding:2px 10px;
      border-radius:5px;
      background:#efefef;
      font-size:0.8em;
      $style'>$str</span>";
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
