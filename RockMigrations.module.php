<?php

namespace ProcessWire;

use DirectoryIterator;
use ProcessWire\WireArray as ProcessWireWireArray;
use RockMatrix\Block as RockMatrixBlock;
use RockMigrations\Deployment;
use RockMigrations\MagicPages;
use RockMigrations\WatchFile;
use RockMigrations\WireArray;
use RockMigrations\WireArray as WireArrayRM;
use RockPageBuilder\Block as RockPageBuilderBlock;
use Symfony\Component\Yaml\Yaml;
use TracyDebugger;

/**
 * @author Bernhard Baumrock, 19.01.2022
 * @license MIT
 * @link https://www.baumrock.com
 */
require_once __DIR__ . "/MagicPage.php";
require_once __DIR__ . "/PageClass.php";
class RockMigrations extends WireData implements Module, ConfigurableModule
{

  const debug = false;
  const cachename = 'rockmigrations-last-run';

  const outputLevelDebug = 'debug';
  const outputLevelQuiet = 'quiet';
  const outputLevelVerbose = 'verbose';

  /** @var WireData */
  public $conf;

  /** @var WireData */
  public $fieldSuccessMessages;

  /**
   * Timestamp of last run migration
   * @var int
   **/
  private $lastrun;

  private $migrateAll = false;

  private $noMigrate = false;

  public $noYaml = false;

  private $outputLevel = self::outputLevelQuiet;

  /** @var string */
  public $path;

  /** @var WireArrayRM */
  private $watchlist;

  /** @var YAML */
  private $yaml;

  public static function getModuleInfo()
  {
    return [
      'title' => 'RockMigrations',
      'version' => '2.15.0',
      'summary' => 'The Ultimate Automation and Deployment-Tool for ProcessWire',
      'autoload' => 2,
      'singular' => true,
      'icon' => 'magic',
      'installs' => [
        'MagicPages',
      ],
    ];
  }

  public function __construct()
  {
    parent::__construct();
    $this->path = $this->wire->config->paths($this);
    $this->wire->classLoader->addNamespace("RockMigrations", __DIR__ . "/classes");

    $this->watchlist = $this->wire(new WireArrayRM());
    $this->lastrun = (int)$this->wire->cache->get(self::cachename);
  }

  public function init()
  {
    $config = $this->wire->config;
    $this->wire('rockmigrations', $this);
    $this->installModule('MagicPages');
    if ($config->debug) $this->setOutputLevel(self::outputLevelVerbose);

    // for development
    // $this->watch($this, false);

    $this->conf = $this->wire(new WireData());
    $this->conf->setArray($this->getArray()); // get modules config
    if (is_array($config->rockmigrations)) {
      $this->conf->setArray($config->rockmigrations); // get config from file
    }

    // load tweaks
    $this->loadTweaks();

    // add hooks and session variables for inputfield success messages
    $this->addSuccessMessageFeature();

    // this creates folders that are necessary for PW and that might have
    // been deleted on deploy
    // for example this will create the sessions folder if it does not exist
    $this->createNeededFolders();

    // always watch + migrate /site/migrate.[yaml|json|php]
    // the third parameter makes it use the migrateNew() method
    // this will be the first file that is watched!
    $this->watch($config->paths->site . "migrate", true);
    $this->watchModules();

    // hooks
    $this->addHookAfter("Modules::refresh", $this, "triggerMigrations");
    $this->addHookBefore("InputfieldForm::render", $this, "showEditInfo");
    $this->addHookBefore("InputfieldForm::render", $this, "showCopyCode");
    $this->addHookBefore("Modules::uninstall", $this, "unwatchBeforeUninstall");

    // other actions on init()
    $this->loadFilesOnDemand();
    $this->syncSnippets();
  }

  private function loadTweaks()
  {
    $path = __DIR__ . "/tweaks";
    $options = ['extensions' => ['php']];
    $tweaks = $this->wire(new ProcessWireWireArray());
    foreach ($this->wire->files->find($path, $options) as $file) {
      $tweak = $this->loadTweak($file);
      $tweaks->add($tweak);
      if ($tweak->enabled) $tweak->init();
    }
    $this->tweaks = $tweaks;
  }

  private function loadTweak($file)
  {
    require_once __DIR__ . "/Tweak.php";
    require_once $file;
    $base = pathinfo($file, PATHINFO_FILENAME);
    $class = "\RockMigrations\Tweaks\\$base";
    try {
      $tweak = new $class();
      $tweak->name = $base;
      $tweak->enabled = in_array($base, (array)$this->enabledTweaks);
      return $tweak;
    } catch (\Throwable $th) {
      if ($this->wire->user->isSuperuser()) {
        throw new WireException($th->getMessage());
      }
    }
  }

  public function ready()
  {
    $this->forceMigrate();
    $this->addLivereload();

    // other actions
    $this->migrateWatchfiles();
    $this->changeFooter();

    // trigger ready() of tweaks
    foreach ($this->tweaks->find("enabled=1") as $tweak) $tweak->ready();

    // load RockMigrations.js on backend
    if ($this->wire->page->template == 'admin') {
      $this->wire->config->scripts->add(
        $this->wire->config->urls($this) . 'RockMigrations.js'
      );

      // fix ProcessWire language tabs issue
      if ($this->wire->languages) {
        $this->wire->config->js('rmUserLang', $this->wire->user->language->id);
      }
    }
  }

  /** ########## tools ########## */

  /**
   * Add a runtime field to an inputfield wrapper
   *
   * Usage:
   * $rm->addAfter($form, 'title', [
   *   'type' => 'markup',
   *   'label' => 'foo',
   *   'value' => 'bar',
   * ]);
   *
   * @return Inputfield
   */
  public function addAfter($wrapper, $existingItem, $newItem)
  {
    if (!$existingItem instanceof Inputfield) $existingItem = $wrapper->get($existingItem);
    $wrapper->add($newItem);
    $newItem = $wrapper->children()->last();
    $wrapper->insertAfter($newItem, $existingItem);
    return $newItem;
  }

  /**
   * Add scripts to $config->scripts and add cache busting timestamp
   */
  public function addScripts($scripts)
  {
    if (!is_array($scripts)) $scripts = [$scripts];
    foreach ($scripts as $script) {
      $path = $this->filePath($script);
      // if file is not found we silently skip it
      // it is silent because of MagicPages::addPageAssets
      if (!is_file($path)) continue;
      $url = str_replace(
        $this->wire->config->paths->root,
        $this->wire->config->urls->root,
        $path
      );
      $this->wire->config->scripts->add($url . "?m=" . filemtime($path));
    }
  }

  /**
   * Add styles to $config->styles and add cache busting timestamp
   */
  public function addStyles($styles)
  {
    if (!is_array($styles)) $styles = [$styles];
    foreach ($styles as $style) {
      $path = $this->filePath($style);

      // check if it is a less file
      if (pathinfo($path, PATHINFO_EXTENSION) === 'less') {
        $path = $this->saveCSS($path);
      }

      // if file is not found we silently skip it
      // it is silent because of MagicPages::addPageAssets
      if (!is_file($path)) continue;
      $url = str_replace(
        $this->wire->config->paths->root,
        $this->wire->config->urls->root,
        $path
      );
      $this->wire->config->styles->add($url . "?m=" . filemtime($path));
    }
  }

  /**
   * Make all pages having given template be created on top of the list
   * @return void
   */
  public function createOnTop($tpl)
  {
    $tpl = $this->wire->templates->get((string)$tpl);
    $this->addHookAfter("Pages::added", function (HookEvent $event) {
      $page = $event->arguments(0);
      $this->wire->pages->sort($page, 0);
    });
  }

  /**
   * Remove non-breaking spaces in string
   * @return string
   */
  public function regularSpaces($str)
  {
    return preg_replace('/\xc2\xa0/', ' ', $str);
  }

  /**
   * Compile LESS file and save CSS version
   *
   * foo.less --> foo.less.css
   *
   * Requires the Less module and will silently return if anything goes wrong.
   * The method is intended to easily develop module styles in LESS and ship
   * the CSS version.
   */
  public function saveCSS($less, $onlySuperuser = true, $css = null): string
  {
    $css = $css ?: "$less.css";
    if (!is_file($less)) return $css;

    $mLESS = filemtime($less);
    $mCSS = is_file($css) ? filemtime($css) : 0;

    $sudoCheck = $onlySuperuser ? $this->wire->user->isSuperuser() : true;
    if ($mLESS > $mCSS and $sudoCheck) {
      if ($parser = $this->wire->modules->get('Less')) {
        // recreate css file
        /** @var Less $parser */
        $parser->addFile($less);
        $parser->saveCss($css);
        $mCSS = time();
        $this->log("Created new CSS file: $css");
      }
    }
    return $css;
  }

  /**
   * Set page name from field of template
   *
   * Usage:
   * $rm->setPageNameFromField("basic-page", "headline");
   * $rm->setPageNameFromField("basic-page", ["headline", "title"]);
   *
   * Make sure to install Page Path History module!
   *
   * @return void
   */
  public function setPageNameFromField($template, $fields = 'title')
  {
    if ($template instanceof Page) $template = $template->template;
    $template = $this->wire->templates->get((string)$template);
    if (!$template) return;
    $tpl = "template=$template";
    $this->addHookAfter("Pages::saved($tpl,id>0)", function (HookEvent $event) use ($fields) {
      /** @var Page $page */
      $page = $event->arguments(0);
      if ($page->rmSetPageName) return;
      $page->rmSetPageName = true;
      $langs = $this->wire->languages;
      if ($langs) {
        foreach ($langs as $lang) {
          try {
            // dont know why exactly that is necessary but had problems
            // at kaumberg "localName not callable in this context"??
            // though the method was definitely there...
            $old = $page->localName($lang);
          } catch (\Throwable $th) {
            $old = $page->name;
          }

          // get new value
          if (is_array($fields)) {
            $new = '';
            foreach ($fields as $field) {
              if ($new) continue;
              $new = $page->getLanguageValue($lang, (string)$field);
            }
          } else $new = $page->getLanguageValue($lang, (string)$fields);

          $new = $event->sanitizer->markupToText($new);
          $new = $event->sanitizer->pageNameTranslate($new);
          $new = $event->wire->pages->names()->uniquePageName($new, $page);
          if ($old != $new) {
            if ($lang->isDefault()) $page->setName($new);
            else $page->setName($new, $lang->name);
            $this->message($this->_("Page name updated to '$new' ($lang->name)"));
          }
        }
        $page->save(['noHooks' => true]);
      } else {
        $old = $page->name;

        if (is_array($fields)) $new = $page->get(implode("|", $fields));
        else $new = $page->get((string)$fields);

        $new = $event->sanitizer->markupToText($new);
        $new = $event->sanitizer->pageNameTranslate($new);
        if ($new and $old != $new) {
          $new = $event->wire->pages->names()->uniquePageName($new, $page);
          $page->name = $new;
        }
        $page->save(['noHooks' => true]);
        $this->message($this->_("Page name updated to $new"));
      }
    });
    $this->addHookAfter("ProcessPageEdit::buildForm", function (HookEvent $event) use ($template, $fields) {
      $field = is_array($fields) ? implode("|", $fields) : $fields;
      $page = $event->object->getPage();
      if ($page->template != $template) return;
      $form = $event->return;
      if ($f = $form->get('_pw_page_name')) {
        $f->prependMarkup = "<style>#wrap_{$f->id} input[type=text] { display: none; }</style>";
        $f->notes = $this->_("Page name will be set automatically from field '$field' on save.");
      }
    });
  }

  /**
   * Set page name from page title
   *
   * Usage:
   * $rm->setPageNameFromTitle("basic-page");
   *
   * Make sure to install Page Path History module!
   *
   * @param mixed $object
   */
  public function setPageNameFromTitle($template)
  {
    return $this->setPageNameFromField($template, 'title');
  }

  /**
   * Wrap fields of a form into a fieldset
   *
   * Usage:
   * $rm->wrapFields($form, ['foo', 'bar'], [
   *   'label' => 'your fieldset label',
   *   'icon' => 'bolt',
   * ]);
   *
   * @return InputfieldFieldset
   */
  public function wrapFields(InputfieldWrapper $form, array $fields, array $fieldset, $placeAfter = null)
  {
    // If we only want to show a single field we exit early
    // as we dont need the wrapper in that case. If you still want to show the
    // wrapper add &wrapper=1 to your url.
    if (!$this->wire->input->get('wrapper')) {
      // check for single field
      if ($this->wire->input->get('field')) return;
      if (!strpos((string)$this->wire->input->get('fields'), ",")) return;
    }

    $_fields = [];
    $last = false;
    foreach ($fields as $k => $v) {
      $noLast = false;
      $field = $v;
      $fieldData = null;
      if (is_string($k)) {
        $field = $k;
        $fieldData = $v;
      }

      if (is_array($field)) {
        $form->add($field);
        $field = $form->children()->last();
        $noLast = true;
      }

      if (!$field instanceof Inputfield) $field = $form->get((string)$field);
      if (!$field) continue;
      if ($fieldData) $field->setArray($fieldData);
      if ($field instanceof Inputfield) {
        $_fields[] = $field;

        // we update the "last" variable to be the current field
        // we do not use runtime fields (applied via array syntax)
        // this ensures that the wrapper is at the same position where
        // the field of the form was
        if (!$noLast) $last = $field;
      }
    }

    // no fields, no render
    // this can be the case in modal windows when the page editor is called
    // with a ?field or ?fields get parameter to only render specific fields
    if (!count($_fields)) return;

    /** @var InputfieldFieldset $fs */
    $fs = $this->wire('modules')->get('InputfieldFieldset');
    foreach ($fieldset as $k => $v) $fs->$k = $v;
    if ($placeAfter) {
      if (!$placeAfter instanceof Inputfield) $placeAfter = $form->get((string)$placeAfter);
      $form->insertAfter($fs, $placeAfter);
    } elseif ($last) $form->insertAfter($fs, $last);
    else $form->add($fs);

    // now remove fields from the form and add them to the fieldset
    foreach ($_fields as $f) {
      // if the field is a runtime only field we add a temporary name
      // otherwise the remove causes an endless loop
      if (!$f->name) $f->name = uniqid();
      $form->remove($f);
      $fs->add($f);
    }

    return $fs;
  }

