<?php

namespace RockMigrations\Tweaks;

use ProcessWire\HookEvent;

class CopyFieldNames extends Tweak
{
  public $description = "Copy field names on shift-click by SuperUsers "
    . "<a href=https://processwire.com/talk/topic/29071-using-javascript-to-copy-page-ids-from-page-list-and-field-nameslabels-from-inputfields/ target=_blank><i class='fa fa-info-circle'></i></a>";

  public function init()
  {
    // Add custom JS file to $config->scripts FilenameArray
    // This adds the custom JS fairly early in the FilenameArray which allows for stopping
    // event propagation so clicks on InputfieldHeader do not also expand/collapse InputfieldContent
    $this->wire->addHookBefore('ProcessController::execute', function (HookEvent $event) {
      if (!$event->wire->user->isSuperuser()) return;
      $rm = $this->rockmigrations();
      $url = $rm->toUrl(__DIR__ . "/CopyFieldNames.js", true);
      $this->wire->config->scripts->add($url);
    });
  }
}
