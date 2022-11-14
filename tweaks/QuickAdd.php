<?php

namespace RockMigrations\Tweaks;

use ProcessWire\HookEvent;

class QuickAdd extends Tweak
{
  public $description = "Skip template selection on page add if only a single template is allowed.";

  public function ready()
  {
    if ($this->wire->page->template != 'admin') return;
    $this->wire->addHookBefore("ProcessPageAdd::buildForm", $this, "skipAdd");
  }

  public function skipAdd(HookEvent $event)
  {
    if ($event->process != "ProcessPageAdd") return;
    $templates = $event->process->getAllowedTemplates();
    if (count($templates) !== 1) return;
    foreach ($templates as $k => $tpl) {
      $p = $this->wire->pages->newPage($tpl);
      $p->parent = $this->wire->input->get('parent_id', 'int');
      $p->addStatus('unpublished');
      $p->save();
      $this->wire->session->redirect($p->editUrl());
    }
  }
}
