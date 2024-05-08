<?php

namespace RockMigrations\Tweaks;

use ProcessWire\HookEvent;

class PageListShowTemplateLink extends Tweak
{
  public $description = "Shows the template name and edit link in the page tree action list for SuperUsers - Originally from AdminOnSteroids";

  public function ready()
  {
    if (!$this->wire->user->isSuperuser()) return;
    if ($this->wire->page->template != 'admin') return;
    $this->wire->addHookAfter('ProcessPageListActions::getActions', $this, 'addAction');
  }

  public function addAction(HookEvent $event)
  {
    $page = $event->arguments('page');
    $actions = $event->return;
    $template = $page->template;

    $templateEditUrl = $this->config->urls->httpAdmin . 'setup/template/edit?id=' . $template->id;

    $editTemplateAction = array(
      'editTemplate' => array(
        // use "Edit" to enable built-in long-click feature
        'cn' => 'Edit',
        'name' => $template->name,
        'url' => $templateEditUrl,
      ),
    );

    // put the template edit action before the Extras (
    $key_extras = array_search('extras', array_keys($actions));

    // home, trash, etc doesn't have 'extras', add the button to the end
    if (!$key_extras) {
      $key_extras = count($actions);
    }

    $actions = array_merge(array_slice($actions, 0, $key_extras, true), $editTemplateAction,
      array_slice($actions, $key_extras, null, true));

    $event->return = $actions;
  }
}
