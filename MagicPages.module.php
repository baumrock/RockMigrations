<?php

namespace RockMigrations;

use ProcessWire\Module;
use ProcessWire\PageArray;
use ProcessWire\RockMigrations;
use ProcessWire\WireData;

class MagicPages extends WireData implements Module
{

  private $readyClasses;

  public static function getModuleInfo()
  {
    return [
      'title' => 'MagicPages',
      'version' => '1.0.0',
      'summary' => 'Autoload module to support MagicPages',
      'autoload' => true,
      'singular' => true,
      'icon' => 'smile-o',
      'requires' => [],
      'installs' => [],
    ];
  }

  public function init()
  {
    $this->readyClasses = $this->wire(new PageArray());
    if ($this->wire->config->useMagicClasses === false) return;
    if ($this->wire->config->useMagicClasses === 0) return;

    foreach ($this->wire->templates as $tpl) {
      $p = $this->wire->pages->newPage(['template' => $tpl]);
      if (!$p->isMagicPage) continue;
      if (method_exists($p, 'init')) $p->init();
      if (method_exists($p, 'migrate')) $this->rockmigrations()->watch($p);
      if (method_exists($p, 'ready')) $this->readyClasses->add($p);
      $this->addMagicMethods($p);
    }
  }

  public function ready()
  {
    foreach ($this->readyClasses as $p) $p->ready();
  }

  /**
   * Add magic methods to this page object
   * @param Page $obj
   * @return void
   */
  public function addMagicMethods($obj)
  {

    if (method_exists($obj, "editForm")) {
      $this->wire->addHookAfter("ProcessPageEdit::buildForm", function ($event) use ($obj) {
        $page = $event->object->getPage();
        if ($obj->className !== $page->className) return;
        $form = $event->return;
        $page->editForm($form, $page);
      });
    }

    if (method_exists($obj, "editFormContent")) {
      $this->wire->addHookAfter("ProcessPageEdit::buildFormContent", function ($event) use ($obj) {
        $page = $event->object->getPage();
        if ($obj->className !== $page->className) return;
        $form = $event->return;
        $page->editFormContent($form, $page);
      });
    }

    if (method_exists($obj, "editFormSettings")) {
      $this->wire->addHookAfter("ProcessPageEdit::buildFormSettings", function ($event) use ($obj) {
        $page = $event->object->getPage();
        if ($obj->className !== $page->className) return;
        $form = $event->return;
        $page->editFormSettings($form, $page);
      });
    }

    // execute onSaved on every save
    // this will also fire when id=0
    if (method_exists($obj, "onSaved")) {
      $this->wire->addHookAfter("Pages::saved", function ($event) use ($obj) {
        $page = $event->arguments(0);
        if ($obj->className !== $page->className) return;
        $page->onSaved();
      });
    }

    // execute onSaveReady on every save
    // this will also fire when id=0
    if (method_exists($obj, "onSaveReady")) {
      $this->wire->addHookAfter("Pages::saveReady", function ($event) use ($obj) {
        $page = $event->arguments(0);
        if ($obj->className !== $page->className) return;
        $page->onSaveReady();
      });
    }

    // execute onCreate on saveReady when id=0
    if (method_exists($obj, "onCreate")) {
      $this->wire->addHookAfter("Pages::saveReady", function ($event) use ($obj) {
        $page = $event->arguments(0);
        if ($page->id) return;
        if ($obj->className !== $page->className) return;
        $page->onCreate();
      });
    }

    // execute onAdded on saved when id=0
    if (method_exists($obj, "onAdded")) {
      $this->wire->addHookAfter("Pages::added", function ($event) use ($obj) {
        $page = $event->arguments(0);
        if ($obj->className !== $page->className) return;
        $page->onAdded();
      });
    }
  }

  public function rockmigrations(): RockMigrations
  {
    return $this->wire->modules->get('RockMigrations');
  }
}