  /** ########## end tools ########## */

  /**
   * Add field to template
   *
   * @param Field|string $field
   * @param Template|string $template
   * @return void
   */
  public function addFieldToTemplate($field, $template, $afterfield = null, $beforefield = null)
  {
    $field = $this->getField($field);
    if (!$field) return; // logging is done in getField()
    $template = $this->getTemplate($template);
    if (!$template) return; // logging is done in getField()
    if (!$afterfield and !$beforefield) {
      if ($template->fields->has($field)) return;
    }

    $afterfield = $this->getField($afterfield);
    $beforefield = $this->getField($beforefield);
    $fg = $template->fieldgroup;
    /** @var Fieldgroup $fg */

    if ($afterfield) $fg->insertAfter($field, $afterfield);
    elseif ($beforefield) $fg->insertBefore($field, $beforefield);
    else $fg->add($field);

    // add end field for fieldsets
    if (
      $field->type instanceof FieldtypeFieldsetOpen
      and !$field->type instanceof FieldtypeFieldsetClose
    ) {
      $closer = $field->type->getFieldsetCloseField($field, false);
      $this->addFieldToTemplate($closer, $template, $field);
    }

    // TODO fix this!
    // quickfix to prevent integrity constraint errors in backend
    try {
      $fg->save();
    } catch (\Throwable $th) {
      $this->log($th->getMessage());
    }
  }

  /**
   * Add fields to template.
   *
   * Simple:
   * $rm->addFieldsToTemplate(['field1', 'field2'], 'yourtemplate');
   *
   * Add fields at special positions:
   * $rm->addFieldsToTemplate([
   *   'field1',
   *   'field4' => 'field3', // this will add field4 after field3
   * ], 'yourtemplate');
   *
   * @param array $fields
   * @param string $template
   * @param bool $sortFields
   * @return void
   */
  public function addFieldsToTemplate($fields, $template, $sortFields = false)
  {
    foreach ($fields as $k => $v) {
      // if the key is an integer, it's a simple field
      if (is_int($k)) $this->addFieldToTemplate((string)$v, $template);
      else $this->addFieldToTemplate((string)$k, $template, $v);
    }
    if ($sortFields) $this->setFieldOrder($fields, $template);
  }

  /**
   * Add a new language or return existing
   *
   * Also installs language support if missing.
   *
   * @param string $name Name of the language
   * @param string $title Optional title of the language
   * @return Language Language that was created
   */
  public function addLanguage(string $name, string $title = null)
  {
    // Make sure Language Support is installed
    $languages = $this->addLanguageSupport();
    if (!$languages) return $this->log("Failed installing LanguageSupport");

    $lang = $this->getLanguage($name);
    if (!$lang->id) {
      $lang = $languages->add($name);
      $languages->reloadLanguages();
    }
    if ($title) $lang->setAndSave('title', $title);
    return $lang;
  }

  /**
   * Install the languagesupport module
   * @return Languages
   */
  public function addLanguageSupport()
  {
    if (!$this->modules->isInstalled("LanguageSupport")) {
      $this->wire->pages->setOutputFormatting(false);
      $ls = $this->installModule("LanguageSupport", ['force' => true]);
      if (!$this->wire->languages) $ls->init();
    }
    return $this->wire->languages;
  }

  /**
   * Add RockFrontend livereloading to the backend
   *
   * This is only added on some pages to prevent reloads from causing issues
   * @return void
   */
  protected function addLivereload()
  {
    if (!$this->wire->modules->isInstalled('RockFrontend')) return;
    if (!$this->wire->config->livereload) return;

    $url = $this->wire->config->urls('RockFrontend');
    $path = $this->wire->config->paths('RockFrontend');
    $m = filemtime($path . "livereload.js");
    $this->wire->config->scripts->add($url . "livereload.js?m=$m");
  }

  /**
   * Add a permission to given role
   *
   * @param string|int $permission
   * @param string|int $role
   * @return boolean
   */
  public function addPermissionToRole($permission, $role)
  {
    $role = $this->getRole($role);
    if (!$role) return $this->log("Role $role not found");
    $role->of(false);
    $role->addPermission($permission);
    return $role->save();
  }

  /**
   * Add role to user
   *
   * @param string $role
   * @param User|string $user
   * @return void
   */
  public function addRoleToUser($role, $user)
  {
    $role = $this->getRole($role);
    $user = $this->getUser($user);
    $msg = "Cannot add role to user";
    if (!$role) return $this->log("$msg - role not found");
    if (!$user) return $this->log("$msg - user not found");
    $user->of(false);
    $user->addRole($role);
    $user->save();
  }

  /**
   * Add the possibility to add success messages on inputfields
   */
  private function addSuccessMessageFeature()
  {
    // setup and load the session variable that stores success messages
    $this->fieldSuccessMessages = $this->wire(new WireData());
    $this->fieldSuccessMessages->setArray(
      $this->wire->session->rmFieldSuccessMessages ?: []
    );

    // add hook that renders success messages on the inputfields
    // rendering the message will also remove the message from the storage
    $this->addHookBefore("Inputfield::render", function ($event) {
      $field = $event->object;
      $messages = $this->fieldSuccessMessages;
      foreach ($messages as $name => $msg) {
        if ($field->name !== $name) continue;
        $field->prependMarkup .= "<div class='uk-alert-success' uk-alert>
          <a class='uk-alert-close' uk-close></a>
          $msg
        </div>";
        $messages->remove($name);
        $this->wire->session->rmFieldSuccessMessages = $messages->getArray();
      }
    });
  }

  /**
   * Add given permission to given template for given role
   *
   * Example for single template:
   * $rm->addTemplateAccess("my-template", "my-role", "edit");
   *
   * Example for multiple templates/roles/permissions:
   * $rm->addTemplateAccess([
   *   'home',
   *   'basic-page',
   * ],
   * [
   *   'admin',
   *   'author',
   * ],
   * [
   *   'add',
   *   'edit',
   * ]);
   *
   * @param mixed string|array $templates template name or array of names
   * @param mixed string|array $roles role name or array of names
   * @param mixed string|array $accs permission name or array of names
   * @return void
   */
  public function addTemplateAccess($templates, $roles, $accs)
  {
    if (!is_array($templates)) $templates = [$templates];
    if (!is_array($roles)) $roles = [$roles];
    if (!is_array($accs)) $accs = [$accs];
    foreach ($roles as $role) {
      if (!$role = $this->getRole($role)) continue;
      foreach ($templates as $tpl) {
        $tpl = $this->getTemplate($tpl);
        if (!$tpl) continue; // log is done above
        foreach ($accs as $acc) {
          $tpl->addRole($role, $acc);
        }
        $tpl->save();
      }
    }
  }

  /**
   * Register autoloader for all classes in given folder
   * This will NOT trigger init() or ready()
   * You can also use $rm->initClasses() with setting autoload=true
   */
  public function autoload($path, $namespace)
  {
    $path = Paths::normalizeSeparators($path);
    spl_autoload_register(function ($class) use ($path, $namespace) {
      if (strpos($class, "$namespace\\") !== 0) return;
      $name = substr($class, strlen($namespace) + 1);
      $file = "$path/$name.php";
      if (is_file($file)) require_once($file);
    });
  }

  /**
   * Get basename of file or object
   * @return string
   */
  public function basename($file)
  {
    return basename($this->filePath($file));
  }

  /**
   * Add deployment info to backend pages
   */
  public function changeFooter()
  {
    if ($this->wire->page->template != 'admin') return;
    $str = $this->wire->config->httpHost;
    $time = date("Y-m-d H:i:s", filemtime($this->wire->config->paths->root));
    if ($this->wire->user->isSuperuser()) {
      $dir = $this->wire->config->paths->root;
      $str = "<span title='$dir @ $time' uk-tooltip>$str</span>";
    }
    $this->wire->addHookAfter('AdminThemeUikit::renderFile', function ($event) use ($str) {
      $file = $event->arguments(0); // full path/file being rendered
      if (basename($file) !== '_footer.php') return;
      $event->return = str_replace("ProcessWire", $str, $event->return);
    });
  }

  /**
   * Create a field of the given type
   *
   * If run multiple times it will only update field data.
   *
   * Usage:
   * $rm->createField('myfield');
   *
   * $rm->createField('myfield', 'text', [
   *   'label' => 'My great field',
   * ]);
   *
   * Alternate array syntax:
   * $rm->createField('myfield', [
   *   'type' => 'text',
   *   'label' => 'My field label',
   * ]);
   *
   * @param string $name
   * @param string|array $type|$options
   * @param array $options
   * @return Field|false
   */
  public function createField($name, $type = 'text', $options = [])
  {
    if (is_array($type)) {
      $options = $type;
      if (!array_key_exists('type', $options)) $options['type'] = 'text';
      $type = $options['type'];
    }
    $field = $this->getField($name, true);

    // field does not exist
    if (!$field) {
      // get type
      $type = $this->getFieldtype($type);
      if (!$type) return; // logging above

      // create the new field
      $_name = $this->wire->sanitizer->fieldName($name);
      if ($_name !== $name) throw new WireException("Invalid fieldname ($name)!");
      $field = $this->wire(new Field());
      $field->type = $type;
      $field->name = $_name;
      $field->label = $_name; // set label (mandatory since ~3.0.172)
      $field->save();

      // create end field for fieldsets
      if ($field->type instanceof FieldtypeFieldsetOpen) {
        $field->type->getFieldsetCloseField($field, true);
      }

      // this will auto-generate the repeater template
      if ($field->type instanceof FieldtypeRepeater) {
        $field->type->getRepeaterTemplate($field);
      }
    }

    // set options
    $options = array_merge($options, ['type' => $type]);
    $field = $this->setFieldData($field, $options);

    return $field;
  }

  /**
   * Create fields from array
   *
   * Usage:
   * $rm->createFields([
   *   'field1' => [...],
   *   'field2' => [...],
   * ]);
   */
  public function createFields($fields): void
  {
    foreach ($fields as $name => $data) {
      if (is_int($name)) {
        $name = $data;
        $data = [];
      }
      $this->createField($name, $data);
    }
  }

  private function createNeededFolders()
  {
    $dir = $this->wire->config->paths->assets . "sessions";
    if (!is_dir($dir)) $this->wire->files->mkdir($dir);
  }

  /**
   * Create a new Page
   *
   * If the page exists it will return the existing page.
   * Note that all available languages will be set active by default!
   *
   * If you need to set a multilang title use
   * $rm->setFieldLanguageValue($page, "title", [
   *   'default'=>'foo',
   *   'german'=>'bar',
   * ]);
   *
   * @param string $title
   * @param string $name
   * @param Template|string $template
   * @param Page|string $parent
   * @param array $status
   * @param array $data
   * @return Page
   */
  public function createPage(string $title, $name = null, $template, $parent, array $status = [], array $data = [])
  {
    // create pagename from page title if it is not set
    if (!$name) $name = $this->sanitizer->pageNameTranslate($title);

    $log = "Parent $parent not found";
    $parent = $this->getPage($parent);
    if (!$parent->id) return $this->log($log);

    // get page if it exists
    $page = $this->getPage([
      'name' => $name,
      'template' => $template,
      'parent' => $parent,
    ], true);

    if ($page and $page->id) {
      $page->status($status);
      $page->setAndSave($data);
      return $page;
    }

    // create a new page
    $p = $this->wire(new Page());
    $p->template = $template;
    $p->title = $title;
    $p->name = $name;
    $p->parent = $parent;
    $p->status($status);
    $p->setAndSave($data);

    // enable all languages for this page
    $this->enableAllLanguagesForPage($p);

    return $p;
  }

  /**
   * Create permission with given name
   *
   * @param string $name
   * @param string $description
   * @return Permission
   */
  public function createPermission($name, $description = null)
  {
    if (!$perm = $this->getPermission($name)) {
      $perm = $this->wire->permissions->add($name);
      $this->log("Created permission $name");
    }
    if (!$description) $description = $name;
    $perm->setAndSave('title', $description);
    return $perm;
  }

  /**
   * Create role with given name
   *
   * Provided permissions will be added to the role. If you remove permissions
   * they will not be removed unless you explicitly remove them via
   * $rm->removePermissionFromRole()
   *
   * If you change permissions later (if the role was already created)
   * permissions will be updated.
   *
   * @param string $name
   * @param array $permissions
   * @return Role|null
   */
  public function createRole($name, $permissions = [])
  {
    if (!$name) return $this->log("Define a name for the role!");

    $role = $this->getRole($name, true);
    if (!$role) $role = $this->roles->add($name);
    foreach ($permissions as $permission) {
      $this->addPermissionToRole($permission, $role);
    }

    return $role;
  }

  /**
   * Helper to create webmaster role
   *
   * You can provide permissions that will be added to the role.
   * By default it will use the default permissions and merge provided ones.
   *
   * If you change permissions later (if the role was already created)
   * permissions will be updated.
   *
   * @return Role
   */
  public function createRoleWebmaster($permissions = [], $name = 'webmaster')
  {
    $permissions = array_merge([
      'page-edit',
      'page-edit-front',
      'page-delete',
      'page-move',
      'page-sort',
      'rockfrontend-alfred',
    ], $permissions ?: []);
    return $this->createRole($name, $permissions);
  }

