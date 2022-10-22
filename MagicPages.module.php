<?php

namespace RockMigrations;

use ProcessWire\HookEvent;
use ProcessWire\Module;
use ProcessWire\PageArray;
use ProcessWire\Paths;
use ProcessWire\RockMigrations;
use ProcessWire\WireData;
use ReflectionClass;

class MagicPages extends WireData implements Module
{

  private $readyClasses;

  private $filePaths = [];

  public static function getModuleInfo()
  {
    return [
      'title' => 'MagicPages',
      'version' => '1.0.8',
      'summary' => 'Autoload module to support MagicPages',
      'autoload' => true,
      'singular' => true,
      'icon' => 'magic',
      'requires' => [
        'RockMigrations>=1.6.3',
      ],
      'installs' => [],
    ];
  }

  public function __construct()
  {
    $this->wire->addHookAfter("ProcessWire::init", function () {
      $this->readyClasses = $this->wire(new PageArray());
      if ($this->wire->config->useMagicClasses === false) return;
      if ($this->wire->config->useMagicClasses === 0) return;

      foreach ($this->wire->templates as $tpl) {
        $p = $this->wire->pages->newPage(['template' => $tpl]);
        if (!property_exists($p, "isMagicPage")) continue;
        if (!$p->isMagicPage) continue;
        if (method_exists($p, 'init')) $p->init();
        if (method_exists($p, 'ready')) $this->readyClasses->add($p);
        $this->rockmigrations()->watch($p, method_exists($p, 'migrate'));
        $this->addMagicMethods($p);
      }
    });
  }

  public function init()
  {
    $this->wire->addHookAfter("ProcessPageEdit::buildForm", $this, "addPageAssets");
  }

  public function ready()
  {
    $ready = $this->readyClasses ?: []; // prevents error on uninstall
    foreach ($ready as $p) $p->ready();
  }

  /**
   * Add magic methods to this page object
   * @param Page $magicPage
   * @return void
   */
  public function addMagicMethods($magicPage)
  {

    if (method_exists($magicPage, "editForm")) {
      $this->wire->addHookAfter("ProcessPageEdit::buildForm", function ($event) use ($magicPage) {
        $page = $event->object->getPage();
        if ($page->className !== $magicPage->className) return;
        $form = $event->return;
        $page->editForm($form, $page);
      });
    }

    if (method_exists($magicPage, "editFormContent")) {
      $this->wire->addHookAfter("ProcessPageEdit::buildFormContent", function ($event) use ($magicPage) {
        $page = $event->object->getPage();
        if ($page->className !== $magicPage->className) return;
        $form = $event->return;
        $page->editFormContent($form, $page);
      });
    }

    if (method_exists($magicPage, "editFormSettings")) {
      $this->wire->addHookAfter("ProcessPageEdit::buildFormSettings", function ($event) use ($magicPage) {
        $page = $event->object->getPage();
        if ($page->className !== $magicPage->className) return;
        $form = $event->return;
        $page->editFormSettings($form, $page);
      });
    }

    // execute onSaved on every save
    // this will also fire when id=0
    if (method_exists($magicPage, "onSaved")) {
      $this->wire->addHookAfter("Pages::saved", function ($event) use ($magicPage) {
        $page = $event->arguments(0);
        if ($page->className !== $magicPage->className) return;
        $page->onSaved();
      });
    }

    // execute onSaveReady on every save
    // this will also fire when id=0
    if (method_exists($magicPage, "onSaveReady")) {
      $this->wire->addHookAfter("Pages::saveReady", function ($event) use ($magicPage) {
        $page = $event->arguments(0);
        if ($page->className !== $magicPage->className) return;
        $page->onSaveReady();
      });
    }

    // execute onCreate on saveReady when id=0
    if (method_exists($magicPage, "onCreate")) {
      $this->wire->addHookAfter("Pages::saveReady", function ($event) use ($magicPage) {
        $page = $event->arguments(0);
        if ($page->id) return;
        if ($page->className !== $magicPage->className) return;
        $page->onCreate();
      });
    }

    // execute onAdded on saved when id=0
    if (method_exists($magicPage, "onAdded")) {
      $this->wire->addHookAfter("Pages::added", function ($event) use ($magicPage) {
        $page = $event->arguments(0);
        if ($page->className !== $magicPage->className) return;
        $page->onAdded();
      });
    }

    // execute onTrashed hook
    if (method_exists($magicPage, "onTrashed")) {
      $this->wire->addHookAfter("Pages::trashed", function ($event) use ($magicPage) {
        $page = $event->arguments(0);
        if ($page->className !== $magicPage->className) return;
        $page->onTrashed();
      });
    }

    // form processing
    if (method_exists($magicPage, "onProcessInput")) {
      $this->wire->addHookAfter("InputfieldForm::processInput", function ($event) use ($magicPage) {
        if ($event->process != "ProcessPageEdit") return;
        $page = $event->process->getPage();
        if ($page->className !== $magicPage->className) return;
        $page->onProcessInput($event->return, $event->arguments(0));
      });
    }
  }

  /**
   * Add assets having the same filename as the magic page
   *
   * FooPage.php would load FooPage.css/.js on page edit screen
   */
  public function addPageAssets(HookEvent $event): void
  {
    $page = $event->process->getPage();
    $path = $this->getFilePath($page);
    $rm = $this->rockmigrations();

    // is the asset stored in /site/classes ?
    // then move it to /site/assets because /site/classes is blocked by htaccess
    if (strpos($path, $this->wire->config->paths->classes) === 0) {
      $cachePath = $this->wire->config->paths->assets . "MagicPages/assets/";
      foreach (['css', 'js'] as $ext) {
        $file = substr($path, 0, -3) . $ext;
        $name = basename($file);
        $cache = $cachePath . $name;

        // delete file?
        if (!is_file($file) and is_file($cache)) {
          $this->wire->files->unlink($cache);
        }

        // create file?
        if ($rm->filemtime($file) > $rm->filemtime($cache)) {
          $this->wire->files->mkdir($cachePath, true);
          $this->wire->files->copy($file, $cache);
        }

        // add asset to backend
        if ($ext == 'css') $this->rockmigrations()->addStyles($cache);
        elseif ($ext == 'js') $this->rockmigrations()->addScripts($cache);
      }
    }
    // now we have an assets from a pageclass inside a module (for example)
    else {
      foreach (['css', 'js'] as $ext) {
        // add asset to backend
        $file = substr($path, 0, -3) . $ext;
        if ($ext == 'css') $this->rockmigrations()->addStyles($file);
        elseif ($ext == 'js') $this->rockmigrations()->addScripts($file);
      }
    }
  }

  /**
   * Get filepath of file for given page
   */
  public function getFilePath($page): string
  {
    // try to get filepath from cache
    $tpl = (string)$page->template;
    if ($tpl and array_key_exists($tpl, $this->filePaths)) {
      return $this->filePaths[$tpl];
    }
    // otherwise get filepath from reflectionclass
    $reflector = new ReflectionClass($page);
    $filePath = Paths::normalizeSeparators($reflector->getFileName());
    if ($tpl) $this->filePaths[$tpl] = $filePath;
    return $filePath;
  }

  public function rockmigrations(): RockMigrations
  {
    return $this->wire->modules->get('RockMigrations');
  }
}
