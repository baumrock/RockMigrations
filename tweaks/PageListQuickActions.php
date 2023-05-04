<?php

namespace RockMigrations\Tweaks;

use ProcessWire\HookEvent;

class PageListQuickActions extends Tweak
{
  public $description = "Shows action buttons instantly on row hover for superusers";

  public function ready()
  {
    if (!$this->wire->user->isSuperuser()) return;
    if ($this->wire->page->template != 'admin') return;
    $this->wire->addHookAfter("Page(template=admin)::render", $this, "addStyle");
  }

  public function addStyle(HookEvent $event)
  {
    $event->return = str_replace(
      "</head>",
      "<style>
      /* PageListQuickActions */
      .PageListItem:hover > .PageListActions {
        display:inline !important;
        opacity:1 !important;
      }
      </style></head>",
      $event->return
    );
  }
}
