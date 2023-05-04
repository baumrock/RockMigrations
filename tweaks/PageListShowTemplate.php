<?php

namespace RockMigrations\Tweaks;

use ProcessWire\HookEvent;

class PageListShowTemplate extends Tweak
{
  public $description = "Shows the page template in page tree for SuperUsers";

  public function ready()
  {
    if (!$this->wire->user->isSuperuser()) return;
    if ($this->wire->page->template != 'admin') return;
    $this->wire->addHookAfter('ProcessPageListRender::getPageLabel', $this, "hookPageLabel");
    $this->wire->addHookAfter("Page(template=admin)::render", $this, "addStyle");
  }

  public function addStyle(HookEvent $event)
  {
    $event->return = str_replace(
      "</head>",
      "<style>
      .PageListTemplate{
        color:#afafaf;
        margin-left:5px;
        font-size:0.7rem;
      }
      </style></head>",
      $event->return
    );
  }

  public function hookPageLabel(HookEvent $event)
  {
    $page = $event->arguments('page');
    $event->return .= "<span class='PageListTemplate'>[{$page->template}]</span>";
  }
}
