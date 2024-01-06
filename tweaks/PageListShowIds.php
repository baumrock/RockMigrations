<?php

namespace RockMigrations\Tweaks;

use ProcessWire\HookEvent;

class PageListShowIds extends Tweak
{
  public $description = "Shows the page ID in page tree for SuperUsers";

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
      .PageListId{
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
    // don't add hook in the menu navigation
    $options = $event->arguments(1);
    if ($options && is_array($options) && array_key_exists('noTags', $options) && $options['noTags']) return;

    // regular page list
    $page = $event->arguments('page');
    $event->return .= "<span class='PageListId' title='Shift-Click to copy' uk-tooltip>#$page</span>";
  }
}