  /**
   * Create a new ProcessWire Template
   *
   * Usage:
   * $rm->createTemplate('foo', [
   *   'fields' => ['foo', 'bar'],
   * ]);
   *
   * @param string $name
   * @param bool|array $data
   * @param bool $migrate
   * @return Template
   */
  public function createTemplate($name, $data = true, $migrate = true)
  {
    // quietly get the template
    // it is quiet to prevent "template xx not found" logs
    $t = $this->getTemplate($name, true);
    if (!$t) {
      // create new fieldgroup
      $fg = $this->wire(new Fieldgroup());
      $fg->name = $name;
      $fg->save();

      // create new template
      $t = $this->wire(new Template());
      $t->name = $name;
      $t->fieldgroup = $fg;
      $t->save();

      if ($migrate) {
        // trigger migrate() of that new template
        $p = $this->wire->pages->newPage(['template' => $t]);
        if (method_exists($p, "migrate")) $p->migrate();
      }
    }

    // handle different types of second parameter
    if (is_bool($data)) {
      // add title field to this template if second param = TRUE
      if ($data) $this->addFieldToTemplate('title', $t);
    } elseif (is_string($data)) {
      // second param is a string
      // eg "\MyModule\MyPageClass"
      $this->setTemplateData($t, ['pageClass' => $data]);
    } elseif (is_array($data)) {
      // second param is an array
      // that means we set the template data from array syntax
      $this->setTemplateData($t, $data);
    }

    return $t;
  }

  /**
   * Create or return a PW user
   *
   * This will use a random password for the user
   *
   * Usage:
   * $rm->createUser('demo', [
   *   'roles' => ['webmaster'],
   * ]);
   *
   * @param string $username
   * @param array $data
   * @return User|false
   */
  public function createUser($username, $data = [])
  {
    $user = $this->getUser($username, true);
    if (!$user) return false;
    if (!$user->id) {
      $user = $this->wire->users->add($username);

      // setup password
      $rand = $this->wire(new WireRandom());
      /** @var WireRandom $rand */
      $password = $rand->alphanumeric(null, [
        'minLength' => 10,
        'maxLength' => 20,
      ]);
      $data['password'] = $password;
    }
    $this->setUserData($user, $data);
    return $user;
  }

  /**
   * Create view file for template (if it does not exist already)
   * @return void
   */
  public function createViewFile($template, $content = "\n")
  {
    if ($template instanceof RockPageBuilderBlock) {
      $template = $template->getTplName();
    }
    $template = $this->getTemplate($template);
    if (!$template) return;
    $file = $this->wire->config->paths->templates . $template->name . ".php";
    if (is_file($content)) $content = file_get_contents($content);
    if (!is_file($file)) $this->wire->files->filePutContents($file, $content);
  }

  /**
   * Delete the given field
   * @param mixed $name
   * @param bool $quiet
   * @return void
   */
  public function deleteField($name, $quiet = false)
  {
    $field = $this->getField($name, $quiet);
    if (!$field) return; // logging in getField()

    // delete _END field for fieldsets first
    if ($field->type instanceof FieldtypeFieldsetOpen) {
      $closer = $field->type->getFieldsetCloseField($field, false);
      $this->deleteField($closer, $quiet);
    }

    // make sure we can delete the field by removing all flags
    $field->flags = Field::flagSystemOverride;
    $field->flags = 0;

    // remove the field from all fieldgroups
    foreach ($this->fieldgroups as $fieldgroup) {
      /** @var Fieldgroup $fieldgroup */
      $fieldgroup->remove($field);
      $fieldgroup->save();
    }

    return $this->fields->delete($field);
  }

  /**
   * Delete given fields
   *
   * If parameter is a string we use it as selector for $fields->find()
   *
   * Usage:
   * $rm->deleteFields("tags=MyModule");
   *
   * @param array|string $fields
   * @return void
   */
  public function deleteFields($fields, $quiet = false)
  {
    if (is_string($fields)) $fields = $this->wire->fields->find($fields);
    foreach ($fields as $field) $this->deleteField($field, $quiet);
  }

  /**
   * Deletes a language
   * @param mixed $language
   * @return void
   */
  public function deleteLanguage($language, $quiet = false)
  {
    if (!$lang = $this->getLanguage($language, $quiet)) return;
    $this->wire->languages->delete($lang);
  }

  /**
   * Delete module
   * This deletes the module files and then removes the entry in the modules
   * table. Removing the module via uninstall() did cause an endless loop.
   * @param mixed $name
   * @return void
   */
  public function deleteModule($name, $path = null)
  {
    $name = (string)$name;
    if ($this->wire->modules->isInstalled($name)) $this->uninstallModule($name);
    if (!$path) $path = $this->wire->config->paths->siteModules . $name;
    if (is_dir($path)) $this->wire->files->rmdir($path, true);
    $this->wire->database->exec("DELETE FROM modules WHERE class = '$name'");
  }

  /**
   * Delete the given page including all children.
   *
   * @param Page|string $page
   * @return void
   */
  public function deletePage($page, $quiet = false)
  {
    if (!$page = $this->getPage($page, $quiet)) return;

    // temporarily disable filesOnDemand feature
    // this prevents PW from downloading files that are deleted from a local dev
    // system but only exist on the live system
    $ondemand = $this->wire->config->filesOnDemand;
    $this->wire->config->filesOnDemand = false;

    // make sure we can delete the page and delete it
    // we also need to make sure that all descendants of this page are deletable
    // todo: make this recursive?
    $all = $this->wire(new PageArray());
    $all->add($page);
    $all->add($this->wire->pages->find("has_parent=$page, include=all"));
    foreach ($all as $p) {
      $p->addStatus(Page::statusSystemOverride);
      $p->status = 1;
      $p->save();
    }
    $this->wire->pages->delete($page, true);

    $this->wire->config->filesOnDemand = $ondemand;
  }

  /**
   * Delete the given permission
   *
   * @param Permission|string $permission
   * @return void
   */
  public function deletePermission($permission, $quiet = false)
  {
    if (!$permission = $this->getPermission($permission, $quiet)) return;
    $this->permissions->delete($permission);
  }

  /**
   * Delete the given role
   * @param Role|string $role
   * @param bool $quiet
   * @return void
   */
  public function deleteRole($role, $quiet = false)
  {
    if (!$role = $this->getRole($role, $quiet)) return;
    $this->roles->delete($role);
  }

  /**
   * Delete a ProcessWire Template
   * @param mixed $tpl
   * @param bool $quiet
   * @return void
   */
  public function deleteTemplate($tpl, $quiet = false)
  {
    $template = $this->getTemplate($tpl, $quiet);
    if (!$template) return;

    // remove all pages having this template
    foreach ($this->pages->find("template=$template, include=all") as $p) {
      $this->deletePage($p);
    }

    // make sure we can delete the template by removing all flags
    $template->flags = Template::flagSystemOverride;
    $template->flags = 0;

    // delete the template
    $this->templates->delete($template);

    // delete the fieldgroup
    $fg = $this->fieldgroups->get((string)$tpl);
    if ($fg) $this->fieldgroups->delete($fg);
  }

  /**
   * Delete templates
   *
   * Usage
   * $rm->deleteTemplates("tags=YourModule");
   *
   * @param string $selector
   */
  public function deleteTemplates($selector, $quiet = false)
  {
    $templates = $this->wire->templates->find($selector);
    foreach ($templates as $tpl) $this->deleteTemplate($tpl, $quiet);
  }

  /**
   * Delete a PW user
   *
   * @param string $username
   * @return void
   */
  public function deleteUser($username, $quiet = false)
  {
    if (!$user = $this->getUser($username, $quiet)) return;
    $this->wire->users->delete($user);
  }

  /**
   * Disable module
   *
   * This is a quickfix for modules that are not uninstallable by
   * uninstallModule() - I don't know why this does not work for some modules...
   * if you do please let me know!
   *
   * @param string|Module $name
   * @return void
   */
  public function disableModule($name)
  {
    $this->wire->modules->setFlag((string)$name, Modules::flagsDisabled, true);
  }

  protected function doMigrate($file)
  {
    if ($this->migrateAll) return true;
    if ($file instanceof RockMatrixBlock) $file = $file->filePath();
    if ($file instanceof RockPageBuilderBlock) {
      $block = $file;
      $file = $block->filePath();
      // block has not been created so we make sure to migrate it
      if (!$block->template) return true;
    }
    $watchFile = $file;
    if (is_string($watchFile)) $watchFile = $this->watchlist->get("path=$file");
    if (!$watchFile instanceof WatchFile) return false;
    if ($watchFile->changed) return true;
    if ($watchFile->force) return true;
    return false;
  }

  /**
   * Download module from url
   *
   * @param string $url
   * @return mixed bool|string Returns destinationDir on success, false on failure.
   */
  public function downloadModule($url)
  {
    if (!class_exists('ProcessWire\ProcessModuleInstall')) {
      require_once($this->config->paths->modules . "Process/ProcessModule/ProcessModuleInstall.php");
    }
    /** @var ProcessModuleInstall $installer */
    $installer = $this->wire(new ProcessModuleInstall());
    $downloaded = $installer->downloadModule($url);
    if ($downloaded !== false) return $downloaded;
    $this->log("Tried to download module from $url but failed");
    return false;
  }

  /**
   * Enable all languages for given page
   *
   * @param mixed $page
   * @return void
   */
  public function enableAllLanguagesForPage($page)
  {
    if (!$page) return;
    $page = $this->getPage($page);
    foreach ($this->languages ?: [] as $lang) $page->set("status$lang", 1);
    $page->save();
  }

  /**
   * Show a success message on an inputfield
   */
  public function fieldSuccess($field, $msg)
  {
    if ($field instanceof Field) {
      if (!$field->name) throw new WireException("Field must have a name");
      $field = $field->name;
    }
    if (!is_string($field)) throw new WireException("Must be string");
    $messages = $this->rm()->fieldSuccessMessages->set($field, $msg);
    $this->wire->session->rmFieldSuccessMessages = $messages->getArray();
  }

  /**
   * Find migration file (tries all extensions)
   * @return string|false
   */
  public function file($path)
  {
    $path = Paths::normalizeSeparators($path);
    if (is_file($path)) return $path;
    foreach (['yaml', 'json', 'php'] as $ext) {
      if (is_file($f = "$path.$ext")) return $f;
    }
    return false;
  }

  /**
   * Get IDE edit link for file
   * @return string
   */
  public function fileEditLink($file)
  {
    $file = $this->filePath($file);
    $tracy = $this->wire->config->tracy;
    if (is_array($tracy) and array_key_exists('localRootPath', $tracy))
      $root = $tracy['localRootPath'];
    else $root = $this->wire->config->paths->root;
    return "vscode://file/"
      . str_replace($this->wire->config->paths->root, $root, $file);
  }

  public function filemtime($file)
  {
    $path = $this->getAbsolutePath($file);
    if (is_file($path)) return filemtime($path);
    return 0;
  }

  /**
   * Get filepath of file or object
   * @return string
   */
  public function filePath($file, $relative = false)
  {
    if (is_object($file)) {
      $reflector = new \ReflectionClass($file);
      $file = $reflector->getFileName();
    }
    if ($relative) $file = str_replace(
      $this->wire->config->paths->root,
      $this->wire->config->urls->root,
      $file
    );
    return $file;
  }

  /**
   * Keep two files in sync
   * See init() method of an example use
   * @return void
   */
  public function fileSync($file1, $file2, $options = [])
  {
    $file1 = $this->getAbsolutePath($file1);
    $file2 = $this->getAbsolutePath($file2);

    // get file timestamps
    $m1 = $this->filemtime($file1);
    $m2 = $this->filemtime($file2);
    // bd($m1, 'm1');
    // bd($m2, 'm2');

    $this->wire->files->mkdir(dirname($file1), true);
    $this->wire->files->mkdir(dirname($file2), true);

    if ($m1 > $m2) {
      $this->wire->files->copy($file1, $file2);
      touch($file1);
    } elseif ($m2 > $m1) {
      $this->wire->files->copy($file2, $file1);
      touch($file2);
    }
  }

