<?php

namespace RockMigrations\Tweaks;

use ProcessWire\HookEvent;

/**
 * Note: See https://github.com/processwire/processwire-issues/issues/1826
 */
class LangActive extends Tweak
{
  public $description = "Set all languages of new pages active by default";

  public function ready()
  {
    if ($this->wire->page->template != 'admin') return;
    $this->wire->addHookAfter("Pages::added", $this, "activate");
  }

  public function activate(HookEvent $event)
  {
    $page = $event->arguments(0);
    $languages = $this->wire->languages->findNonDefault();
    foreach ($languages as $lang) $page->setAndSave("status$lang", 1);
  }
}
