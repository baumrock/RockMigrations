<?php

namespace RockMigrations;

use ProcessWire\HookEvent;
use ProcessWire\Module;
use ProcessWire\Page;
use ProcessWire\Paths;
use ProcessWire\RockMigrations;
use ProcessWire\WireArray;
use ProcessWire\WireData;
use ReflectionClass;

use function ProcessWire\rockmigrations;

class MagicPages extends WireData implements Module
{

  public $readyClasses;

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

  /** special methods */

  public function __construct()
  {
    if ($this->wire->config->useMagicPages === 0) return;
    if ($this->wire->config->useMagicPages === false) return;
    $this->wire->addHookAfter("ProcessWire::init", $this, "pwInit");
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

  protected function pwInit(): void
  {
    // note: must be wirearray, not pagearray
    // pagearray does not allow adding multiple pages with id=0
    $this->readyClasses = $this->wire(new WireArray());
    $rm = rockmigrations();

    // get magic templates from cache
    $templates = $rm->cache(
      "magic-templates",
      function () {
        $templates = [];
        foreach ($this->wire->templates as $tpl) {
          $p = $this->wire->pages->newPage(['template' => $tpl]);
          if (!property_exists($p, "isMagicPage")) continue;
          if (!$p->isMagicPage) continue;
          $templates[] = $tpl->name;
        }
        return $templates;
      },
    );

    // autoload magic templates
    if (!is_array($templates)) $templates = [];
    foreach ($templates as $tpl) {
      $p = $this->wire->pages->newPage(['template' => $tpl]);
      if (!property_exists($p, "isMagicPage") || !$p->isMagicPage) {
        // cache is outdated, recreate it
        $this->wire->cache->delete('magic-templates');
        continue;
      }
      if (method_exists($p, 'init')) $p->init();
      if (method_exists($p, 'ready')) $this->readyClasses->add($p);
      $this->rockmigrations()->watch($p, method_exists($p, 'migrate'));

      // add magic methods
      $config = $this->wire->config;
      if (!$config->noMagicFieldMethods) $this->addMagicFieldMethods($p);
      if (!$config->noMagicMethods) $this->addMagicMethods($p);
    }
  }

  /** regular methods */

  /**
   * Attach magic field methods
   * Makes it possible to access field "foo_bar_baz" as $page->baz()
   * This is very useful when creating fields with long prefixed names to
   * avoid name collisions. Its primary use was for RockPageBuilder but it
   * was moved to MagicPages later as it turned out to be very useful.
   */
  public function addMagicFieldMethods(Page $page)
  {
    if (!$tpl = $page->template) return;
    $fields = $tpl->fields;
    foreach ($fields as $field) {
      $fieldname = $field->name;
      $parts = explode("_", $fieldname);
      $methodname = array_pop($parts);

      // add the dynamic method via hook to the page
      $this->wire->addHookMethod(
        "Page(template=$tpl)::$methodname",
        function ($event) use ($fieldname) {
          // get field value of original field
          $page = $event->object;
          $raw = $event->arguments(0);
          if ($raw === 2) {
            $event->return = $page->getUnformatted($fieldname);
            return;
          }
          if ($raw) {
            $event->return = $page->getFormatted($fieldname);
            return;
          }
          $val = $page->edit($fieldname);
          if (is_string($val)) {
            /** @var RockFrontend $rf */
            $rf = $this->wire->modules->get('RockFrontend');
            if ($rf) $val = $rf->html($val);
          }
          $event->return = $val;
        },
        // we attach the hook as early as possible
        // that means if other hooks kick in later they have priority
        // this is to make sure we don't overwrite $page->editable() etc.
        ['priority' => 0]
      );
    }
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
        if ($page->className(true) !== $magicPage->className(true)) return;
        $form = $event->return;
        $page->editForm($form, $page);
      });
    }

    if (method_exists($magicPage, "editFormContent")) {
      $this->wire->addHookAfter("ProcessPageEdit::buildFormContent", function ($event) use ($magicPage) {
        $page = $event->object->getPage();
        if ($page->className(true) !== $magicPage->className(true)) return;
        $form = $event->return;
        $page->editFormContent($form, $page);
      });
    }

    if (method_exists($magicPage, "editFormSettings")) {
      $this->wire->addHookAfter("ProcessPageEdit::buildFormSettings", function ($event) use ($magicPage) {
        $page = $event->object->getPage();
        if ($page->className(true) !== $magicPage->className(true)) return;
        $form = $event->return;
        $page->editFormSettings($form, $page);
      });
    }

    // execute onAdded on saved when id=0
    if (method_exists($magicPage, "onAdded")) {
      $this->wire->addHookAfter("Pages::added", function ($event) use ($magicPage) {
        $page = $event->arguments(0);
        if ($page->className(true) !== $magicPage->className(true)) return;
        $page->onAdded();
      });
    }

    // field value changed
    if (method_exists($magicPage, "onChanged")) {
      $this->wire->addHookAfter("Page::changed", function ($event) use ($magicPage) {
        $page = $event->object;
        if ($page->className(true) !== $magicPage->className(true)) return;
        $page->onChanged(
          $event->arguments(0), // fieldname
          $event->arguments(1), // old value
          $event->arguments(2), // new value
        );
      });
    }

    // execute onCreate on saveReady when id=0
    if (method_exists($magicPage, "onCreate")) {
      $this->wire->addHookAfter("Pages::saveReady", function ($event) use ($magicPage) {
        $page = $event->arguments(0);
        if ($page->id) return;
        if ($page->className(true) !== $magicPage->className(true)) return;
        $page->onCreate();
      });
    }

    // form processing
    if (method_exists($magicPage, "onProcessInput")) {
      $this->wire->addHookAfter("InputfieldForm::processInput", function ($event) use ($magicPage) {
        if ($event->process != "ProcessPageEdit") return;
        $page = $event->process->getPage();
        if ($page->className(true) !== $magicPage->className(true)) return;
        $page->onProcessInput($event->arguments(0), $event->return);
      });
    }

    // execute onSaved on every save
    // this will also fire when id=0
    if (method_exists($magicPage, "onSaved")) {
      $this->wire->addHookAfter("Pages::saved", function ($event) use ($magicPage) {
        $page = $event->arguments(0);
        if ($page->className(true) !== $magicPage->className(true)) return;
        $page->onSaved();
      });
    }

    // execute onSaveReady on every save
    // this will also fire when id=0
    if (method_exists($magicPage, "onSaveReady")) {
      $this->wire->addHookAfter("Pages::saveReady", function ($event) use ($magicPage) {
        $page = $event->arguments(0);
        if ($page->className(true) !== $magicPage->className(true)) return;
        $page->onSaveReady();
      });
    }

    // execute onTrashed hook
    if (method_exists($magicPage, "onTrashed")) {
      $this->wire->addHookAfter("Pages::trashed", function ($event) use ($magicPage) {
        $page = $event->arguments(0);
        if ($page->className(true) !== $magicPage->className(true)) return;
        $page->onTrashed();
      });
    }

    /**
     * hook pagelist label
     * This implementation is different to the core getPageListLabel()!
     * When using pageListLabel() you will only modify the label in the regular
     * page tree, but labels in the menu or in page reference fields will stay untouched.
     */
    if (method_exists($magicPage, "pageListLabel")) {
      $this->wire->addHookAfter('ProcessPageListRender::getPageLabel', function (HookEvent $event) use ($magicPage) {
        $page = $event->arguments('page');
        if ($page->className(true) !== $magicPage->className(true)) return;
        $options = $event->arguments(1);
        $noTags = $options && is_array($options) && array_key_exists('noTags', $options) && $options['noTags'];
        if ($noTags) {
          // noTags is active, that means we are in a menu or such
          return;
        }
        // regular pagelist --> modify label
        $icon = "";
        if ($icon = $page->template->icon) $icon = "<i class='icon fa fa-fw fa-$icon'></i> ";
        $event->return = $icon . $page->pageListLabel($noTags);
      });
    }

    /**
     * Set page name from callback
     * Usage:
     * Add this to your MagicPage class:
     * public function setPageName() {
     *   return $this->title . " - " . date("Y");
     * }
     */
    if (method_exists($magicPage, "setPageName")) {
      $this->wire->addHookAfter("Pages::saved(id>0)", function (HookEvent $event) use ($magicPage) {
        $page = $event->arguments(0);
        if ($page->className(true) !== $magicPage->className(true)) return;
        $page->setName($page->setPageName());
        $page->save(['noHooks' => true]);
      });
      $this->wire->addHookAfter("ProcessPageEdit::buildForm", function (HookEvent $event) use ($magicPage) {
        $page = $event->process->getPage();
        if ($page->className(true) !== $magicPage->className(true)) return;
        $form = $event->return;
        if ($f = $form->get('_pw_page_name')) {
          $f->prependMarkup = "<style>#wrap_{$f->id} input[type=text] { display: none; }</style>";
          $f->notes = $this->_("Page name will be set automatically on save.");
        }
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
    $rm = rockmigrations();

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
        if ($ext == 'css') $rm->addStyles($cache);
        elseif ($ext == 'js') $rm->addScripts($cache);
      }
    }
    // now we have an assets from a pageclass inside a module (for example)
    else {
      foreach (['css', 'js'] as $ext) {
        // add asset to backend
        $file = substr($path, 0, -3) . $ext;
        if ($ext == 'css') $rm->addStyles($file);
        elseif ($ext == 'js') $rm->addScripts($file);
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