  /**
   * DEPRECATED
   */
  public function fireOnRefresh($module, $method = null, $priority = [])
  {
    $trace = debug_backtrace()[0];
    $trace = $trace['file'] . ":" . $trace['line'];
    $this->warning("fireOnRefresh is DEPRECATED and does not work any more!
      RockMigrations will migrate all watched files on Modules::refresh automatically. $trace");
    return;
  }

  /**
   * Run migrations during development on every request
   * This is handy to code rapidly and get instant output via RockFrontend's
   * livereload feature!
   *
   * To use this feature add this to your /site/init.php:
   * $config->forceMigrate = true;
   */
  private function forceMigrate()
  {
    if (!$this->wire->config->forceMigrate) return;
    if ($this->wire->config->ajax) return;
    $this->migrateAll = true;
    $this->run();
  }

  /**
   * Get absolute path
   * If the provided file is not an absolute path this will simply prefix
   * the provided path with the pw root path
   * @param string $file
   * @return string
   */
  public function getAbsolutePath($file)
  {
    $path = Paths::normalizeSeparators($file);
    $rootPath = $this->wire->config->paths->root;
    if (strpos($path, $rootPath) === 0) return $path;
    return $rootPath . ltrim($file, "/");
  }

  /**
   * Get changed watchfiles
   * @return array
   */
  public function getChangedFiles()
  {
    $changed = [];
    foreach ($this->watchlist as $file) {
      // remove the hash from file path
      // hashes are needed for multiple callbacks living on the same file
      $path = explode("::", $file->path)[0];
      $m = filemtime($path);
      if ($m > $this->lastrun) {
        $changed[] = $file->path;
        $file->changed = true;
      }
    }
    return $changed;
  }

  /**
   * Get code (export data)
   *
   * raw = 1 --> get string
   * raw = 2 --> get PHP array
   *
   * @return string
   */
  public function getCode($item, $raw = false)
  {
    if ($item instanceof Field) {
      ob_start();
      $data = $item->getExportData();
      unset($data['id']);
      unset($data['name']);
      unset($data['rockmigrations']);

      // we have a different syntax for options of an options field
      if ($item->type instanceof FieldtypeRepeater) {
        unset($data['repeaterFields']);
        unset($data['fieldContexts']);
      } elseif ($item->type instanceof FieldtypeOptions) {
        $options = [];
        foreach ($item->type->manager->getOptions($item) as $opt) {
          $options[$opt->id] =
            ($opt->value ? $opt->value . "|" : "") .
            "{$opt->title}";
        }
        unset($data['export_options']);
        $data['options'] = $options;

        // for multilang fields we also set the translated labels
        if ($this->wire->languages) {
          $arr = [];
          foreach ($this->wire->languages as $lang) {
            $options = [];
            foreach ($item->type->manager->getOptions($item) as $opt) {
              $options[$opt->id] =
                ($opt->value ? $opt->value . "|" : "") .
                $opt->get("title$lang|title");
            }
            $arr[$lang->name] = $options;
          }
          $data['optionsLang'] = $arr;
          unset($data['options']);
        }
      }
    } elseif ($item instanceof Template) {
      $data = $item->getExportData();
      unset($data['id']);
      unset($data['name']);
      unset($data['rockmigrations']);

      // use custom fields syntax
      try {
        $fields = [];
        foreach ($data['fieldgroupFields'] as $k => $field) {
          $context = $data['fieldgroupContexts'][$field];
          $fields[$field] = $context;
        }
        $data = ['fields' => $fields] + $data;
      } catch (\Throwable $th) {
        $this->log($th->getMessage());
      }
      unset($data['fieldgroupFields']);
      unset($data['fieldgroupContexts']);
    } elseif ($item instanceof Module) {
      $data = $this->wire->modules->getConfig($item->className());
    }

    if (!isset($data) or !is_array($data)) return false;

    if (array_key_exists('_rockmigrations_log', $data)) {
      unset($data['_rockmigrations_log']);
    }

    // if code was requested as array return it now
    if ($raw == 2) return $data;

    $code = $this->varexport($data);
    if ($raw) return $code;
    return "'{$item->name}' => $code";
  }

  /**
   * Convert an array into a WireData config object
   * @return WireData
   */
  public function getConfigObject(array $config)
  {
    // this ensures that $config->fields is an empty array rather than
    // a processwire fields object (proxied from the wire object)
    $conf = $this->wire(new WireData());
    /** @var WireData $conf */
    $conf->setArray([
      "fields" => [],
      "templates" => [],
      "pages" => [],
      "roles" => [],
    ]);
    $conf->setArray($config);
    return $conf;
  }

  /**
   * Get field by name
   * @param Field|string $name
   * @param bool $quiet
   * @return mixed
   */
  public function getField($name, $quiet = false)
  {
    if (!$name) return false; // for addfieldtotemplate
    $field = $this->fields->get((string)$name);
    if ($field) return $field;
    if (!$quiet) $this->log("Field $name not found");
    return false;
  }

  /**
   * Get a new deployment instance (for debugging/testing)
   */
  public function getDeployment($argv = null, $whitelistedPath = null): Deployment
  {
    require_once __DIR__ . "/Deployment.php";
    return new Deployment($argv, $whitelistedPath);
  }

  /**
   * Get fieldtype instance
   *
   * This will also try to install the Fieldtype if it is not installed.
   *
   * Usage:
   * $rm->getFieldtype("page"); // FieldtypePage
   *
   * Note that this returns the Fieldtype even if the shortname module exists:
   * This returns FieldtypeRockMatrix even though RockMatrix is a module!
   * $rm->getFieldtype("RockMatrix");
   *
   * @param mixed $type
   * @return Fieldtype|false
   */
  public function getFieldtype($type)
  {
    if ($type instanceof Fieldtype) return $type;
    $modules = $this->wire->modules;
    $name = (string)$type;

    // first we try to get the module by name
    // $rm->getFieldtype('page') will request the page module!
    // we make sure not to auto-install non-fieldtype modules!
    if ($modules->isInstalled($name)) {
      $module = $modules->get($name);
      if ($module instanceof Fieldtype) return $module;
    }

    // prepend Fieldtype (page --> FieldtypePage)
    // now we try to get the module and install it
    $fname = $name;
    if (strpos($fname, "Fieldtype") !== 0) $fname = "Fieldtype" . ucfirst($fname);
    if (!$modules->isInstalled($fname)) @$modules->install($fname);
    $module = $modules->get($fname);
    if ($module) return $module;

    if (is_array($type)) $type = print_r($type, 1);
    $this->log("No fieldtype found for $type (also tried $fname)");
    return false;
  }

  /**
   * Get language
   * Returns FALSE if language is not found
   * @return Language|false
   */
  public function getLanguage($data, $quiet = false)
  {
    $lang = $this->wire->languages->get((string)$data);
    if ($lang and $lang->id) return $lang;
    if (!$quiet) $this->log("Language $data not found");
    return false;
  }

  /**
   * Get language zip url
   * @param string $code 2-Letter Language Code
   * @return string
   */
  public function getLanguageZip($code)
  {
    if (strtoupper($code) == 'DE') return "https://github.com/jmartsch/pw-lang-de/archive/refs/heads/main.zip";
    if (strtoupper($code) == 'FI') return "https://github.com/apeisa/Finnish-ProcessWire/archive/refs/heads/master.zip";
    return $code;
  }

  /**
   * Get page
   * Returns FALSE if page is not found
   * @return Page|false
   */
  public function getPage($data, $quiet = false)
  {
    if ($data instanceof Page) return $data;
    $page = $this->wire->pages->get($data);
    if ($page->id) return $page;
    if (is_array($data)) $data = "#array#";
    if (!$quiet) $this->log("Page $data not found");
    return false;
  }

  /**
   * Get permission
   * Returns FALSE if permission does not exist
   * @param mixed $data
   * @param bool $quiet
   * @return Permission|false
   */
  public function getPermission($data, $quiet = false)
  {
    $permission = $this->permissions->get((string)$data);
    if ($permission and $permission->id) return $permission;
    if (!$quiet) $this->log("Permission $data not found");
    return false;
  }

  /**
   * Get role
   * Returns false if the role does not exist
   * @param Role|string $name
   * @param bool $quiet
   * @return mixed
   */
  public function getRole($name, $quiet = false)
  {
    if (!$name) return false;
    $role = $this->wire->roles->get((string)$name);
    if ($role and $role->id) return $role;
    if (!$quiet) $this->log("Role $name not found");
    return false;
  }

  /**
   * Get trace and return last entry from trace that is within the site folder
   * @return string
   */
  public function getTrace($msg = null)
  {
    $self = Paths::normalizeSeparators(__FILE__);
    $trace = debug_backtrace();
    $paths = $this->wire->config->paths;
    $trace = array_filter($trace, function ($item) use ($paths, $self) {
      if (!array_key_exists('file', $item)) return false; // when run from CLI
      $file = $item['file'];
      if ($file === $self) {
        if ($item['function'] == 'getTrace') return false;
      }
      if ($file === $paths->templates . "admin.php") return false;
      if ($file === $paths->root . "index.php") return false;
      if (strpos($file, $paths->wire) === 0) return false;
      return true;
    });
    // bd($trace, $msg);

    // return first trace entry that does not come from RockMigrations.module.php
    $first = null;
    foreach ($trace as $k => $v) {
      if (!$first) $first = $v;
      if ($v['file'] !== $self) return $v;
    }
    return $first;
  }

  /**
   * Get trace log for field/template log
   *
   * This log will be shown on fields/templates that are under control of RM
   *
   * @return string
   */
  public function getTraceLog()
  {
    $trace = date("--- Y-m-d H:i:s ---") . "\n";
    // bd(debug_backtrace());
    foreach (debug_backtrace() as $line) {
      if (!array_key_exists('file', $line)) continue;
      if (strpos($line['file'], $this->wire->config->paths->wire) !== false) continue;
      $base = basename($line['file']);
      if ($base == 'index.php') continue;
      if ($base == 'admin.php') continue;
      if ($base == 'RockMigrations.module.php') continue;
      $trace .= "$base::{$line['function']}() - L{$line['line']}\n";
    }
    return $trace;
  }

  /**
   * Get user
   * Returns false if the user does not exist
   * @param User|string $name
   * @param bool $quiet
   * @return mixed
   */
  public function getUser($name, $quiet = false)
  {
    if (!$name) return false;
    $user = $this->wire->users->get((string)$name);
    if ($user and $user->id) return $user;
    if (!$quiet) $this->log("User $name not found");
    return false;
  }

  /**
   * Get template by name
   *
   * Returns FALSE if template is not found
   *
   * @param Template|string $name
   * @return Template|false
   */
  public function getTemplate($name, $quiet = false)
  {
    if ($name instanceof RockPageBuilderBlock) return $name->getTpl();
    if ($name instanceof Page) $name = $name->template;
    $template = $this->templates->get((string)$name);
    if ($template and $template->id) return $template;
    if (!$quiet) $this->log("Template $name not found");
    return false;
  }

  /**
   * DEPRECATED
   *
   * As of v1.0.5 the recommended way of using magic page classes is using
   * the MagicPage trait! See readme about MagicPage
   *
   * Trigger init() method of classes in this folder
   *
   * If autoload is set to TRUE it will attach a class autoloader before
   * triggering the init() method. The autoloader is important so that we do
   * not get any conflicts on the loading order of the classes. This could
   * happen if we just used require() in here because then the loading order
   * would depend on the file names of loaded classes.
   *
   * Example problem:
   * class Bar extends Foo
   * class Foo
   *
   * load order = Bar, then Foo therefore without autoload we'd get an error
   *
   * @return void
   */
  public function initClasses($path, $namespace = "ProcessWire", $autoload = true)
  {
    if ($autoload) $this->autoload($path, $namespace);
    foreach ($this->files->find($path, ['extensions' => ['php']]) as $file) {
      $class = pathinfo($file, PATHINFO_FILENAME);

      // skip files that start with an underscore
      // this is to make it possible to add abstract classes to your folder
      // an example could be /site/classes/_MyPage.php
      if (strpos($class, "_") === 0) continue;

      if ($namespace) $class = "\\$namespace\\$class";
      try {
        $tmp = new $class();
        if (method_exists($tmp, "init")) $tmp->init();
        $this->magic()->addMagicMethods($tmp);
      } catch (\Throwable $th) {
        $this->log($th->getMessage());
      }
    }
  }

  /**
   * Install module
   *
   * If an URL is provided the module will be downloaded before installation.
   * You can provide module settings as 2nd parameter.
   *
   * Usage:
   * $rm->installModule("YourModule");
   *
   * Install from url:
   * $rm->installModule(
   *   "TracyDebugger",
   *   "https://github.com/adrianbj/TracyDebugger/archive/refs/heads/master.zip"
   * );
   *
   * Install with settings:
   * $rm->installModule("YourModule", ['setting1'=>'foo', 'setting2'=>'bar']);
   *
   * Install with settings from url:
   * $rm->installModule("MyModule", ['setting'=>'foo'], "https://...");
   *
   * @param string $name
   * @param array $config
   * @param string|array $url
   * @return Module
   */
  public function installModule($name, $conf = [], $options = [])
  {
    if (is_string($conf)) $options['url'] = $conf;
    if (is_string($options)) $options = ['url' => $options];
    if (!$options) $options = [];

    $opt = $this->wire(new WireData());
    /** @var WireData $opt */
    $opt->setArray([
      'url' => '',
      'conf' => $conf,

      // a setting of true forces the module to be installed even if
      // dependencies are not met
      'force' => false,
    ]);
    $opt->setArray($options);

    // dont return early to make sure that if module settings
    // are changed we apply the new settings at the bottom!!
    $module = $this->wire->modules->get($name);

    if (!$module) {
      // check if module files exist
      $path = $this->wire->config->path($name);
      $pathExists = $path and $this->wire->files->exists($path);
      // download only if an url was provided and module files do not exist yet
      if ($opt->url and !$pathExists) {
        $pathExists = !!$this->downloadModule($opt->url);
      }

      if ($pathExists) {
        // module files are in place -> install the module
        $module = $this->modules->install($name, ['force' => $opt->force]);
        if ($module) $this->log("Installed module $name");
        else $this->log("Tried to install module $name but failed");
      }
    }
    if ($module and is_array($opt->conf) and count($opt->conf)) {
      $this->setModuleConfig($module, $opt->conf);
    }
    return $module;
  }

  /**
   * Install a base Site.module.php from stub
   */
  public function installSiteModule()
  {
    $file = $this->wire->config->paths->siteModules . "Site/Site.module.php";
    if (is_file($file)) return;
    $stub = __DIR__ . "/stubs/SiteModule.php";
    $this->wire->files->mkdir(dirname($file));
    $this->wire->files->filePutContents(
      $file,
      $this->wire->files->fileGetContents($stub)
    );
    $this->refresh();
    $this->installModule('Site');
  }

  /**
   * Install system permission(s) with given name
   *
   * Usage:
   * $rm->installSystemPermissions('page-hide');
   * $rm->installSystemPermissions([
   *   'page-hide',
   *   'page-publish',
   * );
   *
   * available predefined system permissions:
   * 'page-hide'
   * 'page-publish'
   * 'page-edit-created'
   * 'page-edit-trash-created'
   * 'page-edit-images'
   * 'page-rename'
   * 'user-admin-all'
   * 'user-view-all'
   * 'user-view-self'
   *
   * @param mixed string|array $names system permission name or array of names
   * @return Permission
   */
  public function installSystemPermissions($names)
  {
    if (!is_array($names)) $installPermissions = [$names];
    $optionalPermissions = $this->wire->permissions->getOptionalPermissions();
    $user = $this->wire->user;
    $languages = $this->wire->languages;
    $userLanguage = null;
    if ($languages) {
      $userLanguage = $user->language;
      $user->language = $languages->getDefault();
    }
    foreach ($installPermissions as $name) {
      if (!$permission = $this->getPermission($name)) { // if permission not installed yet
        if (!isset($optionalPermissions[$name])) continue; // permission is not system permission
        $permission = $this->wire->permissions->add($name);
        if (!$permission->id) continue;
        $permission->title = $optionalPermissions[$name];
        if ($languages && $permission->title instanceof LanguagesValueInterface) {
          // if the permission titles have been translated, ensure that the translation goes in for each language
          foreach ($languages as $language) {
            if ($language->isDefault()) continue;
            $a = $this->wire->permissions->getOptionalPermissions();
            if ($a[$name] == $optionalPermissions[$name]) continue;
            $permission->title->setLanguageValue($language, $a[$name]);
          }
        }
        $permission->save();
        // $this->message(sprintf($this->_('Added optional permission: %s'), $name));

        $this->log("Installed system permission $name");
      }
    }
    if ($userLanguage) $user->language = $userLanguage;
    return $permission;
  }

  /**
   * Are we in CLI environment?
   */
  public function isCLI(): bool
  {
    return php_sapi_name() == "cli" or defined('RockMigrationsCLI');
  }

  /**
   * @return bool
   */
  public function isDebug()
  {
    return $this->outputLevel == self::outputLevelDebug;
  }

  /**
   * Check if given string is a valid email
   * This does also support IDN emails
   * See https://github.com/processwire/processwire-issues/issues/1647
   */
  public function isEmail($mail): bool
  {
    $atom = "[-a-z0-9!#$%&'*+/=?^_`{|}~]"; // RFC 5322 unquoted characters in local-part
    $alpha = "a-z\x80-\xFF"; // superset of IDN
    return (bool) preg_match(<<<XX
      (^
        ("([ !#-[\\]-~]*|\\\\[ -~])+"|$atom+(\\.$atom+)*)  # quoted or unquoted
        @
        ([0-9$alpha]([-0-9$alpha]{0,61}[0-9$alpha])?\\.)+  # domain - RFC 1034
        [$alpha]([-0-9$alpha]{0,17}[$alpha])?              # top domain
      $)Dix
      XX, $mail);
  }

  /**
   * @return bool
   */
  public function isVerbose()
  {
    return $this->outputLevel == self::outputLevelVerbose;
  }

  /**
   * Get or set json data to file
   * @return mixed
   */
  public function json($path, $data = null)
  {
    if ($data === null) return json_decode(file_get_contents($path));
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT));
  }

  /**
   * Get lastmodified timestamp of watchlist
   * @return int
   */
  public function lastmodified()
  {
    $last = 0;
    foreach ($this->watchlist as $file) {
      // remove the hash from file path
      // hashes are needed for multiple callbacks living on the same file
      $path = explode("::", $file->path)[0];
      $m = filemtime($path);
      if ($m > $last) $last = $m;
    }
    return $last;
  }

  /**
   * Load files on demand on local installation
   *
   * Usage: set $config->filesOnDemand = 'your.hostname.com' in your config file
   *
   * Usage with basic authentication:
   * $config->filesOnDemand = 'https://user:password@example.com';
   *
   * Make sure that this setting is only used on your local test config and not
   * on a live system!
   *
   * @return void
   */
  protected function loadFilesOnDemand()
  {
    if (!$this->wire->config->filesOnDemand) return;
    $hook = function (HookEvent $event) {
      $config = $this->wire->config;
      $file = $event->return;
      $pagefile = $event->object;
      if ($pagefile->page->isTrash()) return;

      // this makes it possible to prevent downloading at runtime
      if (!$host = $this->wire->config->filesOnDemand) return;

      // convert url to disk path
      if ($event->method == 'url') {
        $file = $config->paths->root . substr($file, strlen($config->urls->root));
      }

      // load file from remote if it does not exist
      if (!file_exists($file)) {
        $host = rtrim($host, "/");
        $src = "$host/site/assets/files/";
        $url = str_replace($config->paths->files, $src, $file);
        $http = $this->wire(new WireHttp());
        /** @var WireHttp $http */
        try {
          $http->download($url, $file);
        } catch (\Throwable $th) {
          // do not throw exception, show error message instead
          $this->error($th->getMessage());
        }
      }
    };
    $this->addHookAfter("Pagefile::url", $hook);
    $this->addHookAfter("Pagefile::filename", $hook);
  }

  /**
   * Log message
   *
   * Usage:
   * $rm->log("some message");
   *
   * try {
   *   // do something
   * } catch($th) {
   *   $rm->log($th->getMessage(), false);
   * }
   *
   * @param string $msg
   * @param bool $throwException
   * @return void
   */
  public function log($msg, $throwException = true)
  {
    $trace = $this->getTrace($msg);
    $file = $trace['file'];
    $line = $trace['line'];
    $filename = pathinfo($file, PATHINFO_FILENAME);
    $traceStr = "$filename:$line";
    if ($this->isVerbose()) {
      try {
        $url = TracyDebugger::createEditorLink($file, $line, $traceStr);
        $opt = ['url' => $url];
      } catch (\Throwable $th) {
        $opt = [];
      }
      if ($this->wire->config->external) echo "$msg\n";
      $this->wire->log->save("RockMigrations", $msg, $opt);
    } elseif ($this->isDebug()) {
      if ($throwException) throw new WireException("$msg in $traceStr");
    }
  }

  /**
   * Log but throw no exception
   * @param string $msg
   * @return void
   */
  public function logOnly($msg)
  {
    $this->log($msg, false);
  }

  /**
   * Get array value
   * @return mixed
   */
  public function val($arr, $property)
  {
    if (!array_key_exists($property, $arr)) return;
    return $arr[$property];
  }

  /**
   * Get instance of MagicPages module
   */
  public function magic(): MagicPages
  {
    return $this->wire->modules->get('MagicPages');
  }

  /**
   * Mail to superuser
   * @return void
   */
  public function mailToSuperuser($body, $subject = null)
  {
    $su = $this->wire->users->get($this->wire->config->superUserPageID);
    $host = $this->wire->config->httpHost;

    $this->log($body);
    if ($su->email) {
      $mail = new WireMail();
      $mail->to($su->email);
      $mail->subject($subject ?: "Issue on page $host");
      $mail->body($body);
      $mail->send();
      $this->log("Sent mail to superuser: " . $su->email);
    } else {
      $this->log("Superuser has no email in its profile");
    }
  }

  /**
   * Merge fields
   *
   * This makes it possible to keep manually created fields at the same position
   * after a migrate() call happened that does not include the manually created
   * field. Prior to v1.0.9 manually created fields had always been added to the
   * top of the page editor if not explicitly listed in the migrate() call.
   */
  protected function mergeFields(Fieldgroup $old, Fieldgroup $new): Fieldgroup
  {
    // set the correct sort order of fields
    $merged = clone $new;
    $insertAfter = false;
    foreach ($old as $item) {
      $newItem = $new->get("name=$item");
      if ($newItem) $insertAfter = $newItem;
      if ($new->has($item)) continue;

      // add item to merged array
      if ($insertAfter) {
        $merged->insertAfter($item, $insertAfter);
        $insertAfter = $item;
      } else $merged->prepend($item);
    }
    return $merged;
  }

  /**
   * Migrate PW setup based on config array
   *
   * Usage:
   * $rm->migrate([
   *   'fields' => [
   *     'myfield1' => [
   *       'type' => 'text',
   *       'label' => 'My Field One',
   *     ],
   *   ],
   *   'templates' => [
   *     'mytpl' => [
   *       'fields' => [
   *         'title' => [
   *           'label' => 'Page Title',
   *           'required' => false,
   *         ],
   *         'myfield1',
   *       ],
   *     ],
   *   ],
   * ]);
   *
   * Short syntax (for development and testing):
   * $rm->migrate([
   *   // create plain text fields
   *   'fields' => ['foo', 'bar', 'baz'],
   * ]);
   *
   * @return void
   */
  public function migrate($config)
  {
    $config = $this->getConfigObject($config);

    // create fields+templates
    foreach ($config->fields as $name => $data) {
      // if key is an integer and data is a string that means
      // the fields have been defined name-only
      if (is_integer($name) and is_string($data)) {
        $name = $data;
        $data = ['type' => 'text'];
      }

      // if no type is set this means that only field data was set
      // for example to update only label or icon of an existing field
      if (array_key_exists('type', $data)) $this->createField($name, $data['type']);
    }
    foreach ($config->templates as $name => $data) {
      // this check makes it possible to define templates without data
      // that means the defined templates will be created but not changed
      if (is_int($name)) $name = $data;
      $this->createTemplate($name, false);
    }
    foreach ($config->roles as $name => $data) $this->createRole($name);

    // set field+template data after they have been created
    foreach ($config->fields as $name => $data) {
      if (is_array($data)) $this->setFieldData($name, $data);
    }
    foreach ($config->templates as $name => $data) {
      // this check makes it possible to define templates without data
      // that means the defined templates will be created but not changed
      if (!is_int($name)) $this->setTemplateData($name, $data);
    }
    foreach ($config->roles as $role => $data) {
      // set permissions for this role
      if (array_key_exists("permissions", $data)) $this->setRolePermissions($role, $data['permissions']);
      if (array_key_exists("access", $data)) {
        foreach ($data['access'] as $tpl => $access) $this->setTemplateAccess($tpl, $role, $access);
      }
    }

    // setup pages
    foreach ($config->pages as $name => $data) {
      if (isset($data['name'])) {
        $name = $data['name'];
      } elseif (is_int($name)) {
        // no name provided
        $name = uniqid();
      }

      $d = $this->wire(new WireData());
      /** @var WireData $d */
      $d->setArray($data);
      $this->createPage(
        $d->title ?: $name,
        $name,
        $d->template,
        $d->parent,
        $d->status,
        $d->data
      );
    }
  }

  /**
   * Call $module::migrate() on modules::refresh
   * @return void
   */
  public function migrateOnRefresh(Module $module)
  {
    $trace = debug_backtrace()[0];
    $trace = $trace['file'] . ":" . $trace['line'];
    $this->warning("fireOnRefresh is DEPRECATED and does not work any more!
      RockMigrations will migrate all watched files on Modules::refresh automatically. $trace");
    return;
    $this->fireOnRefresh($module);
  }

  /**
   * Migrate all pageclasses in given path
   * Note that every pageclass needs to have the template name defined in
   * the "tpl" constant, eg YourPageClass::tpl = 'your-template-name'
   */
  public function migratePageClasses($path, $namespace = 'ProcessWire'): void
  {
    $options = [
      'extensions' => ['php'],
      'recursive' => 1,
    ];
    $namespace = "\\" . ltrim($namespace, "\\");
    foreach ($this->wire->files->find($path, $options) as $file) {
      require_once $file;
      $name = pathinfo($file, PATHINFO_FILENAME);
      $class = "$namespace\\$name";
      $tmp = $this->wire(new $class());
      if (!$tmp->template) {
        try {
          $templatename = $tmp::tpl;
          $tpl = $this->wire->templates->get($templatename);
          if (!$tpl) $tpl = $this->createTemplate($templatename, $class);
          $tmp->template = $tpl;
        } catch (\Throwable $th) {
        }
      }
      if (method_exists($tmp, "migrate")) $tmp->migrate();
    }
  }

  /**
   * Run migrations of all watchfiles
   * @return void
   */
  public function migrateWatchfiles($force = false)
  {
    if (!$this->isCLI() and $this->wire->config->noMigrate) {
      $this->log("Migrations disabled via \$config->noMigrate");
      return;
    }

    // prevent auto-migrate when CLI mode is enabled or when $rm->noMigrate()
    // was called (which can be handy to get quick reloads while working on a
    // module whithout the need for migrations)
    // $rm->run() will run either way because it sets force=true

    // this prevents running migrations when processwire
    // is bootstrapped in other cli scripts
    $cli = $this->isCLI();
    if ($cli and !$force) return;

    $runOnlyWhenForced = $cli || $this->noMigrate;
    if ($runOnlyWhenForced and !$force) return;

    // on CLI we always migrate all files and not only changed ones
    // this is to make sure that all migrations run on deployments
    if ($cli) $this->migrateAll = true;

    // if the noMigrate flag is set we do not run migrations
    // this makes it possible to refresh modules without triggering another
    // run of migrations!
    if ($this->wire->session->noMigrate) {
      $this->wire->session->noMigrate = false;
      return;
    }

    $changed = $this->getChangedFiles();
    $run = ($force or self::debug or count($changed));
    if (!$run) return;

    // on module uninstall we reset the watchlist
    // this is just to indicate that behaviour
    if (!count($this->watchlist)) return;

    // set flat that indicates that migrations are in progress
    // this can be helpful in other modules to check if save actions
    // are triggered during a migration or not
    $this->ismigrating = true;

    // logging
    $this->wire->log->delete($this->className);
    if (!$cli) {
      $this->log('-------------------------------------');
      foreach ($changed as $file) $this->log("Detected change in $file");
      $this->log('Running migrations from watchfiles...');
    }

    // always refresh modules before running migrations
    // this makes sure that $rm->installModule() etc will catch all new files
    if (!$this->triggeredByRefresh) $this->refresh();

    $this->updateLastrun();
    foreach ($this->watchlist as $file) {
      if (!$file->migrate) continue;
      if (!$this->doMigrate($file)) {
        $this->log("--- Skipping {$file->path} (no change)");
        continue;
      }

      // if it is a callback we execute it
      if ($callback = $file->callback) {
        $callback->__invoke($this);
        continue;
      }

      // if it is a module we call $module->migrate()
      if ($module = $file->module) {
        if (method_exists($module, "migrate") or method_exists($module, "___migrate")) {
          $this->log("Triggering $module::migrate()");
          $module->migrate();
        } else {
          $this->log("--- Skipping $module::migrate() - method does not exist");
        }
        continue;
      }

      // if it is a pageclass we create a temporary page and migrate it
      if ($file->pageClass) {
        if ($this->doMigrate($file->path)) {
          $tmp = $this->wire->pages->newPage($file->template);
          if (
            method_exists($tmp, 'migrate') or
            (is_object($module) and method_exists($module, "___migrate"))
          ) {
            $this->log("Triggering {$file->pageClass}::migrate()");
            $tmp->migrate();
          }
        } else $this->log("--- Skipping {$file->pageClass} (no change)");
        continue;
      }

      // we have a regular file
      // first we render the file
      // this will already execute commands inside the file if it is PHP
      $this->log("Loading {$file->path}...");
      $migrate = $this->wire->files->render($file->path, [], [
        'allowedPaths' => [dirname($file->path)],
      ]);
      // if rendering the file returned a string we state that it is YAML code
      if (is_string($migrate)) $migrate = $this->yaml($migrate);
      if (is_array($migrate)) {
        $this->log("Returned an array - trigger migrate() of "
          . print_r($migrate, true));
        $this->migrate($migrate);
      }
    }
  }

  /**
   * Migrate yaml file
   */
  public function migrateYAML($path)
  {
    $data = $this->yaml($path);
    if (is_array($data)) $this->migrate($data);
  }

  /**
   * Move one page on top of another one
   * @return void
   */
  public function movePageAfter($page, $reference)
  {
    $page = $this->getPage($page);
    $ref = $this->getPage($reference);
    if (!$page->id) return $this->log("Page does not exist");
    if (!$ref->id) return $this->log("Reference does not exist");
    if ($page->parent !== $ref->parent) return $this->log("Both pages must have the same parent");
    $this->wire->pages->sort($page, $ref->sort + 1);
  }

  /**
   * Move one page on top of another one
   * @return void
   */
  public function movePageBefore($page, $reference)
  {
    $page = $this->getPage($page);
    $ref = $this->getPage($reference);
    if (!$page->id) return $this->log("Page does not exist");
    if (!$ref->id) return $this->log("Reference does not exist");
    if ($page->parent !== $ref->parent) return $this->log("Both pages must have the same parent");
    $this->wire->pages->sort($page, $ref->sort);
  }

  /**
   * Set no migrate flag
   */
  public function noMigrate()
  {
    $this->noMigrate = true;
  }

  /**
   * Execute profile
   */
  private function profileExecute()
  {
    $profile = $this->wire->input->post('profile', 'filename');
    foreach ($this->profiles() as $path => $label) {
      if ($label !== $profile) continue;
      $this->wire->files->include($path);
      $this->wire->message("Executed profile $label");
      return true;
    }
    return false;
  }

  private function profiles()
  {
    $profiles = [];
    $opt = ['extensions' => ['php']];
    foreach ($this->wire->files->find(__DIR__ . "/profiles", $opt) as $file) {
      $profiles[$file] = basename($file);
    }
    $path = $this->wire->config->paths->assets . "RockMigrations/profiles";
    foreach ($this->wire->files->find($path, $opt) as $file) {
      $profiles[$file] = basename($file);
    }
    return $profiles;
  }

  /**
   * Refresh modules
   */
  public function refresh()
  {
    $this->wire->session->noMigrate = true;
    $this->log('Refreshing modules...');
    $this->wire->modules->refresh();
    $this->wire->session->noMigrate = false;
  }

  /**
   * Remove Field from Template
   *
   * @param Field|string $field
   * @param Template|string $template
   * @param bool $force
   * @return void
   */
  public function removeFieldFromTemplate($field, $template, $force = false)
  {
    $field = $this->getField($field);
    if (!$field) return;
    $template = $this->getTemplate($template);
    if (!$template) return;

    $fg = $template->fieldgroup;
    /** @var Fieldgroup $fg */
    if ($force) $field->flags = 0;

    if (!$fg->get($field->name)) return;

    $fg->remove($field);
    $fg->save();
    $this->log("Removed field $field from template $template");
  }

  /**
   * See method above
   */
  public function removeFieldsFromTemplate($fields, $template, $force = false)
  {
    foreach ($fields as $field) $this->removeFieldFromTemplate($field, $template, $force);
  }

  /**
   * Remove a permission from given role
   *
   * @param string|int $permission
   * @param string|int $role
   * @return void
   */
  public function removePermissionFromRole($permission, $role)
  {
    if (!$role = $this->getRole($role)) return;
    $role->of(false);
    $role->removePermission($permission);
    return $role->save();
  }

  /**
   * Remove an array of permissions to an array of roles
   *
   * @param array|string $permissions
   * @param array|string $roles
   * @return void
   */
  public function removePermissionsFromRoles($permissions, $roles)
  {
    if (!is_array($permissions)) $permissions = [(string)$permissions];
    if (!is_array($roles)) $roles = [(string)$roles];
    foreach ($permissions as $permission) {
      foreach ($roles as $role) {
        $this->removePermissionFromRole($permission, $role);
      }
    }
  }

  /**
   * Remove access from template for given role
   * @return void
   */
  public function removeTemplateAccess($tpl, $role)
  {
    if (!$role = $this->getRole($role)) return;
    if (!$tpl = $this->getTemplate($tpl)) return;
    $tpl->removeRole($role, "all");
    $tpl->save();
  }

  /**
   * Remove all template context field settings
   * @return void
   */
  public function removeTemplateContext($tpl, $field)
  {
    $tpl = $this->getTemplate($tpl);
    $field = $this->getField($field);
    $tpl->fieldgroup->setFieldContextArray($field->id, []);
  }

  /**
   * Rename given page
   * @return void
   */
  public function renamePage($page, $newName, $quiet = false)
  {
    if (!$page = $this->getPage($page, $quiet)) return;
    $old = $page->name;
    $page->setAndSave('name', $newName);
    $this->log("Renamed page from $old to $newName");
  }

  /**
   * Require all PHP files in given path
   */
  public function require($path, $recursive = 1)
  {
    $options = [
      'extensions' => ['php'],
      'recursive' => $recursive,
    ];
    foreach ($this->wire->files->find($path, $options) as $file) {
      require_once $file;
    }
  }

  /**
   * Reset "lastrun" cache to force migrations
   * @return void
   */
  public function resetCache(HookEvent $event)
  {
    $this->updateLastrun(0);
  }

  /**
   * Run migrations that have been attached via watch()
   * @return void
   */
  public function run()
  {
    $user = $this->wire->user;
    $this->sudo();
    $this->migrateWatchfiles(true);
    $this->wire->users->setCurrentUser($user);
  }

  /**
   * Set the logo url of the backend logo (AdminThemeUikit)
   * @return void
   */
  public function setAdminLogoUrl($url)
  {
    $this->setModuleConfig("AdminThemeUikit", ['logoURL' => $url]);
  }

  /**
   * Set default options for several things in PW
   * These are opinionated defaults that I like to use in my projects!
   */
  public function setDefaults($options = [])
  {
    $this->log('$rm->setDefaults() is deprecated! Use rm-defaults snippet instead');
  }

  /**
   * Set data of a field
   *
   * If a template is provided the data is set in template context only.
   *
   * Multilang is also possible:
   * $rm->setFieldData('yourfield', [
   *   'label' => 'foo', // default language
   *   'label1021' => 'bar', // other language
   * ]);
   *
   * @param Field|string $field
   * @param array $data
   * @param Template|string $template
   * @return void
   */
  public function setFieldData($field, $data, $template = null)
  {
    $field = $this->getField($field);
    if (!$field) return; // logging in getField()

    // Support shortcut syntax
    // $rm->setFieldData('title', 'textLanguage');
    if (is_string($data)) $data = ['type' => $data];

    // check if the field type has changed
    if (array_key_exists('type', $data)) {
      $type = $this->getFieldtype($data['type']);
      if ((string)$type !== (string)$field->type) {
        $field->type = $type;
        // if we do not save the field here it will lose some data (eg icon)
        $field->save();
      }
    }

    // save trace of migration to field
    // this is shown on field edit screen
    $field->_rockmigrations_log = $this->getTraceLog();

    // prepare data array
    foreach ($data as $key => $val) {

      // this makes it possible to set the template via name
      if ($key === "template_id") {
        $tpl = $this->getTemplate($val);
        if (!$tpl) continue;
        $data[$key] = $tpl->id;
      }

      // support repeater fields short syntax
      if ($field->type instanceof FieldtypeRepeater) {
        // check for old syntax and throw an error
        if ($key == 'repeaterFields' or $key == 'fieldContexts') {
          throw new WireException("Please use the new fields syntax, see https://bit.ly/3WVB2Dc");
        }

        // new "fields" syntax for repeater fields as of v2.9.0
        if ($key === 'fields' or $key === 'fields-') {
          $tpl = $field->type->getRepeaterTemplate($field);
          $this->setTemplateData($tpl, [$key => $data[$key]]);
        }
      }

      // add support for setting options of a select field
      // this will remove non-existing options from the field!
      if ($key === "options") {
        $options = $data[$key];
        $this->setOptions($field, $options, true);

        // this prevents setting the "options" property directly to the field
        // if not done, the field shows raw option values when rendered
        unset($data['options']);
      }
      if ($key == "optionsLang") {
        $options = $data[$key];
        $this->setOptionsLang($field, $options, true);

        // this prevents setting the "options" property directly to the field
        // if not done, the field shows raw option values when rendered
        unset($data[$key]);
      }
    }

    // set data
    if (!$template) {
      // set field data directly
      foreach ($data as $k => $v) $field->set($k, $v);
    } else {
      // make sure the template is set as array of strings
      if (!is_array($template)) $template = [(string)$template];

      foreach ($template as $t) {
        $tpl = $this->templates->get((string)$t);
        if (!$tpl) throw new WireException("Template $t not found");

        // set field data in template context
        $fg = $tpl->fieldgroup;
        $current = $fg->getFieldContextArray($field->id);
        $fg->setFieldContextArray($field->id, array_merge($current, $data));
        $fg->saveContext();
      }
    }

    // Make sure Table field actually updates database schema
    if ($field->type == "FieldtypeTable") {
      $fieldtypeTable = $field->getFieldtype();
      $fieldtypeTable->_checkSchema($field, true); // Commit changes
    }

    $field->save();

    return $field;
  }

  /**
   * Set field order at given template
   *
   * The first field is always the reference for all other fields.
   *
   * @param array $fields
   * @param Template|string $name
   * @return void
   */
  public function setFieldOrder($fields, $template)
  {
    if (!$template = $this->getTemplate($template)) return;

    // make sure fields is an array and not a fieldgroup
    if ($fields instanceof Fieldgroup) $fields = $fields->getArray();

    // make sure that all fields exist
    foreach ($fields as $i => $field) {
      if (!$this->getField($field)) unset($fields[$i]);
    }
    $fields = array_values($fields); // reset indices
    foreach ($fields as $i => $field) {
      if (!$i) continue;
      $this->addFieldToTemplate($field, $template, $fields[$i - 1]);
    }
  }

  /**
   * Set language translations from zip file to a language
   * Removes old translation files and installs LanguageSupport.
   *
   * Usage:
   * // use german translation files for default language
   * $rm->setLanguageTranslations('DE');
   *
   * @param string $translations Url to language zip OR 2-letter-code (eg DE)
   * @param string|Language $lang PW Language to update
   * @return Language $language
   */
  public function setLanguageTranslations($translations, $lang = null)
  {
    $zip = $this->getLanguageZip($translations);

    // Make sure Language Support is installed
    if (!$this->wire->languages) $this->addLanguageSupport();
    if ($lang) {
      $language = $this->getLanguage($lang);
      if (!$language) return; // logging above
    } else $language = $this->wire->languages->getDefault();
    if (!$language->id) return $this->log("No language found");
    $language->of(false);

    $this->log("Downloading $zip");
    $cache = $this->wire->config->paths->cache;
    $http = new WireHttp();
    $zipTemp = $cache . $lang . "_temp.zip";
    $http->download($zip, $zipTemp);

    // Unzip files and add .json files to language
    $items = $this->wire->files->unzip($zipTemp, $cache);
    if ($cnt = count($items)) {
      $this->log("Adding $cnt new language files to language $language");
      $language->language_files->deleteAll();
      $language->save();
      foreach ($items as $item) {
        if (strpos($item, ".json") === false) continue;
        $language->language_files->add($cache . $item);
      }
    }
    $language->save();

    return $language;
  }

  /**
   * Set module config data
   *
   * By default this will remember old settings and only set the ones that are
   * specified as $data parameter. If you want to reset old parameters
   * set the $reset param to true.
   *
   * Note that the update feature will only work for simple settings like
   * single checkboxes or text fields. If you set a field with multiple checkboxes
   * from this:
   * [foo, bar]
   * to this:
   * [baz]
   * The result will be [baz] no matter what you set in $reset
   *
   *
   *
   * @param string|Module $module
   * @param array $data
   * @param bool $merge
   * @return Module|false
   */
  public function setModuleConfig($module, $data, $reset = false)
  {
    /** @var Module $module */
    $name = (string)$module;
    $module = $this->modules->get($name);
    if (!$module) {
      if ($this->config->debug) $this->log("Module $name not found");
      return false;
    }

    // now we merge the new config data over the old config
    // if reset is TRUE we skip this step which means we may lose old config!
    if (!$reset) {
      $old = $this->wire->modules->getConfig($module);
      if (!is_array($data)) $data = [];
      $data = array_merge($old, $data);
    }

    $this->modules->saveConfig($module, $data);
    return $module;
  }

  /**
   * Set options of an options field as array
   *
   * Usage:
   * $rm->setOptions($field, [
   *   // never use key 0 !!
   *   1 => 'foo|My foo option',
   *   2 => 'bar|My bar option',
   * ]);
   *
   * @param Field|string $field
   * @param array $options
   * @param bool $removeOthers
   * @return Field|null
   */
  public function setOptions($field, $options, $removeOthers = false)
  {
    $string = "";
    foreach ($options as $k => $v) {
      if ($k === 0) $this->log("Option with key 0 skipped");
      else $string .= "\n$k=$v";
    }
    return $this->setOptionsString($field, $string, $removeOthers);
  }

  /**
   * Set options of an options field as array for given language
   *
   * @param Field|string $field
   * @param array $options
   * @param bool $removeOthers
   * @param Language $lang
   * @return Field|null
   */
  public function setOptionsLang($field, $options, $removeOthers = false)
  {
    $field = $this->getField($field);

    $optionsArray = [];
    foreach ($options as $lang => $opt) {
      $lang = $this->getLanguage($lang);
      $string = "";
      foreach ($opt as $k => $v) {
        if ($k === 0) $this->log("Option with key 0 skipped");
        else $string .= "\n$k=$v";
      }
      if ($lang->isDefault()) $defaults = $string;
      $optionsArray[$lang->id] = $string;
    }

    /** @var SelectableOptionManager $manager */
    $manager = $this->wire(new SelectableOptionManager());

    // now set the options
    // first we set the default options
    // if we dont do that, the translations are empty on the first run of migrate
    $manager->setOptionsString($field, $defaults, $removeOthers); // necessary!
    $manager->setOptionsStringLanguages($field, $optionsArray, $removeOthers);
    $field->save();

    return $field;
  }

  /**
   * Set options of an options field via string
   *
   * Better use $rm->setOptions($field, $options) to set an array of options!
   *
   * $rm->setOptionsString("yourfield", "
   *   1=foo|My Foo Option
   *   2=bar|My Bar Option
   * ");
   *
   * @param Field|string $name
   * @param string $options
   * @param bool $removeOthers
   * @return void
   */
  public function setOptionsString($name, $options, $removeOthers = false)
  {
    $field = $this->getField($name);

    /** @var SelectableOptionManager $manager */
    $manager = $this->wire(new SelectableOptionManager());

    // now set the options
    $manager->setOptionsString($field, $options, $removeOthers);
    $field->save();

    return $field;
  }

  /**
   * Set output level
   * @return void
   */
  public function setOutputLevel($level)
  {
    $this->outputLevel = $level;
  }

  /**
   * Set page name replacements as array or by filename
   *
   * This will update the 'replacements' setting of InputfieldPageName module
   *
   * Usage: $rm->setPagenameReplacements("de");
   * Usage: $rm->setPagenameReplacements(['ä'=>'ae']);
   *
   * @param mixed $data
   * @return void
   */
  public function setPagenameReplacements($data)
  {
    if (is_string($data)) {
      $file = __DIR__ . "/replacements/$data.txt";
      if (!is_file($file)) {
        return $this->log("File $file not found");
      }
      $replacements = explode("\n", $this->wire->files->render($file));
      $arr = [];
      foreach ($replacements as $row) {
        $items = explode("=", $row);
        $arr[$items[0]] = $items[1];
      }
    } elseif (is_array($data)) $arr = $data;
    if (!is_array($arr)) return;
    $this->setModuleConfig("InputfieldPageName", ['replacements' => $arr]);
  }

  /**
   * Set parent child family settings for two templates
   */
  public function setParentChild($parent, $child, $onlyOneParent = true)
  {
    $noParents = 0; // many parents are allowed
    if ($onlyOneParent) $noParents = -1;
    $this->setTemplateData($child, [
      'noChildren' => 1, // may not have children
      'noParents' => '', // can be used for new pages
      'parentTemplates' => [(string)$parent],
    ]);
    $this->setTemplateData($parent, [
      'noChildren' => 0, // may have children
      'noParents' => $noParents, // only one page
      'childTemplates' => [(string)$child],
      'childNameFormat' => 'title',
    ]);
  }

  /**
   * Set settings of a template's access tab
   *
   * This will by default only ADD permissions and not remove them!
   * This is to be consistent with other SET methods (setData, migrate, etc)
   *
   * Usage add permissions "view", "edit", "create", "add" for role "my-role" to template "my-template":
   * $rm->setTemplateAccess("my-tpl", "my-role", "view, edit, create, add"]);
   *
   * Usage remove permission "add" and keep permissions "view", "edit", "create":
   * $rm->setTemplateAccess("my-tpl", "my-role", "view, edit, create", true);
   *
   * Thx @apeisa https://bit.ly/2QU1b8e
   *
   * @param mixed $tpl Template
   * @param mixed $role Role
   * @param array $access Permissions, eg ['view', 'edit']
   * @param bool $remove Reset Permissions for this role? If true, first all permissions are removed and then all in $access will be added back in
   * @return void
   */
  public function setTemplateAccess($tpl, $role, $access, $remove = false)
  {
    $tpl = $this->getTemplate($tpl);
    $role = $this->getRole($role);
    if ($remove) $this->removeTemplateAccess($tpl, $role);
    $this->setTemplateData($tpl, ['useRoles' => 1]);
    if (is_string($access)) $access = $this->strToArray($access);
    foreach ($access as $acc) $this->addTemplateAccess($tpl, $role, $acc);
  }

  /**
   * Set data of a template
   *
   * Only the properties provided will be set on the template. It will not touch
   * any properties that are not specified in $data
   *
   * Usage:
   * $rm->setTemplateData('yourtemplate', [
   *   'label' => 'foo',
   * ]);
   *
   * Multilang:
   * $rm->setTemplateData('yourtemplate', [
   *   'label' => 'foo', // default language
   *   'label1021' => 'bar', // other language
   * ]);
   *
   * @param Template|Page|string $template
   * @param array $data
   * @return Template
   */
  public function setTemplateData($name, array $data)
  {
    $template = $this->getTemplate($name);
    if (!$template) return; // logging above

    // it is possible to define templates without data:
    // rm->migrate('templates' => ['tpl1', 'tpl2'])
    if (!$data) return $template;

    // save trace of migration to field
    // this is shown on field edit screen
    $template->_rockmigrations_log = $this->getTraceLog();

    // loop template data
    foreach ($data as $k => $v) {

      // the "fields" property is a special property from RockMigrations
      // templates have "fieldgroupFields" and "fieldgroupContexts"
      if (($k === 'fields' || $k === 'fields-')) {
        if (is_array($v)) {
          $removeOthers = ($k === 'fields-');
          $this->setTemplateFields($template, $v, $removeOthers);
        } else {
          $this->log("Value of property 'fields' must be an array");
        }
        continue;
      }

      // set property of template
      $template->set($k, $v);
    }
    $template->save();
    return $template;
  }

  /**
   * Set fields of template via array
   * @return void
   */
  public function setTemplateFields($template, $fields, $removeOthers = false)
  {
    $template = $this->getTemplate($template);
    if (!$template) return; // logging happens in getTemplate()
    $oldfields = clone $template->fields;

    $last = null;
    $names = [];
    $newfields = $this->wire(new Fieldgroup());
    foreach ($fields as $name => $data) {
      if (is_int($name) and is_int($data)) {
        $name = $this->getField((string)$data)->name;
        $data = [];
      }
      if (is_int($name)) {
        $name = $data;
        $data = [];
      }
      $names[] = $name;
      $field = $this->getField($name);
      if ($field) $newfields->add($field);
      $this->addFieldToTemplate($name, $template, $last);
      $this->setFieldData($name, $data, $template);
      $last = $name;
    }

    if (!$removeOthers) {
      $merged = $this->mergeFields($oldfields, $newfields);
      $this->setFieldOrder($merged, $template);
      return;
    }

    // remove other fields!
    foreach ($template->fields as $field) {
      $name = (string)$field;
      if (!in_array($name, $names)) {
        // remove this field from the template
        // global fields like the title field are also removed
        $this->removeFieldFromTemplate($name, $template, true);
      }
    }
  }

  /**
   * Set user data
   *
   * $rm->setUserData('demo', [
   *   'roles' => [...],
   *   'adminTheme' => ...,
   * ]);
   *
   * @param mixed $user
   * @param array $data
   * @return User
   */
  public function setUserData($user, array $data)
  {
    $user = $this->getUser($user);
    if (!$user) return; // logging above
    $user->of(false);

    // setup options
    $opt = $this->wire(new WireData());
    /** @var WireData $opt */
    $opt->setArray([
      // dont set password here as this would reset passwords
      // when createUser() is used in a migration!
      'roles' => [],
      'admintheme' => 'AdminThemeUikit',
      'password' => null,
    ]);
    $opt->setArray($data);

    // set roles
    if (is_string($opt->roles)) $opt->roles = [$opt->roles];
    foreach ($opt->roles as $role) $this->addRoleToUser($role, $user);

    // set password if it is set
    if ($opt->password) $user->set('pass', $opt->password);

    // save admin theme in 2 steps
    // otherwise the admin theme will not update (PW issue)
    if ($opt->admintheme) $user->set('admin_theme', $opt->admintheme);

    $user->save();
    $this->log("Set user data for user $user ({$user->name})");
    return $user;
  }

  /**
   * Show edit info on field and template edit screen
   * @return void
   */
  public function showCopyCode(HookEvent $event)
  {
    $form = $event->object;
    if (!$id = $this->wire->input->get('id', 'int')) return;

    if ($event->process == 'ProcessField') {
      $existing = $form->get('field_label');
      $item = $this->wire->fields->get($id);
    } elseif ($event->process == 'ProcessTemplate') {
      $existing = $form->get('fieldgroup_fields');
      $item = $this->wire->templates->get($id);
    } else return;

    // early exit (eg when changing fieldtype)
    if (!$existing) return;

    $form->add([
      'name' => '_RockMigrationsCopyInfo',
      'type' => 'markup',
      'label' => 'RockMigrations Code',
      'description' => 'This is the code you can use for your migrations. Use it in $rockmigrations->migrate():',
      'value' => "<pre><code>" . $this->getCode($item) . "</code></pre>",
      'collapsed' => Inputfield::collapsedYes,
      'icon' => 'code',
    ]);
    $f = $form->get('_RockMigrationsCopyInfo');
    $form->remove($f);
    $form->insertBefore($f, $existing);
  }

  /**
   * Show edit info on field and template edit screen
   * @return void
   */
  public function showEditInfo(HookEvent $event)
  {
    $form = $event->object;
    if (!$id = $this->wire->input->get('id', 'int')) return;

    if ($event->process == 'ProcessField') {
      $existing = $form->get('field_label');
      $item = $this->wire->fields->get($id);
    } elseif ($event->process == 'ProcessTemplate') {
      $existing = $form->get('fieldgroup_fields');
      $item = $this->wire->templates->get($id);
    } else return;

    // early exit (eg when changing fieldtype)
    if (!$existing) return;

    $log = $item->get('_rockmigrations_log') ?: '';
    if ($log) $log = "<small>" . nl2br($log) . "</small>";
    $form->add([
      'name' => '_RockMigrations',
      'type' => 'markup',
      'label' => 'RockMigrations',
      'value' => '<div class="uk-alert">
        ATTENTION - RockMigrations is installed on this system. You can apply
        changes in the GUI as usual but if any settings are set via code in a
        migration file they will be overwritten on the next migration cycle!
        </div>' . $log,
    ]);
    $f = $form->get('_RockMigrations');
    $f->entityEncodeText = false;
    $form->remove($f);
    $form->insertBefore($f, $existing);
  }

  /**
   * Get sorted WireArray of fields
   * @return WireArray
   */
  public function sort($data)
  {
    $arr = $this->wire(new WireArray());
    /** @var WireArray $arr */
    foreach ($data as $item) $arr->add($item);
    return $arr->sort('name');
  }

  /**
   * Convert a comma separated string into an array of single values
   */
  public function strToArray($data): array
  {
    if (is_array($data)) return $data;
    if (!is_string($data)) throw new WireException("Invalid data in strToArray");
    return array_map('trim', explode(",", $data));
  }

  /**
   * Add submodule to project
   * This will only add the submodule if the destination path does not exist!
   * @return void
   */
  public function submodule($name, $config = [], $url = null, $dst = null)
  {
    $url = $url ?: 'git@github.com:baumrock';
    $dst = $dst ?: "site/modules/$name";
    $this->setModuleConfig($name, $config);
    if (is_dir($this->wire->config->paths->root . $dst)) return;
    $cwd = getcwd();
    ob_start();
    chdir($this->wire->config->paths->root);
    shell_exec("git submodule add $url/$name.git $dst");
    chdir($cwd);
    $this->refresh();
    $this->installModule($name, $config);
  }

  /**
   * Change current user to superuser
   * When bootstrapped sometimes we get permission conflicts
   * See https://processwire.com/talk/topic/458-superuser-when-bootstrapping/
   * @return void
   */
  public function sudo()
  {
    $role = $this->wire->roles->get('superuser');
    $su = $this->wire->users->get("sort=id,roles=$role");
    if (!$su->id) return $this->log("No superuser found");
    $this->wire->users->setCurrentUser($su);
  }

  /**
   * Sync snippets
   */
  private function syncSnippets()
  {
    if (!$this->conf->syncSnippets) return;
    $this->fileSync(
      "/.vscode/RockMigrations.code-snippets",
      __DIR__ . "/.vscode/RockMigrations.code-snippets"
    );
    $this->fileSync(
      "/.vscode/ProcessWire.code-snippets",
      __DIR__ . "/.vscode/ProcessWire.code-snippets"
    );
  }

  /**
   * Make sure that the given file/directory path is absolute
   * This will NOT check if the directory or path exists!
   * It will always prepend the PW root directory so this method does not work
   * for absolute paths outside of PW!
   */
  public function toPath($url): string
  {
    $url = $this->toUrl($url);
    return $this->wire->config->paths->root . ltrim($url, "/");
  }

  /**
   * Make sure that the given file/directory path is relative to PW root
   * This will NOT check if the directory or path exists!
   * Other than PWs url feature it will remove trailing slashes!
   * If provided a path outside of PW root it will return that path because
   * the str_replace only works if the path starts with the pw root path!
   */
  public function toUrl($path, $cachebuster = false): string
  {
    $cache = '';
    if ($cachebuster) {
      $path = $this->toPath($path);
      if (is_file($path)) $cache = "?m=" . filemtime($path);
    }
    return rtrim(str_replace(
      $this->wire->config->paths->root,
      $this->wire->config->urls->root,
      Paths::normalizeSeparators((string)$path) . $cache
    ), "/");
  }

  /**
   * Trigger migrations after Modules::refresh
   * @return void
   */
  public function triggerMigrations(HookEvent $event)
  {
    // If flags are present dont attach hooks to Modules::refresh
    // See the readme for more information!
    if (defined("DontFireOnRefresh")) return;
    if ($this->wire->config->DontFireOnRefresh) return;
    if (!$this->wire->session->noMigrate) $this->migrateAll = true;
    $this->triggeredByRefresh = true;
    $this->run();
  }

  /**
   * Unwatch all files
   */
  public function unwatchAll()
  {
    $this->watchlist->removeAll();
  }

  /**
   * On every uninstall of a module we unwatch all files to make sure
   * that migrations are not run immediately after uninstall.
   */
  public function unwatchBeforeUninstall(HookEvent $event)
  {
    $this->unwatchAll();
  }

  /**
   * Update last run timestamp
   * @return void
   */
  public function updateLastrun($timestamp = null)
  {
    if ($timestamp === null) $timestamp = time();
    $this->wire->cache->save(self::cachename, $timestamp, WireCache::expireNever);
  }

  /**
   * Uninstall module
   *
   * @param string|Module $name
   * @return void
   */
  public function uninstallModule($name)
  {
    if (!$this->modules->isInstalled($name)) return;
    $this->wire->session->noMigrate = true;
    $this->modules->uninstall((string)$name);
    $this->wire->session->noMigrate = false;
  }

  public function unset(&$array, $property)
  {
    if (!array_key_exists($property, $array)) return;
    unset($array[$property]);
  }

  /**
   * PHP var_export() with short array syntax (square brackets) indented 2 spaces.
   *
   * NOTE: The only issue is when a string value has `=>\n[`, it will get converted to `=> [`
   * @link https://www.php.net/manual/en/function.var-export.php
   */
  function varexport($expression, $return = TRUE)
  {
    $export = var_export($expression, TRUE);
    $patterns = [
      "/array \(/" => '[',
      "/^([ ]*)\)(,?)$/m" => '$1]$2',
      "/=>[ ]?\n[ ]+\[/" => '=> [',
      "/([ ]*)(\'[^\']+\') => ([\[\'])/" => '$1$2 => $3',
    ];
    $export = preg_replace(array_keys($patterns), array_values($patterns), $export);
    if ((bool)$return) return $export;
    else echo $export;
  }

  /**
   * Add file to watchlist
   *
   * Usage:
   * Default priority = 1 (higher = earlier)
   * $rm->watch(what, migrate/priority, options);
   *
   * If you dont specify an extension it will watch all available extensions:
   * $rm->watch('/path/to/module'); // watches module.[yaml|json|php]
   *
   * Watch a module: Put this in your module's init()
   * $rm->watch($this);
   * This will automatically call $yourModule->migrate();
   *
   * Only watch the file but don't migrate it. This is useful if a migration
   * file depends on something else (like constants of a module). To make the
   * migrations run when the module changes you can add the module file to the
   * watchlist:
   * $rm->watch('/site/modules/MyModule.module.php', false);
   *
   * You an set a priority as second parameter. Default will be 1.
   * Higher numbers have higher priority and therefore run earlier than others.
   *
   * Note that migrations will only run when you are logged in as superuser!
   *
   * @param mixed $what File, directory or Module to be watched
   * @param bool|float $migrate Execute migration? Float = priority (high=earlier, 1=default)
   * @param array $options Array of options
   * @return void
   */
  public function watch($what, $migrate = true, $options = [])
  {
    if (!$this->watchEnabled()) return;

    // setup options
    $opt = $this->wire(new WireData());
    $opt->setArray([
      'recursive' => false,
      'force' => false,
    ]);
    $opt->setArray($options);

    // other variables
    $file = $what;
    $migrate = (float)$migrate;

    $module = false;
    $callback = false;
    $pageClass = false;
    $hash = false;

    $trace = debug_backtrace()[1];
    $tracefile = $trace['file'];
    $traceline = $trace['line'];

    // watch a custom pageclass
    if ($what instanceof Page) {
      $reflector = new \ReflectionClass($what);
      $file = $reflector->getFileName();
      $opt->template = $what->template;
      return $this->watchPageClass(
        $file,
        $reflector->getNamespaceName(),
        $opt->getArray(),
        $migrate
      );
    }
    // instance of module
    elseif ($what instanceof Module) {
      $module = $what;
      $file = $this->wire->modules->getModuleFile($module);
    }
    // callback
    elseif (!is_string($what) and is_callable($what)) {
      $trace = debug_backtrace()[0];
      $tracefile = $trace['file'];
      $traceline = $trace['line'];
      $callback = $what;
      $file = $tracefile;
      $hash = "::" . uniqid();
    }
    // path to folder
    elseif (is_dir($what)) {
      $dir = $what;
      $fopt = [
        'extensions' => ['php'],
        'recursive' => $opt->recursive,
      ];
      foreach ($this->wire->files->find($dir, $fopt) as $f) {
        $this->watch($f, $migrate, $opt->getArray());
      }
    }

    // if we got no file until now we exit early
    if (!$path = $this->file($file)) return;

    // set migrate to false if extension is not valid
    // this can happen on $rm->watch("/my/file.js");
    if ($migrate) {
      $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
      $allowed = ['php', 'yaml'];
      if (!in_array($ext, $allowed)) $migrate = false;
    }

    // if path already exists we skip adding this file
    $exists = $this->watchlist->get("path=$path");
    if ($exists) {
      if (!$this->isCLI()) {
        $this->log("Did not add $path to watchlist because it already exists.
          Called in $tracefile:$traceline");
      }
      return;
    }

    require_once($this->path . "WatchFile.php");
    $data = $this->wire(new WatchFile());
    /** @var WatchFile $data */
    $data->setArray([
      'path' => $path . $hash,
      'module' => $module,
      'callback' => $callback,
      'pageClass' => $opt->pageClass,
      'migrate' => (float)$migrate,
      'trace' => "$tracefile:$traceline",
      'changed' => false,
      'force' => $opt->force,
      'template' => $opt->template,
    ]);
    // bd($data, $data->path);

    // add item to watchlist and sort watchlist by migrate priority
    // see https://github.com/processwire/processwire-issues/issues/1528
    $this->watchlist->add($data)->sortFloat('migrate');
  }

  public function watchEnabled()
  {
    if (!$this->wire->user) return false;
    if ($this->wire->user->isSuperuser()) return true;
    if ($this->wire->config->forceWatch) return true;
    if ($this->isCLI()) return true;
    if ($this->wire->modules->isInstalled('TracyDebugger')) {
      $tracy = $this->wire->modules->get('TracyDebugger');
      if ($tracy->forceIsLocal) return true;
    }
    return false;
  }

  /**
   * Return watchlist
   * @return WireArray
   */
  public function watchlist()
  {
    return $this->watchlist;
  }

  /**
   * Watch module migration files
   *
   * Note that files are only watched if you are logged in as superuser!
   *
   * @return void
   */
  public function watchModules()
  {
    if (!$this->watchEnabled()) return;
    $path = $this->wire->config->paths->siteModules;
    foreach (new DirectoryIterator($path) as $fileInfo) {
      if (!$fileInfo->isDir()) continue;
      if ($fileInfo->isDot()) continue;
      $name = $fileInfo->getFilename();
      $migrateFile = $fileInfo->getPath() . "/$name/$name.migrate";
      $this->watch("$migrateFile.yaml");
      $this->watch("$migrateFile.json");
      $this->watch("$migrateFile.php");
    }
  }

  /**
   * Add a single pageclass file to watchlist
   * @return void
   */
  public function watchPageClass(string $file, $namespace = "ProcessWire", $options = [], $migrate = true)
  {
    $name = pathinfo($file, PATHINFO_FILENAME);
    $class = "$namespace\\$name";
    $options = array_merge([
      'pageClass' => $class,
    ], $options);
    $this->watch($file, $migrate, $options);
  }

  /**
   * Watch pageClasses and trigger migrate() on change
   * @return void
   */
  public function watchPageClasses($path, $namespace = "ProcessWire", $options = [], $migrate = true)
  {
    if (!$this->watchEnabled()) return;
    if (is_dir($path)) {
      $opt = ['extensions' => ['php'], 'recursive' => 1];
      foreach ($this->wire->files->find($path, $opt) as $file) {
        $this->watchPageClass($file, $namespace, $options, $migrate);
      }
    } elseif (is_file($path)) $this->watchPageClass($path, $namespace, $options, $migrate);
    else $this->log("Nothing to watch in $path");
  }

  /**
   * Interface to the Symfony YAML class
   *
   * Get array from YAML file
   * $rm->yaml('/path/to/file.yaml');
   *
   * Save data to file
   * $rm->yaml('/path/to/file.yaml', ['foo'=>'bar']);
   *
   * @return mixed
   */
  public function yaml($path, $data = null)
  {
    if (!$path) return;
    require_once(__DIR__ . '/vendor/autoload.php');

    // write yaml data to file
    if ($data) {
      // early exit if noYaml flag is set
      if ($this->noYaml) return;

      // remove properties that are not helpful in yaml files
      $this->unset($data, 'configPhpHash');

      $yaml = Yaml::dump($data, 99, 2);
      $yaml = str_replace("''", '""', $yaml);
      $yaml = str_replace(" '", ' "', $yaml);
      $yaml = str_replace("'\n", "\"\n", $yaml);
      $this->wire->files->mkdir(dirname($path), true);
      $this->wire->files->filePutContents($path, $yaml);
      return $yaml;
    }

    if (!is_file($path)) return false;
    return Yaml::parseFile($path);
  }

  /**
   * Config inputfields
   * @param InputfieldWrapper $inputfields
   */
  public function getModuleConfigInputfields($inputfields)
  {
    $video = new InputfieldMarkup();
    $video->label = 'processwire-rocks.com';
    $video->value = '<iframe width="560" height="315" src="https://www.youtube.com/embed/eBOB8dZvRN4" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>';
    $inputfields->add($video);

    $inputfields->add([
      'type' => 'markup',
      'label' => 'RockMigrations Config Options',
      'value' => 'You can set all settings either here via GUI or alternatively via config array:<br>
        <pre>$config->rockmigrations = [<br>'
        . '  "syncSnippets" => true,<br>'
        . '];</pre>'
        . 'Note that settings in config.php have precedence over GUI settings!',
    ]);

    $f = $this->wire->modules->get('InputfieldCheckboxes');
    $f->name = 'enabledTweaks';
    $f->label = "Tweaks";
    $f->entityEncodeText = false;
    foreach ($this->tweaks as $tweak) {
      $f->addOption($tweak->name, implode(' - ', array_filter([$tweak->name, $tweak->description])));
    }
    $f->value = (array)$this->enabledTweaks;
    $inputfields->add($f);

    $inputfields->add([
      'type' => 'checkbox',
      'name' => 'syncSnippets',
      'label' => 'Sync VSCode Snippets to PW root',
      'description' => "If this option is enabled the module will copy the vscode snippets file to the PW root directory. If you are using VSCode I highly recommend using this option. See readme for details.",
      'collapsed' => Inputfield::collapsedBlank,
    ]);
    $inputfields->children()->last()->attr(
      'checked',
      $this->syncSnippets ? 'checked' : ''
    );

    $this->profileExecute();
    $f = new InputfieldSelect();
    $f->label = "Execute one of the existing profile migrations";
    $f->name = 'profile';
    foreach ($this->profiles() as $path => $label) {
      $f->addOption($label, $label);
    }
    $path = $this->wire->config->paths->assets . "RockMigrations/profiles";
    $f->notes = "You can place your own profiles in $path";
    $f->collapsed = Inputfield::collapsedYes;
    $inputfields->add($f);

    $this->console(); // run console code
    $inputfields->add([
      'type' => 'markup',
      'label' => 'Console',
      'icon' => 'code',
      'description' => "",
      'value' => $this->wire->files->render(__DIR__ . "/profileeditor.php", [
        'code' => $this->getConsoleCode(),
      ]),
    ]);

    return $inputfields;
  }

  private function getConsoleCode()
  {
    $code = $this->wire->pages->get(1)->meta('rockmigrations-consolecode');
    if ($code) return $code;
    return $this->wire->sanitizer->entities(
      $this->wire->files->fileGetContents(__DIR__ . "/profiles/default.php")
    );
  }

  /**
   * Execute console code
   */
  private function console()
  {
    if (!$code = $this->wire->input->post->code) return;
    if (!$this->wire->user->isSuperuser()) {
      throw new WireException("Console only allowed for superusers");
    }
    $this->wire->pages->get(1)->meta('rockmigrations-consolecode', $code);
    if (!$this->wire->input->post->runcode) return;

    // write code to temp file that we can execute
    $file = $this->wire->config->paths->cache . "rmconsole.php";
    $this->wire->files->filePutContents($file, $code);
    $this->refresh();
    $this->wire->files->include($file, ['code' => $code]);
    $this->wire->files->unlink($file);
  }

  public function ___install(): void
  {
    $file = $this->wire->config->paths->site . "migrate.php";
    if (!is_file($file)) {
      $this->wire->files->filePutContents(
        $file,
        $this->wire->files->fileGetContents(__DIR__ . "/stubs/migrate.php")
      );
    }
  }

  public function __debugInfo()
  {
    $lastrun = "never";
    if ($this->lastrun) {
      $lastrun = date("Y-m-d H:i:s", $this->lastrun) . " ({$this->lastrun})";
    }
    return [
      'Version' => $this->getModuleInfo()['version'],
      'lastrun' => $lastrun,
      'watchlist' => $this->watchlist,
    ];
  }
}
