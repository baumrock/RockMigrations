<?php namespace ProcessWire;

use DirectoryIterator;
use RockMigrations\RecorderFile;
use RockMigrations\WatchFile;
use RockMigrations\WireArray;
use RockMigrations\WireArray as WireArrayRM;
use RockMigrations\YAML;
use TracyDebugger;

/**
 * @author Bernhard Baumrock, 19.01.2022
 * @license COMMERCIAL DO NOT DISTRIBUTE
 * @link https://www.baumrock.com
 */
require_once __DIR__ . "/PageClass.php";
class RockMigrations extends WireData implements Module, ConfigurableModule {

  const debug = false;
  const cachename = 'rockmigrations-last-run';

  const outputLevelDebug = 'debug';
  const outputLevelQuiet = 'quiet';
  const outputLevelVerbose = 'verbose';

  /**
   * Timestamp of last run migration
   * @var int
   **/
  private $lastrun;

  private $outputLevel = self::outputLevelQuiet;

  /** @var string */
  public $path;

  /**
   * If true we will write data to recorder files
   * @var bool
   */
  public $record = false;

  /** @var WireArrayRM */
  private $recorders;

  /** @var WireArrayRM */
  private $watchlist;

  /** @var YAML */
  private $yaml;

  public static function getModuleInfo() {
    return [
      'title' => 'RockMigrations',
      'version' => '0.10.8',
      'summary' => 'The ultimate Automation and Deployment-Tool for ProcessWire',
      'autoload' => 2,
      'singular' => true,
      'icon' => 'magic',
      'requires' => [],
      'installs' => [],
    ];
  }

  public function __construct() {
    parent::__construct();
    $this->path = $this->wire->config->paths($this);
    $this->wire->classLoader->addNamespace("RockMigrations", __DIR__."/classes");

    $this->recorders = $this->wire(new WireArrayRM());
    $this->watchlist = $this->wire(new WireArrayRM());
    $this->lastrun = (int)$this->wire->cache->get(self::cachename);
  }

  public function init() {
    $config = $this->wire->config;
    $this->wire('rockmigrations', $this);
    if($config->debug) $this->setOutputLevel(self::outputLevelVerbose);

    // this creates folders that are necessary for PW and that might have
    // been deleted on deploy
    // for example this will create the sessions folder if it does not exist
    $this->createNeededFolders();

    // always watch + migrate /site/migrate.[yaml|json|php]
    // the third parameter makes it use the migrateNew() method
    // this will be the first file that is watched!
    $this->watch($config->paths->site."migrate", true);
    $this->watchModules();

    // disabled as of 2022-06-04
    // it created files that I did not want
    // no need to fix something that I don't need...
    // add recorders based on module settings (true=add, false=remove)
    // $time = date("Y-m-d--H:i:s");
    // $this->record($config->paths->assets.$this->className."/$time.yaml", [], !$this->saveToProject);
    // $this->record($config->paths->site."migrate.yaml", [], !$this->saveToMigrate);

    // hooks
    $this->addHookAfter("Modules::refresh", $this, "triggerMigrations");
    $this->addHookAfter("ProcessPageView::finished", $this, "triggerRecorder");
    $this->addHookBefore("Field::getInputfield", $this, "addSourceCodeButton");

    // add hooks for recording changes
    $this->addHookAfter("Fields::saved", $this, "setRecordFlag");
    $this->addHookAfter("Fields::deleted", $this, "setRecordFlag");
    $this->addHookAfter("Templates::saved", $this, "setRecordFlag");
    $this->addHookAfter("Templates::deleted", $this, "setRecordFlag");
    $this->addHookAfter("Modules::refresh", $this, "setRecordFlag");
    $this->addHookAfter("Modules::saveConfig", $this, "setRecordFlag");
    $this->addHookBefore("InputfieldForm::render", $this, "showEditInfo");
    $this->addHookBefore("InputfieldForm::render", $this, "showCopyCode");

    // files on demand feature
    $this->loadFilesOnDemand();
  }

  public function ready() {
    $this->migrateWatchfiles();
    $this->changeFooter();
  }

  /** ########## tools ########## */

  // ckeditor settings
  const cke_formatTags = 'p;h2;h3;h4;';
  const cke_contentCss = '/site/templates/bundle/main.css';
  const cke_stylesSet = 'customstyles:/site/templates/customstyles.js';
  const cke_customOptions = '{"bodyClass":"p-4 prose max-w-full"}';
  const cke_toggles = [
    InputfieldCKEditor::toggleCleanDIV, // convert <div> to <p>
    InputfieldCKEditor::toggleCleanP, // remove empty paragraphs
    InputfieldCKEditor::toggleCleanNBSP, // remove &nbsp;
  ];
  const cke_toolbarLinks = "PWLink, Unlink, Anchor";
  const cke_toolbarMinimal = "Format, Styles,
    Bold, Italic, RemoveFormat";
  const cke_toolbarSimple = "Format, Styles,
    JustifyLeft, JustifyCenter, JustifyRight,
    Bold, Italic, RemoveFormat,
    NumberedList, BulletedList, Blockquote,
    PWLink, Unlink, Anchor";
  const cke_toolbarDefault = "Format, Styles
    JustifyLeft, JustifyCenter, JustifyRight, JustifyBlock
    Bold, Italic, Underline, RemoveFormat
    NumberedList, BulletedList, Blockquote
    PWLink, Unlink, Anchor
    PWImage, Table, HorizontalRule, SpecialChar";
  const cke_toolbarFull = "Format, Styles,
    Bold, Italic, Underline, RemoveFormat
    JustifyLeft, JustifyCenter, JustifyRight, JustifyBlock
    TransformTextToLowercase,TransformTextToUppercase,TransformTextCapitalize
    TextColor,BGColor
    NumberedList, BulletedList, Blockquote
    PWLink, Unlink, Anchor
    PWImage, Table, HorizontalRule, SpecialChar
    PasteText, PasteFromWord
    Scayt, -, Source
    facebookvideo";

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
  public function addAfter($wrapper, $existingItem, $newItem) {
    if(!$existingItem instanceof Inputfield) $existingItem = $wrapper->get($existingItem);
    $wrapper->add($newItem);
    $newItem = $wrapper->children()->last();
    $wrapper->insertAfter($newItem, $existingItem);
    return $newItem;
  }

  /**
   * Add sourcecode button to ckeditor fields for superuser
   * @return void
   */
  public function addSourceCodeButton(HookEvent $event) {
    if(!$this->wire->user->isSuperuser()) return;
    $field = $event->object;
    if(!$field->toolbar) return;
    $field->toolbar .= ",Source";
  }

  /**
   * Remove non-breaking spaces in string
   * @return string
   */
  public function regularSpaces($str) {
    return preg_replace('/\xc2\xa0/', ' ', $str);
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
  public function setPageNameFromTitle($template) {
    $template = $this->wire->templates->get((string)$template);
    if(!$template) return;
    $tpl = "template=$template";
    $this->addHookAfter("Pages::saveReady($tpl,id>0)", function(HookEvent $event) {
      /** @var Page $page */
      $page = $event->arguments(0);
      $langs = $this->wire->languages;
      if($langs) {
        foreach($langs as $lang) {
          $prop = $lang->isDefault() ? "name" : "name$lang";
          $old = $page->get($prop);
          $new = $page->getLanguageValue($lang, "title");
          $new = $event->sanitizer->pageNameTranslate($new);
          if($new AND $old!=$new) {
            $new = $event->wire->pages->names()->uniquePageName($new, $page);
            $page->set($prop, $new);
            $this->message($this->_("Page name updated to $new ($lang->name)"));
          }
        }
      }
      else {
        $old = $page->name;
        $new = $event->sanitizer->pageNameTranslate($page->title);
        if($new AND $old!=$new) {
          $new = $event->wire->pages->names()->uniquePageName($new, $page);
          $page->name = $new;
          $this->message($this->_("Page name updated to $new"));
        }
      }
    });
    $this->addHookAfter("ProcessPageEdit::buildForm", function(HookEvent $event) use($template) {
      $page = $event->object->getPage();
      if($page->template != $template) return;
      $form = $event->return;
      if($f = $form->get('_pw_page_name')) {
        $f->notes = $this->_('Page name will be set automatically from page title on save.');
      }
    });
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
  public function wrapFields(InputfieldWrapper $form, array $fields, array $fieldset) {
    $_fields = [];
    $last = false;
    foreach($fields as $k=>$v) {
      $noLast = false;
      $field = $v;
      $fieldData = null;
      if(is_string($k)) {
        $field = $k;
        $fieldData = $v;
      }

      if(is_array($field)) {
        $form->add($field);
        $field = $form->children()->last();
        $noLast = true;
      }

      if(!$field instanceof Inputfield) $field = $form->get((string)$field);
      if($fieldData) $field->setArray($fieldData);
      if($field instanceof Inputfield) {
        $_fields[] = $field;

        // we update the "last" variable to be the current field
        // we do not use runtime fields (applied via array syntax)
        // this ensures that the wrapper is at the same position where
        // the field of the form was
        if(!$noLast) $last = $field;
      }
    }

    // no fields, no render
    // this can be the case in modal windows when the page editor is called
    // with a ?field or ?fields get parameter to only render specific fields
    if(!count($_fields)) return;

    /** @var InputfieldFieldset $f */
    $fs = $this->wire('modules')->get('InputfieldFieldset');
    foreach($fieldset as $k=>$v) $fs->$k = $v;
    if($last) $form->insertAfter($fs, $last);
    else $form->add($fs);

    // now remove fields from the form and add them to the fieldset
    foreach($_fields as $f) {
      // if the field is a runtime only field we add a temporary name
      // otherwise the remove causes an endless loop
      if(!$f->name) $f->name = uniqid();
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
  public function addFieldToTemplate($field, $template, $afterfield = null, $beforefield = null) {
    $field = $this->getField($field);
    if(!$field) return; // logging is done in getField()
    $template = $this->getTemplate($template);
    if(!$template) return; // logging is done in getField()

    $afterfield = $this->getField($afterfield);
    $beforefield = $this->getField($beforefield);
    $fg = $template->fieldgroup; /** @var Fieldgroup $fg */

    if($afterfield) $fg->insertAfter($field, $afterfield);
    elseif($beforefield) $fg->insertBefore($field, $beforefield);
    else $fg->add($field);

    // add end field for fieldsets
    if($field->type instanceof FieldtypeFieldsetOpen
      AND !$field->type instanceof FieldtypeFieldsetClose) {
      $closer = $field->type->getFieldsetCloseField($field, false);
      $this->addFieldToTemplate($closer, $template, $field);
    }

    $fg->save();
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
  public function addFieldsToTemplate($fields, $template, $sortFields = false) {
    foreach($fields as $k=>$v) {
      // if the key is an integer, it's a simple field
      if(is_int($k)) $this->addFieldToTemplate((string)$v, $template);
      else $this->addFieldToTemplate((string)$k, $template, $v);
    }
    if($sortFields) $this->setFieldOrder($fields, $template);
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
  public function addLanguage(string $name, string $title = null) {
    // Make sure Language Support is installed
    $languages = $this->addLanguageSupport();
    if(!$languages) return $this->log("Failed installing LanguageSupport");

    $lang = $this->getLanguage($name);
    if(!$lang->id) {
      $lang = $languages->add($name);
      $languages->reloadLanguages();
    }
    if($title) $lang->setAndSave('title', $title);
    return $lang;
  }

  /**
   * Install the languagesupport module
   * @return Languages
   */
  public function addLanguageSupport() {
    if(!$this->modules->isInstalled("LanguageSupport")) {
      $this->wire->pages->setOutputFormatting(false);
      $ls = $this->installModule("LanguageSupport", ['force' => true]);
      if(!$this->wire->languages) $ls->init();
    }
    return $this->wire->languages;
  }

  /**
   * Add magic methods to this page object
   * @param Page $obj
   * @return void
   */
  public function addMagicMethods($obj) {

    if(method_exists($obj, "editForm")) {
      $this->wire->addHookAfter("ProcessPageEdit::buildForm", function($event) use($obj) {
        $page = $event->object->getPage();
        if($obj->className !== $page->className) return;
        $form = $event->return;
        $page->editForm($form, $page);
      });
    }

    if(method_exists($obj, "editFormContent")) {
      $this->wire->addHookAfter("ProcessPageEdit::buildFormContent", function($event) use($obj) {
        $page = $event->object->getPage();
        if($obj->className !== $page->className) return;
        $form = $event->return;
        $page->editFormContent($form, $page);
      });
    }

    if(method_exists($obj, "editFormSettings")) {
      $this->wire->addHookAfter("ProcessPageEdit::buildFormSettings", function($event) use($obj) {
        $page = $event->object->getPage();
        if($obj->className !== $page->className) return;
        $form = $event->return;
        $page->editFormSettings($form, $page);
      });
    }

    // execute onSaved on every save
    // this will also fire when id=0
    if(method_exists($obj, "onSaved")) {
      $this->wire->addHookAfter("Pages::saved", function($event) use($obj) {
        $page = $event->arguments(0);
        if($obj->className !== $page->className) return;
        $page->onSaved();
      });
    }

    // execute onSaveReady on every save
    // this will also fire when id=0
    if(method_exists($obj, "onSaveReady")) {
      $this->wire->addHookAfter("Pages::saveReady", function($event) use($obj) {
        $page = $event->arguments(0);
        if($obj->className !== $page->className) return;
        $page->onSaveReady();
      });
    }

    // execute onCreate on saveReady when id=0
    if(method_exists($obj, "onCreate")) {
      $this->wire->addHookAfter("Pages::saveReady", function($event) use($obj) {
        $page = $event->arguments(0);
        if($page->id) return;
        if($obj->className !== $page->className) return;
        $page->onCreate();
      });
    }

    // execute onAdded on saved when id=0
    if(method_exists($obj, "onAdded")) {
      $this->wire->addHookAfter("Pages::added", function($event) use($obj) {
        $page = $event->arguments(0);
        if($obj->className !== $page->className) return;
        $page->onAdded();
      });
    }

  }

  /**
   * Add a permission to given role
   *
   * @param string|int $permission
   * @param string|int $role
   * @return boolean
   */
  public function addPermissionToRole($permission, $role) {
    $role = $this->getRole($role);
    if(!$role) return $this->log("Role $role not found");
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
  public function addRoleToUser($role, $user) {
    $role = $this->getRole($role);
    $user = $this->getUser($user);
    $msg = "Cannot add role to user";
    if(!$role) return $this->log("$msg - role not found");
    if(!$user) return $this->log("$msg - user not found");
    $user->of(false);
    $user->addRole($role);
    $user->save();
  }

  /**
   * Add given permission to given template for given role
   *
   * Example for single template:
   * $rm->addTemplateAccess("my-template", "my-role", "edit");
   *
   * Example for multiple templates:
   * $rm->addTemplateAccess([
   *   'home',
   *   'basic-page',
   * ], "guest", "view");
   *
   * @return void
   */
  public function addTemplateAccess($templates, $role, $acc) {
    if(!$role = $this->getRole($role)) return;
    if(!is_array($templates)) $templates = [$templates];
    foreach($templates as $tpl) {
      $tpl = $this->getTemplate($tpl);
      if(!$tpl) continue; // log is done above
      $tpl->addRole($role, $acc);
      $tpl->save();
    }
  }

  /**
   * Register autoloader for all classes in given folder
   * This will NOT trigger init() or ready()
   * You can also use $rm->initClasses() with setting autoload=true
   */
  public function autoload($path, $namespace) {
    $path = Paths::normalizeSeparators($path);
    spl_autoload_register(function($class) use($path, $namespace) {
      if(strpos($class, "$namespace\\") !== 0) return;
      $name = substr($class, strlen($namespace)+1);
      $file = "$path/$name.php";
      if(is_file($file)) require_once($file);
    });
  }

  /**
   * Get basename of file or object
   * @return string
   */
  public function basename($file) {
    return basename($this->filePath($file));
  }

  /**
   * Add deployment info to backend pages
   */
  public function changeFooter() {
    if($this->wire->page->template != 'admin') return;
    $str = $this->wire->config->httpHost;
    $time = date("Y-m-d H:i:s", filemtime($this->wire->config->paths->root));
    if($this->wire->user->isSuperuser()) {
      $dir = $this->wire->config->paths->root;
      $str = "<span title='$dir @ $time' uk-tooltip>$str</span>";
    }
    $this->wire->addHookAfter('AdminThemeUikit::renderFile', function($event) use($str) {
      $file = $event->arguments(0); // full path/file being rendered
      if(basename($file) !== '_footer.php') return;
      $event->return = str_replace("ProcessWire", $str, $event->return);
    });
  }

  /**
   * Create a field of the given type
   *
   * If run multiple times it will only update field data.
   *
   * Usage:
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
  public function createField($name, $type, $options = null) {
    if(is_array($type)) {
      $options = $type;
      $type = $type['type'];
    }
    $field = $this->getField($name, true);

    // field does not exist
    if(!$field) {
      // get type
      $type = $this->getFieldtype($type);
      if(!$type) return; // logging above

      // create the new field
      if(strtolower($name) !== $name) throw new WireException("Fieldname must be lowercase!");
      $name = strtolower($name);
      $field = $this->wire(new Field());
      $field->type = $type;
      $field->name = $name;
      $field->label = $name; // set label (mandatory since ~3.0.172)
      $field->save();

      // create end field for fieldsets
      if($field->type instanceof FieldtypeFieldsetOpen) {
        $field->type->getFieldsetCloseField($field, true);
      }

      // this will auto-generate the repeater template
      if($field->type instanceof FieldtypeRepeater) {
        $field->type->getRepeaterTemplate($field);
      }
    }

    // set options
    if($options) $field = $this->setFieldData($field, $options);

    return $field;
  }

  private function createNeededFolders() {
    $dir = $this->wire->config->paths->assets."sessions";
    if(!is_dir($dir)) $this->wire->files->mkdir($dir);
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
  public function createPage(string $title, $name = null, $template, $parent, array $status = [], array $data = []) {
    // create pagename from page title if it is not set
    if(!$name) $name = $this->sanitizer->pageNameTranslate($title);

    $log = "Parent $parent not found";
    $parent = $this->getPage($parent);
    if(!$parent->id) return $this->log($log);

    // get page if it exists
    $page = $this->getPage([
      'name' => $name,
      'template' => $template,
      'parent' => $parent,
    ], true);

    if($page AND $page->id) {
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
  public function createPermission($name, $description = null) {
    if(!$perm = $this->getPermission($name)) {
      $perm = $this->wire->permissions->add($name);
      $this->log("Created permission $name");
    }
    $perm->setAndSave('title', $description);
    return $perm;
  }

  /**
   * Create role with given name
   * @param string $name
   * @param array $permissions
   * @return Role|null
   */
  public function createRole($name, $permissions = []) {
    if(!$name) return $this->log("Define a name for the role!");

    $role = $this->getRole($name);
    if(!$role) $role = $this->roles->add($name);
    foreach($permissions as $permission) {
      $this->addPermissionToRole($permission, $role);
    }

    return $role;
  }

  /**
   * Helper to create webmaster role
   * @return Role
   */
  public function createRoleWebmaster($name = 'webmaster', $permissions = null) {
    if(!$permissions) $permissions = [
      'page-edit',
      'page-edit-front',
      'page-delete',
      'page-move',
      'page-sort',
      'rockfrontend-alfred',
    ];
    return $this->createRole($name, $permissions);
  }

  /**
   * Create a new ProcessWire Template
   *
   * @param string $name
   * @param bool $addTitlefield
   * @return void
   */
  public function createTemplate($name, $addTitlefield = true) {
    $t = $this->templates->get((string)$name);
    if(!$t) {
      // create new fieldgroup
      $fg = $this->wire(new Fieldgroup());
      $fg->name = $name;
      $fg->save();

      // create new template
      $t = $this->wire(new Template());
      $t->name = $name;
      $t->fieldgroup = $fg;
      $t->save();
    }

    // add title field to this template
    if($addTitlefield) $this->addFieldToTemplate('title', $t);

    return $t;
  }

  /**
   * Create or return a PW user
   *
   * Usage:
   * $rm->createUser('demo', 'password');
   *
   * $rm->createUser('demo', [
   *   'roles' => ['webmaster'],
   *   'password' => 'MySecretPassword',
   * ]);
   *
   * @param string $username
   * @param array $data
   * @return User
   */
  public function createUser($username, $data = []) {
    $user = $this->users->get($username);
    if(!$user->id) $user = $this->wire->users->add($username);
    $this->setUserData($user, $data);
    return $user;
  }

  /**
   * Create view file for template (if it does not exist already)
   * @return void
   */
  public function createViewFile($template, $content = "\n") {
    $template = $this->getTemplate($template);
    $file = $this->wire->config->paths->templates.$template->name.".php";
    if(is_file($content)) $content = file_get_contents($content);
    if(!is_file($file)) $this->wire->files->filePutContents($file, $content);
  }

  /**
   * Delete the given field
   * @param mixed $name
   * @param bool $quiet
   * @return void
   */
  public function deleteField($name, $quiet = false) {
    $field = $this->getField($name, $quiet);
    if(!$field) return; // logging in getField()

    // delete _END field for fieldsets first
    if($field->type instanceof FieldtypeFieldsetOpen) {
      $closer = $field->type->getFieldsetCloseField($field, false);
      $this->deleteField($closer, $quiet);
    }

    // make sure we can delete the field by removing all flags
    $field->flags = Field::flagSystemOverride;
    $field->flags = 0;

    // remove the field from all fieldgroups
    foreach($this->fieldgroups as $fieldgroup) {
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
  public function deleteFields($fields, $quiet = false) {
    if(is_string($fields)) $fields = $this->wire->fields->find($fields);
    foreach($fields as $field) $this->deleteField($field, $quiet);
  }

  /**
   * Deletes a language
   * @param mixed $language
   * @return void
   */
  public function deleteLanguage($language, $quiet = false) {
    if(!$lang = $this->getLanguage($language, $quiet)) return;
    $this->wire->languages->delete($lang);
  }

  /**
   * Delete module
   * This deletes the module files and then removes the entry in the modules
   * table. Removing the module via uninstall() did cause an endless loop.
   * @param mixed $name
   * @return void
   */
  public function deleteModule($name, $path = null) {
    $name = (string)$name;
    if($this->wire->modules->isInstalled($name)) $this->uninstallModule($name);
    if(!$path) $path = $this->wire->config->paths->siteModules.$name;
    if(is_dir($path)) $this->wire->files->rmdir($path, true);
    $this->wire->database->exec("DELETE FROM modules WHERE class = '$name'");
  }

  /**
   * Delete the given page including all children.
   *
   * @param Page|string $page
   * @return void
   */
  public function deletePage($page, $quiet = false) {
    if(!$page = $this->getPage($page, $quiet)) return;

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
    foreach($all as $p) {
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
  public function deletePermission($permission, $quiet = false) {
    if(!$permission = $this->getPermission($permission, $quiet)) return;
    $this->permissions->delete($permission);
  }

  /**
   * Delete the given role
   * @param Role|string $role
   * @param bool $quiet
   * @return void
   */
  public function deleteRole($role, $quiet = false) {
    if(!$role = $this->getRole($role, $quiet)) return;
    $this->roles->delete($role);
  }

  /**
   * Delete a ProcessWire Template
   * @param mixed $tpl
   * @param bool $quiet
   * @return void
   */
  public function deleteTemplate($tpl, $quiet = false) {
    $template = $this->getTemplate($tpl, $quiet);
    if(!$template) return;

    // remove all pages having this template
    foreach($this->pages->find("template=$template, include=all") as $p) {
      $this->deletePage($p);
    }

    // make sure we can delete the template by removing all flags
    $template->flags = Template::flagSystemOverride;
    $template->flags = 0;

    // delete the template
    $this->templates->delete($template);

    // delete the fieldgroup
    $fg = $this->fieldgroups->get((string)$tpl);
    if($fg) $this->fieldgroups->delete($fg);
  }

  /**
   * Delete templates
   *
   * Usage
   * $rm->deleteTemplates("tags=YourModule");
   *
   * @param string $selector
   */
  public function deleteTemplates($selector, $quiet = false) {
    $templates = $this->wire->templates->find($selector);
    foreach($templates as $tpl) $this->deleteTemplate($tpl, $quiet);
  }

  /**
   * Delete a PW user
   *
   * @param string $username
   * @return void
   */
  public function deleteUser($username, $quiet = false) {
    if(!$user = $this->getUser($username, $quiet)) return;
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
  public function disableModule($name) {
    $this->wire->modules->setFlag((string)$name, Modules::flagsDisabled, true);
  }

  /**
   * Download module from url
   *
   * @param string $url
   * @return void
   */
  public function downloadModule($url) {
    if(!class_exists('ProcessWire\ProcessModuleInstall')) {
      require $this->config->paths->modules
        ."Process/ProcessModule/ProcessModuleInstall.php";
    }
    $install = $this->wire(new ProcessModuleInstall());
    $install->downloadModule($url);
  }

  /**
   * Enable all languages for given page
   *
   * @param mixed $page
   * @return void
   */
  public function enableAllLanguagesForPage($page) {
    if(!$page) return;
    $page = $this->getPage($page);
    foreach($this->languages ?: [] as $lang) $page->set("status$lang", 1);
    $page->save();
  }

  /**
   * Find migration file (tries all extensions)
   * @return string|false
   */
  public function file($path) {
    $path = Paths::normalizeSeparators($path);
    if(is_file($path)) return $path;
    foreach(['yaml', 'json', 'php'] as $ext) {
      if(is_file($f = "$path.$ext")) return $f;
    }
    return false;
  }

  /**
   * Get IDE edit link for file
   * @return string
   */
  public function fileEditLink($file) {
    $file = $this->filePath($file);
    $tracy = $this->wire->config->tracy;
    if(is_array($tracy) and array_key_exists('localRootPath', $tracy))
      $root = $tracy['localRootPath'];
    else $root = $this->wire->config->paths->root;
    return "vscode://file/"
      .str_replace($this->wire->config->paths->root, $root, $file);
  }

  /**
   * Get filepath of file or object
   * @return string
   */
  public function filePath($file, $relative = false) {
    if(is_object($file)) {
      $reflector = new \ReflectionClass($file);
      $file = $reflector->getFileName();
    }
    if($relative) $file = str_replace(
      $this->wire->config->paths->root,
      $this->wire->config->urls->root,
      $file
    );
    return $file;
  }

  /**
   * DEPRECATED
   */
  public function fireOnRefresh($module, $method = null, $priority = []) {
    $trace = debug_backtrace()[0];
    $trace = $trace['file'].":".$trace['line'];
    $this->warning("fireOnRefresh is DEPRECATED and does not work any more!
      RockMigrations will migrate all watched files on Modules::refresh automatically. $trace");
    return;
  }

  /**
   * Get changed watchfiles
   * @return array
   */
  public function getChangedFiles() {
    $changed = [];
    foreach($this->watchlist as $file) {
      // remove the hash from file path
      // hashes are needed for multiple callbacks living on the same file
      $path = explode(":", $file->path)[0];
      $m = filemtime($path);
      if($m>$this->lastrun) $changed[] = $file->path;
    }
    return $changed;
  }

  /**
   * Get code (export data)
   * @return string
   */
  public function getCode($item) {
    if($item instanceof Field) {
      ob_start();
      $data = $item->getExportData();
      unset($data['id']);
      unset($data['name']);
      unset($data['rockmigrations']);

      // we have a different syntax for options of an options field
      if($item->type instanceof FieldtypeOptions) {
        $options = [];
        foreach($item->type->manager->getOptions($item) as $opt) {
          $options[$opt->id] =
            ($opt->value ? $opt->value."|" : "").
            "{$opt->title}";
        }
        unset($data['export_options']);
        $data['options'] = $options;

        // for multilang fields we also set the translated labels
        if($this->wire->languages) {
          $arr = [];
          foreach($this->wire->languages as $lang) {
            if($lang->isDefault()) continue;
            $options = [];
            foreach($item->type->manager->getOptions($item) as $opt) {
              $options[$opt->id] =
                ($opt->value ? $opt->value."|" : "").
                $opt->get("title$lang|title");
            }
            $arr[$lang->name] = $options;
          }
          $data['optionLabels'] = $arr;
        }
      }
    }
    elseif($item instanceof Template) {
      $data = $item->getExportData();
      unset($data['id']);
      unset($data['name']);
      unset($data['rockmigrations']);
    }
    if(array_key_exists('_rockmigrations_log', $data)) {
      unset($data['_rockmigrations_log']);
    }
    $code = $this->varexport($data);
    return "'{$item->name}' => $code,";
  }

  /**
   * Convert an array into a WireData config object
   * @return WireData
   */
  public function getConfigObject(array $config) {
    // this ensures that $config->fields is an empty array rather than
    // a processwire fields object (proxied from the wire object)
    $conf = $this->wire(new WireData()); /** @var WireData $conf */
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
  public function getField($name, $quiet = false) {
    if(!$name) return false; // for addfieldtotemplate
    $field = $this->fields->get((string)$name);
    if($field) return $field;
    if(!$quiet) $this->log("Field $name not found");
    return false;
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
  public function getFieldtype($type) {
    if($type instanceof Fieldtype) return $type;
    $modules = $this->wire->modules;
    $name = (string)$type;

    // first we try to get the module by name
    // $rm->getFieldtype('page') will request the page module!
    // we make sure not to auto-install non-fieldtype modules!
    if($modules->isInstalled($name)) {
      $module = $modules->get($name);
      if($module instanceof Fieldtype) return $module;
    }

    // prepend Fieldtype (page --> FieldtypePage)
    // now we try to get the module and install it
    $fname = $name;
    if(strpos($fname, "Fieldtype") !== 0) $fname = "Fieldtype".ucfirst($fname);
    if(!$modules->isInstalled($fname)) @$modules->install($fname);
    $module = $modules->get($fname);
    if($module) return $module;

    $this->log("No fieldtype found for $type (also tried $fname)");
    return false;
  }

  /**
   * Get language
   * Returns FALSE if language is not found
   * @return Language|false
   */
  public function getLanguage($data, $quiet = false) {
    $lang = $this->wire->languages->get((string)$data);
    if($lang and $lang->id) return $lang;
    if(!$quiet) $this->log("Language $data not found");
    return false;
  }

  /**
   * Get language zip url
   * @param string $code 2-Letter Language Code
   * @return string
   */
  public function getLanguageZip($code) {
    if(strtoupper($code) == 'DE') return "https://github.com/jmartsch/pw-lang-de/archive/refs/heads/main.zip";
    if(strtoupper($code) == 'FI') return "https://github.com/apeisa/Finnish-ProcessWire/archive/refs/heads/master.zip";
    return $code;
  }

  /**
   * Get page
   * Returns FALSE if page is not found
   * @return Page|false
   */
  public function getPage($data, $quiet = false) {
    if($data instanceof Page) return $data;
    $page = $this->wire->pages->get($data);
    if($page->id) return $page;
    if(is_array($data)) $data = "#array#";
    if(!$quiet) $this->log("Page $data not found");
    return false;
  }

  /**
   * Get permission
   * Returns FALSE if permission does not exist
   * @param mixed $data
   * @param bool $quiet
   * @return Permission|false
   */
  public function getPermission($data, $quiet = false) {
    $permission = $this->permissions->get((string)$data);
    if($permission AND $permission->id) return $permission;
    if(!$quiet) $this->log("Permission $data not found");
    return false;
  }

  /**
   * Get role
   * Returns false if the role does not exist
   * @param Role|string $name
   * @param bool $quiet
   * @return mixed
   */
  public function getRole($name, $quiet = false) {
    if(!$name) return false;
    $role = $this->wire->roles->get((string)$name);
    if($role AND $role->id) return $role;
    if(!$quiet) $this->log("Role $name not found");
    return false;
  }

  /**
   * Get trace and return last entry from trace that is within the site folder
   * @return string
   */
  public function getTrace($msg = null) {
    $self = Paths::normalizeSeparators(__FILE__);
    $trace = debug_backtrace();
    $paths = $this->wire->config->paths;
    $trace = array_filter($trace, function($item) use($paths, $self) {
      if(!array_key_exists('file', $item)) return false; // when run from CLI
      $file = $item['file'];
      if($file === $self) {
        if($item['function']=='getTrace') return false;
      }
      if($file === $paths->templates."admin.php") return false;
      if($file === $paths->root."index.php") return false;
      if(strpos($file, $paths->wire)===0) return false;
      return true;
    });
    // bd($trace, $msg);

    // return first trace entry that does not come from RockMigrations.module.php
    $first = null;
    foreach($trace as $k=>$v) {
      if(!$first) $first = $v;
      if($v['file'] !== $self) return $v;
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
  public function getTraceLog() {
    $trace = date("--- Y-m-d H:i:s ---")."\n";
    // bd(debug_backtrace());
    foreach(debug_backtrace() as $line) {
      if(!array_key_exists('file', $line)) continue;
      if(strpos($line['file'], $this->wire->config->paths->wire) !== false) continue;
      $base = basename($line['file']);
      if($base == 'index.php') continue;
      if($base == 'admin.php') continue;
      if($base == 'RockMigrations.module.php') continue;
      $trace .= "$base::".$line['function']." (".$line['line'].")\n";
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
  public function getUser($name, $quiet = false) {
    if(!$name) return false;
    $role = $this->wire->users->get((string)$name);
    if($role AND $role->id) return $role;
    if(!$quiet) $this->log("User $name not found");
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
  public function getTemplate($name, $quiet = false) {
    $template = $this->templates->get((string)$name);
    if($template AND $template->id) return $template;
    if(!$quiet) $this->log("Template $name not found");
    return false;
  }

  /**
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
  public function initClasses($path, $namespace = "ProcessWire", $autoload = true) {
    if($autoload) $this->autoload($path, $namespace);
    foreach($this->files->find($path, ['extensions' => ['php']]) as $file) {
      $class = pathinfo($file, PATHINFO_FILENAME);

      // skip files that start with an underscore
      // this is to make it possible to add abstract classes to your folder
      // an example could be /site/classes/_MyPage.php
      if(strpos($class, "_")===0) continue;

      if($namespace) $class = "\\$namespace\\$class";
      $tmp = new $class();
      if(method_exists($tmp, "init")) $tmp->init();
      $this->addMagicMethods($tmp);
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
  public function installModule($name, $conf = [], $options = []) {
    if(is_string($conf)) $options['url'] = $conf;
    if(is_string($options)) $options = ['url' => $options];
    if(!$options) $options = [];

    $opt = $this->wire(new WireData()); /** @var WireData $opt */
    $opt->setArray([
      'url' => '',
      'conf' => $conf,

      // a setting of true forces the module to be installed even if
      // dependencies are not met
      'force' => false,
    ]);
    $opt->setArray($options);

    $module = $this->wire->modules->get($name);
    if(!$module) $module = $this->modules->install($name, ['force' => $opt->force]);
    if(!$module) {
      // if an url was provided, download the module
      if($opt->url) $this->downloadModule($opt->url);

      // install the module
      $module = $this->modules->install($name, ['force' => $opt->force]);
      $this->log("Installed module $module");
    }
    if(is_array($opt->conf) AND count($opt->conf)) {
      $this->setModuleConfig($module, $opt->conf);
    }
    return $module;
  }

  /**
   * @return bool
   */
  public function isDebug() {
    return $this->outputLevel == self::outputLevelDebug;
  }

  /**
   * @return bool
   */
  public function isVerbose() {
    return $this->outputLevel == self::outputLevelVerbose;
  }

  /**
   * Get or set json data to file
   * @return mixed
   */
  public function json($path, $data = null) {
    if($data === null) return json_decode(file_get_contents($path));
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT));
  }

  /**
   * Get lastmodified timestamp of watchlist
   * @return int
   */
  public function lastmodified() {
    $last = 0;
    foreach($this->watchlist as $file) {
      // remove the hash from file path
      // hashes are needed for multiple callbacks living on the same file
      $path = explode(":", $file->path)[0];
      $m = filemtime($path);
      if($m>$last) $last=$m;
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
  public function loadFilesOnDemand() {
    if(!$this->wire->config->filesOnDemand) return;
    $hook = function(HookEvent $event) {
      $config = $this->wire->config;
      $file = $event->return;
      $pagefile = $event->object;
      if($pagefile->page->isTrash()) return;

      // this makes it possible to prevent downloading at runtime
      if(!$host = $this->wire->config->filesOnDemand) return;

      // convert url to disk path
      if($event->method == 'url') {
        $file = $config->paths->root.substr($file, strlen($config->urls->root));
      }

      // load file from remote if it does not exist
      if(!file_exists($file)) {
        $host = rtrim($host, "/");
        $src = "$host/site/assets/files/";
        $url = str_replace($config->paths->files, $src, $file);
        $http = $this->wire(new WireHttp()); /** @var WireHttp $http */
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
  public function log($msg, $throwException = true) {
    $trace = $this->getTrace($msg);
    $file = $trace['file'];
    $line = $trace['line'];
    $filename = pathinfo($file, PATHINFO_FILENAME);
    $traceStr = "$filename:$line";
    if($this->isVerbose()) {
      try {
        $url = TracyDebugger::createEditorLink($file, $line, $traceStr);
        $opt = ['url' => $url];
      } catch (\Throwable $th) {
        $opt = [];
      }
      if($this->wire->config->external) echo "$msg\n";
      $this->wire->log->save("RockMigrations", $msg, $opt);
    }
    elseif($this->isDebug()) {
      if($throwException) throw new WireException("$msg in $traceStr");
    }
  }

  /**
   * Log but throw no exception
   * @param string $msg
   * @return void
   */
  public function logOnly($msg) {
    $this->log($msg, false);
  }

  /**
   * Get array value
   * @return mixed
   */
  public function val($arr, $property) {
    if(!array_key_exists($property, $arr)) return;
    return $arr[$property];
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
   * @return void
   */
  public function migrate($config) {
    $config = $this->getConfigObject($config);

    // create fields+templates
    foreach($config->fields as $name=>$data) {
      // if no type is set this means that only field data was set
      // for example to update only label or icon of an existing field
      if(array_key_exists('type', $data)) $this->createField($name, $data['type']);
    }
    foreach($config->templates as $name=>$data) {
      // this check makes it possible to define templates without data
      // that means the defined templates will be created but not changed
      if(is_int($name)) $name = $data;
      $this->createTemplate($name, false);
    }
    foreach($config->roles as $name=>$data) $this->createRole($name);

    // set field+template data after they have been created
    foreach($config->fields as $name=>$data) $this->setFieldData($name, $data);
    foreach($config->templates as $name=>$data) {
      // this check makes it possible to define templates without data
      // that means the defined templates will be created but not changed
      if(!is_int($name)) $this->setTemplateData($name, $data);
    }
    foreach($config->roles as $role=>$data) {
      // set permissions for this role
      if(array_key_exists("permissions", $data)) $this->setRolePermissions($role, $data['permissions']);
      if(array_key_exists("access", $data)) {
        foreach($data['access'] as $tpl=>$access) $this->setTemplateAccess($tpl, $role, $access);
      }
    }

    // setup pages
    foreach($config->pages as $name=>$data) {
      if(isset($data['name'])) {
        $name = $data['name'];
      } elseif(is_int($name)) {
        // no name provided
        $name = uniqid();
      }

      $d = $this->wire(new WireData()); /** @var WireData $d */
      $d->setArray($data);
      $this->createPage(
        $d->title ?: $name,
        $name,
        $d->template,
        $d->parent,
        $d->status,
        $d->data);
    }
  }

  /**
   * Call $module::migrate() on modules::refresh
   * @return void
   */
  public function migrateOnRefresh(Module $module) {
    $trace = debug_backtrace()[0];
    $trace = $trace['file'].":".$trace['line'];
    $this->warning("fireOnRefresh is DEPRECATED and does not work any more!
      RockMigrations will migrate all watched files on Modules::refresh automatically. $trace");
    return;
    $this->fireOnRefresh($module);
  }

  /**
   * Run migrations of all watchfiles
   * @return void
   */
  public function migrateWatchfiles($force = false) {
    // prevent auto-migrate when CLI mode is enabled
    // $rm->run() will run either way because it sets force=true
    $cli = defined('RockMigrationsCLI');
    if($cli AND !$force) return;

    // if the noMigrate flag is set we do not run migrations
    // this makes it possible to refresh modules without triggering another
    // run of migrations!
    if($this->wire->session->noMigrate) {
      $this->wire->session->noMigrate = false;
      return;
    }

    $changed = $this->getChangedFiles();
    $run = ($force OR self::debug OR count($changed));
    if(!$run) return;

    // logging
    $this->wire->log->delete($this->className);
    if(!$cli) {
      $this->log('-------------------------------------');
      foreach($changed as $file) $this->log("Detected change in $file");
      $this->log('Running migrations from watchfiles...');
    }
    // always refresh modules before running migrations
    // this makes sure that $rm->installModule() etc will catch all new files
    $this->refresh();

    $this->updateLastrun();
    foreach($this->watchlist as $file) {
      if(!$file->migrate) continue;

      // if it is a callback we execute it
      if($callback = $file->callback) {
        $callback->__invoke($this);
        continue;
      }

      // if it is a module we call $module->migrate()
      if($module = $file->module) {
        if(method_exists($module, "migrate")) {
          $this->log("Triggering $module::migrate()");
          $module->migrate();
        }
        else {
          $this->log("Skipping $module::migrate() - method does not exist");
        }
        continue;
      }

      // if it is a pageclass we load it and call migrate()
      // the homepage get's special treatment because it is always loaded
      // by PW since it is needed for all other pages as root element
      $pageClass = $file->pageClass;
      if($file->path == $this->wire->config->paths->classes."HomePage.php") {
        // fixes Cannot declare class ProcessWire\HomePage, because the name is already in use
        $pageClass = "\\ProcessWire\\HomePage";
      }
      if($pageClass) {
        if(!class_exists($pageClass)) require_once $file->path;
        $tmp = new $pageClass();
        if(method_exists($tmp, 'migrate')) {
          $this->log("Triggering $pageClass::migrate()");
          $tmp->migrate();
        }
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
      if(is_string($migrate)) $migrate = $this->yaml($migrate);
      if(is_array($migrate)) {
        $this->log("Returned an array - trigger migrate() of "
          .print_r($migrate, true));
        $this->migrate($migrate);
      }
    }
  }

  /**
   * Move one page on top of another one
   * @return void
   */
  public function movePageAfter($page, $reference) {
    $page = $this->getPage($page);
    $ref = $this->getPage($reference);
    if(!$page->id) return $this->log("Page does not exist");
    if(!$ref->id) return $this->log("Reference does not exist");
    if($page->parent !== $ref->parent) return $this->log("Both pages must have the same parent");
    $this->wire->pages->sort($page, $ref->sort+1);
  }

  /**
   * Move one page on top of another one
   * @return void
   */
  public function movePageBefore($page, $reference) {
    $page = $this->getPage($page);
    $ref = $this->getPage($reference);
    if(!$page->id) return $this->log("Page does not exist");
    if(!$ref->id) return $this->log("Reference does not exist");
    if($page->parent !== $ref->parent) return $this->log("Both pages must have the same parent");
    $this->wire->pages->sort($page, $ref->sort);
  }

  /**
   * Execute profile
   */
  private function profileExecute() {
    $profile = $this->wire->input->post('profile', 'filename');
    foreach($this->profiles() as $path=>$label) {
      if($label !== $profile) continue;
      $this->wire->files->include($path);
      $this->wire->message("Executed profile $label");
      return true;
    }
    return false;
  }

  private function profiles() {
    $profiles = [];
    $opt = ['extensions' => ['php']];
    foreach($this->wire->files->find(__DIR__."/profiles", $opt) as $file) {
      $profiles[$file] = basename($file);
    }
    $path = $this->wire->config->paths->assets."RockMigrations/profiles";
    foreach($this->wire->files->find($path, $opt) as $file) {
      $profiles[$file] = basename($file);
    }
    return $profiles;
  }

  /**
   * Record settings to file
   *
   * Usage:
   * $rm->record("/path/to/file.yaml");
   *
   * $rm->record("/path/to/file.json", ['type'=>'json']);
   *
   * @param string $path
   * @param array $options
   * @return void
   */
  public function record($path, $options = [], $remove = false) {
    if($remove) {
      $this->recorders->remove($this->recorders->get("path=$path"));
      return;
    }
    require_once($this->path."RecorderFile.php");
    $data = $this->wire(new RecorderFile()); /** @var RecorderFile $data */
    $data->setArray([
      'path' => $path,
      'type' => 'yaml', // other options: php, json
      'system' => false, // dump system fields and templates?
    ]);
    $data->setArray($options);
    $this->recorders->add($data);
  }

  /**
   * Refresh modules
   */
  public function refresh() {
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
  public function removeFieldFromTemplate($field, $template, $force = false) {
    $field = $this->getField($field);
    if(!$field) return;
    $template = $this->getTemplate($template);
    if(!$template) return;

    $fg = $template->fieldgroup; /** @var Fieldgroup $fg */
    if($force) $field->flags = 0;

    if(!$fg->get($field->name)) return;

    $fg->remove($field);
    $fg->save();
    $this->log("Removed field $field from template $template");
  }

  /**
   * See method above
   */
  public function removeFieldsFromTemplate($fields, $template, $force = false) {
    foreach($fields as $field) $this->removeFieldFromTemplate($field, $template, $force);
  }

  /**
   * Remove access from template for given role
   * @return void
   */
  public function removeTemplateAccess($tpl, $role) {
    if(!$role = $this->getRole($role)) return;
    if(!$tpl = $this->getTemplate($tpl)) return;
    $tpl->removeRole($role, "all");
    $tpl->save();
  }

  /**
   * Remove all template context field settings
   * @return void
   */
  public function removeTemplateContext($tpl, $field) {
    $tpl = $this->getTemplate($tpl);
    $field = $this->getField($field);
    $tpl->fieldgroup->setFieldContextArray($field->id, []);
  }

  /**
   * Rename given page
   * @return void
   */
  public function renamePage($page, $newName, $quiet = false) {
    if(!$page = $this->getPage($page, $quiet)) return;
    $old = $page->name;
    $page->setAndSave('name', $newName);
    $this->log("Renamed page from $old to $newName");
  }

  /**
   * Reset "lastrun" cache to force migrations
   * @return void
   */
  public function resetCache(HookEvent $event) {
    $this->updateLastrun(0);
  }

  /**
   * Run migrations that have been attached via watch()
   * @return void
   */
  public function run() {
    $this->sudo();
    $this->migrateWatchfiles(true);
  }

  /**
   * Set the logo url of the backend logo (AdminThemeUikit)
   * @return void
   */
  public function setAdminLogoUrl($url) {
    $this->setModuleConfig("AdminThemeUikit", ['logoURL' => $url]);
  }

  /**
   * Set default options for several things in PW
   * These are opinionated defaults that I like to use in my projects!
   */
  public function setDefaults($options = []) {
    $opt = $this->wire(new WireData()); /** @var WireData $opt */
    $opt->setArray([
      'pagenameReplacements' => 'de',
      'toggleBehavior' => 1,
      'german' => true, // install german language pack
    ]);
    $opt->setArray($options);

    // set german pagename replacements
    $this->setPagenameReplacements($opt->pagenameReplacements);

    // AdminThemeUikit settings
    $this->setModuleConfig("AdminThemeUikit", [
      // use consistent inputfield clicks
      // see https://github.com/processwire/processwire/pull/169
      'toggleBehavior' => $opt->toggleBehavior,
    ]);

    $this->setModuleConfig("ProcessPageList", [
      'useTrash' => true, // show trash in tree for non superusers
    ]);

    if($opt->german) {
      // install german language pack for the default language
      // this will install language support, download the ZIP and install it
      $this->setLanguageTranslations('DE');
    }
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
  public function setFieldData($field, $data, $template = null) {
    $field = $this->getField($field);
    if(!$field) return; // logging in getField()

    // check if the field type has changed
    if(array_key_exists('type', $data)) {
      $type = $this->getFieldtype($data['type']);
      if((string)$type !== (string)$field->type) {
        $field->type = $type;
        // if we do not save the field here it will lose some data (eg icon)
        $field->save();
      }
    }

    // save trace of migration to field
    // this is shown on field edit screen
    $field->_rockmigrations_log = $this->getTraceLog();

    // prepare data array
    $optionLabels = [];
    foreach($data as $key=>$val) {

      // this makes it possible to set the template via name
      if($key === "template_id") {
        $data[$key] = $this->templates->get($val)->id;
      }

      // support repeater field array
      $contexts = [];
      if($key === "repeaterFields") {
        $fields = $data[$key];
        $addFields = [];
        $index = 0;
        foreach($fields as $i=>$_field) {
          if(is_string($i)) {
            // we've got a field with field context info here
            $fieldname = $i;
            $fielddata = $_field;
            $contexts[] = [
              $fieldname,
              $fielddata,
              $this->getRepeaterTemplate($field),
            ];
          }
          else {
            // field without field context info
            $fieldname = $_field;
          }
          $addFields[$index] = $this->fields->get((string)$fieldname)->id;
          $index++;
        }
        $data[$key] = $addFields;

        // add fields to repeater template
        if($tpl = $this->getRepeaterTemplate($field)) {
          $this->addFieldsToTemplate($addFields, $tpl);
        }

        // set field contexts now that the fields are present
        foreach($contexts as $c) {
          $this->setFieldData($c[0], $c[1], $c[2]);
        }

      }

      // add support for setting options of a select field
      // this will remove non-existing options from the field!
      if($key === "options") {
        $options = $data[$key];
        $this->setOptions($field, $options, true);

        // this prevents setting the "options" property directly to the field
        // if not done, the field shows raw option values when rendered
        unset($data['options']);
      }
      if($key === "optionLabels") {
        $optionLabels = $data['optionLabels'];
        unset($data['optionLabels']);
      }

    }

    // set data
    if(!$template) {
      // set field data directly
      foreach($data as $k=>$v) $field->set($k, $v);
    }
    else {
      // make sure the template is set as array of strings
      if(!is_array($template)) $template = [(string)$template];

      foreach($template as $t) {
        $tpl = $this->templates->get((string)$t);
        if(!$tpl) throw new WireException("Template $t not found");

        // set field data in template context
        $fg = $tpl->fieldgroup;
        $current = $fg->getFieldContextArray($field->id);
        $fg->setFieldContextArray($field->id, array_merge($current, $data));
        $fg->saveContext();
      }
    }

    // Make sure Table field actually updates database schema
    if($field->type == "FieldtypeTable") {
      $fieldtypeTable = $field->getFieldtype();
      $fieldtypeTable->_checkSchema($field, true); // Commit changes
    }

    $field->save();

    // update options field labels
    if(count($optionLabels)) {
      // git available options from default language
      // note that the options must be set BEFORE the title translations
      $options = $field->type->getOptions($field);

      // setup the default language options array
      $arr = [];
      foreach($options as $option) {
        $arr[$option->id] = $option->value."|".$option->title;
      }

      $labels = [];
      $labels[$this->wire->languages->getDefault()->name] = $arr;
      $labels = array_merge($labels, $optionLabels);

      $strArray = [];
      foreach($labels as $langName=>$langLabels) {
        $langLabels = array_values($langLabels);
        $lang = $this->wire->languages->get("name=$langName");
        $i = 0;
        $str = '';
        foreach($options as $option) {
          $str .= $option->id."=".$option->value."|".$langLabels[$i]."\n";
          $i++;
        }
        $strArray[$lang->id] = $str;
      }
      // db($strArray);
      $this->setOptionTitles($field, $strArray, true);
      $field->save();
    }

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
  public function setFieldOrder($fields, $name) {
    if(!$template = $this->getTemplate($name)) return;

    // make sure that all fields exist
    foreach($fields as $i=>$field) {
      if(!$this->getField($field)) unset($fields[$i]);
    }
    $fields = array_values($fields); // reset indices
    foreach($fields as $i => $field) {
      if(!$i) continue;
      $this->addFieldToTemplate($field, $template, $fields[$i-1]);
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
  public function setLanguageTranslations($translations, $lang = null) {
    $zip = $this->getLanguageZip($translations);

    // Make sure Language Support is installed
    if(!$this->wire->languages) $this->addLanguageSupport();
    if($lang) {
      $language = $this->getLanguage($lang);
      if(!$language) return; // logging above
    }
    else $language = $this->wire->languages->getDefault();
    if(!$language->id) return $this->log("No language found");
    $language->of(false);

    $this->log("Downloading $zip");
    $cache = $this->wire->config->paths->cache;
    $http = new WireHttp();
    $zipTemp = $cache . $lang . "_temp.zip";
    $http->download($zip, $zipTemp);

    // Unzip files and add .json files to language
    $items = $this->wire->files->unzip($zipTemp, $cache);
    if($cnt = count($items)) {
      $this->log("Adding $cnt new language files to language $language");
      $language->language_files->deleteAll();
      $language->save();
      foreach($items as $item) {
        if(strpos($item, ".json") === false) continue;
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
   * @param string|Module $module
   * @param array $data
   * @param bool $merge
   * @return Module|false
   */
  public function setModuleConfig($module, $data, $reset = false) {
    /** @var Module $module */
    $name = (string)$module;
    $module = $this->modules->get($name);
    if(!$module) {
      if($this->config->debug) $this->log("Module $name not found");
      return false;
    }

    // now we merge the new config data over the old config
    // if reset is TRUE we skip this step which means we may lose old config!
    if(!$reset) {
      $old = $this->wire->modules->getConfig($module);
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
  public function setOptions($field, $options, $removeOthers = false) {
    $string = "";
    foreach($options as $k=>$v) {
      if($k===0) $this->log("Option with key 0 skipped");
      else $string.="\n$k=$v";
    }
    return $this->setOptionsString($field, $string, $removeOthers);
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
  public function setOptionsString($name, $options, $removeOthers = false) {
    $field = $this->getField($name);

    /** @var SelectableOptionManager $manager */
    $manager = $this->wire(new SelectableOptionManager());

    // now set the options
    $manager->setOptionsString($field, $options, $removeOthers);
    $field->save();

    return $field;
  }

  /**
   * Internal method to set option titles in other languages
   *
   * @param Field|string $name
   * @param array $titles
   * @param bool $removeOthers
   * @return void
   */
  protected function setOptionTitles($name, $titles, $removeOthers = false) {
    $field = $this->getField($name);

    /** @var SelectableOptionManager $manager */
    $manager = $this->wire(new SelectableOptionManager());

    // now set the options
    $manager->setOptionsStringLanguages($field, $titles, $removeOthers);
    $field->save();

    return $field;
  }

  /**
   * Set output level
   * @return void
   */
  public function setOutputLevel($level) {
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
  public function setPagenameReplacements($data) {
    if(is_string($data)) {
      $file = __DIR__."/replacements/$data.txt";
      if(!is_file($file)) {
        return $this->log("File $file not found");
      }
      $replacements = explode("\n", $this->wire->files->render($file));
      $arr = [];
      foreach($replacements as $row) {
        $items = explode("=", $row);
        $arr[$items[0]] = $items[1];
      }
    }
    elseif(is_array($data)) $arr = $data;
    if(!is_array($arr)) return;
    $this->setModuleConfig("InputfieldPageName", ['replacements' => $arr]);
  }

  /**
   * Set parent child family settings for two templates
   */
  public function setParentChild($parent, $child, $onlyOneParent = true) {
    $noParents = 0; // many parents are allowed
    if($onlyOneParent) $noParents = -1;
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
   * Save config to recorder file
   * @return void
   */
  public function setRecordFlag(HookEvent $event) {
    // remove this part as of 2022-04-04
    // there is no project.yaml any more
    // we store migrations with timestamps in /site/assets/RockMigrations
    // if($event->object instanceof Modules) {
    //   // module was saved
    //   $config = $this->wire->config;
    //   $module = $event->arguments(0);
    //   if($module != 'RockMigrations') return;
    //   // set runtime properties to submitted values so that migrations
    //   // fire immediately on module save
    //   $this->record($config->paths->site."project.yaml", [],
    //     !$this->wire->input->post('saveToProject', 'int'));
    //   $this->record($config->paths->site."migrate.yaml", [],
    //     !$this->wire->input->post('saveToMigrate', 'int'));
    // }

    // set the flag to write recorders after pageview::finished
    $this->record = true;

    // we remove this hook because we have already set the flag
    $event->removeHook(null);
  }

  /**
   * Set settings of a template's access tab
   *
   * This will by default only ADD permissions and not remove them!
   * This is to be consistent with other SET methods (setData, migrate, etc)
   *
   * Usage:
   * $rm->setTemplateAccess("my-tpl", "my-role", ["view", "edit", "create", "add"]);
   *
   * Thx @apeisa https://bit.ly/2QU1b8e
   *
   * @param mixed $tpl Template
   * @param mixed $role Role
   * @param array $access Permissions, eg ['view', 'edit']
   * @param bool $remove Reset Permissions for this role?
   * @return void
   */
  public function setTemplateAccess($tpl, $role, $access, $remove = false) {
    $tpl = $this->getTemplate($tpl);
    $role = $this->getRole($role);
    if($remove) $this->removeTemplateAccess($tpl, $role);
    $this->setTemplateData($tpl, ['useRoles'=>1]);
    foreach($access as $acc) $this->addTemplateAccess($tpl, $role, $acc);
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
  public function setTemplateData($name, array $data) {
    if($name instanceof Page) $template = $name->template;
    $template = $this->templates->get((string)$name);
    if(!$template) return $this->log("Template $name not found");

    // it is possible to define templates without data:
    // rm->migrate('templates' => ['foo', 'bar'])
    if(!$data) return $template;

    // save trace of migration to field
    // this is shown on field edit screen
    $template->_rockmigrations_log = $this->getTraceLog();

    // loop template data
    foreach($data as $k=>$v) {

      // the "fields" property is a special property from RockMigrations
      // templates have "fieldgroupFields" and "fieldgroupContexts"
      if(($k === 'fields' || $k === 'fields-')) {
        if(is_array($v)) {
          $removeOthers = ($k==='fields-');
          $this->setTemplateFields($template, $v, $removeOthers);
        }
        else {
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
  public function setTemplateFields($template, $fields, $removeOthers = false) {
    $template = $this->getTemplate($template);
    if(!$template) return; // logging happens in getTemplate()

    $last = null;
    $names = [];
    foreach($fields as $name=>$data) {
      if(is_int($name) AND is_int($data)) {
        $name = $this->getField((string)$data)->name;
        $data = [];
      }
      if(is_int($name)) {
        $name = $data;
        $data = [];
      }
      $names[] = $name;
      $this->addFieldToTemplate($name, $template, $last);
      $this->setFieldData($name, $data, $template);
      $last = $name;
    }

    if(!$removeOthers) return;
    foreach($template->fields as $field) {
      $name = (string)$field;
      if(!in_array($name, $names)) {
        // remove this field from the template
        // global fields like the title field are also removed
        $this->removeFieldFromTemplate($name, $template, true);
      }
    }
  }

  /**
   * Set user data
   *
   * Usage:
   * $rm->setUserData('demo', 'mySecretPassword');
   *
   * $rm->setUserData('demo', 50); // 50 char random string
   *
   * $rm->setUserData('demo', [
   *   'roles' => [...],
   *   'password' => ...,
   *   'adminTheme' => ...,
   * ]);
   *
   * @param mixed $user
   * @param mixed $data
   * @return User
   */
  public function setUserData($user, $data) {
    $user = $this->getUser($user);
    if(!$user) return; // logging above
    $user->of(false);

    // setup data
    if(is_int($data)) {
      $rand = $this->wire(new WireRandom()); /** @var WireRandom $rand */
      $data = $rand->alphanumeric($data);
    }

    if(is_string($data)) $data = ['password' => $data];

    // setup options
    $opt = $this->wire(new WireData()); /** @var WireData $opt */
    $opt->setArray([
      'roles' => [],
      'password' => null,
      'admintheme' => 'AdminThemeUikit',
    ]);
    $opt->setArray($data);

    // set roles
    foreach($opt->roles as $role) $this->addRoleToUser($role, $user);

    // setup password
    $password = $opt->password;
    $rand = $this->wire(new WireRandom()); /** @var WireRandom $rand */
    if(is_array($opt->password)) $password = $rand->alphanumeric($opt->password);
    if(!$password) $password = $rand->alphanumeric(null, [
      'minLength' => 10,
      'maxLength' => 20,
    ]);
    $user->pass = $password;

    // save admin theme in 2 steps
    // otherwise the admin theme will not update (PW issue)
    if($opt->admintheme) $user->set('admin_theme', $opt->admintheme);

    $user->save();
    $this->log("Set user data for user $user ({$user->name})");
    return $user;
  }

  /**
   * Show edit info on field and template edit screen
   * @return void
   */
  public function showCopyCode(HookEvent $event) {
    $form = $event->object;
    if(!$id = $this->wire->input->get('id', 'int')) return;

    if($event->process == 'ProcessField') {
      $existing = $form->get('field_label');
      $item = $this->wire->fields->get($id);
    }
    elseif($event->process == 'ProcessTemplate') {
      $existing = $form->get('fieldgroup_fields');
      $item = $this->wire->templates->get($id);
    }
    else return;

    $form->add([
      'name' => '_RockMigrationsCopyInfo',
      'type' => 'markup',
      'label' => 'RockMigrations Code',
      'description' => 'This is the code you can use for your migrations:',
      'value' => "<pre><code>" . $this->getCode($item) . "</code></pre>",
      'collapsed' => Inputfield::collapsedYes,
    ]);
    $f = $form->get('_RockMigrationsCopyInfo');
    $form->remove($f);
    $form->insertBefore($f, $existing);
  }

  /**
   * Show edit info on field and template edit screen
   * @return void
   */
  public function showEditInfo(HookEvent $event) {
    $form = $event->object;
    if(!$id = $this->wire->input->get('id', 'int')) return;

    if($event->process == 'ProcessField') {
      $existing = $form->get('field_label');
      $item = $this->wire->fields->get($id);
    }
    elseif($event->process == 'ProcessTemplate') {
      $existing = $form->get('fieldgroup_fields');
      $item = $this->wire->templates->get($id);
    }
    else return;

    $form->add([
      'name' => '_RockMigrations',
      'type' => 'markup',
      'label' => 'RockMigrations',
      'description' => '<div class="uk-alert uk-alert-danger">
        ATTENTION - This item is under control of RockMigrations
        </div>
        <div>If you make any changes they might be overwritten
        by the next migration! Here is the backtrace of the last migration:</div>',
      'value' => "<small>".nl2br($item->get('_rockmigrations_log')?:'')."</small>",
    ]);
    $f = $form->get('_RockMigrations');
    $f->entityEncodeText = false;
    $form->remove($f);
    $form->insertBefore($f, $existing);
  }

  /**
   * Add submodule to project
   * This will only add the submodule if the destination path does not exist!
   * @return void
   */
  public function submodule($name, $config = [], $url = null, $dst = null) {
    $url = $url ?: 'git@github.com:baumrock';
    $dst = $dst ?: "site/modules/$name";
    $this->setModuleConfig($name, $config);
    if(is_dir($this->wire->config->paths->root.$dst)) return;
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
  public function sudo() {
    $role = $this->wire->roles->get('superuser');
    $su = $this->wire->users->get("sort=id,roles=$role");
    if(!$su->id) return $this->log("No superuser found");
    $this->wire->users->setCurrentUser($su);
  }

  /**
   * Trigger migrations after Modules::refresh
   * @return void
   */
  public function triggerMigrations(HookEvent $event) {
    // If flags are present dont attach hooks to Modules::refresh
    // See the readme for more information!
    if(defined("DontFireOnRefresh")) return;
    if($this->wire->config->DontFireOnRefresh) return;
    $this->run();
  }

  /**
   * This will trigger the recorder if the flag is set
   * @return void
   */
  public function triggerRecorder(HookEvent $event) {
    if($this->record) $this->writeRecorderFiles();
  }

  public function writeRecorderFiles() {
    $this->log('Running recorders...');
    foreach($this->recorders as $recorder) {
      $path = $recorder->path;
      $type = strtolower($recorder->type);
      $this->log("Writing config to $path");

      $arr = [
        'fields' => [],
        'templates' => [],
      ];
      foreach($this->sort($this->wire->fields) as $field) {
        if($field->flags) continue;
        $arr['fields'][$field->name] = $field->getExportData();
        unset($arr['fields'][$field->name]['id']);
      }
      foreach($this->sort($this->wire->templates) as $template) {
        if($template->flags) continue;
        $arr['templates'][$template->name] = $template->getExportData();
        unset($arr['templates'][$template->name]['id']);
      }

      if($type == 'yaml') $this->yaml($path, $arr);
      if($type == 'json') $this->json($path, $arr);
    }
    $this->updateLastrun();
  }

  /**
   * Get sorted WireArray of fields
   * @return WireArray
   */
  public function sort($data) {
    $arr = $this->wire(new WireArray()); /** @var WireArray $arr */
    foreach($data as $item) $arr->add($item);
    return $arr->sort('name');
  }

  /**
   * Update last run timestamp
   * @return void
   */
  public function updateLastrun($timestamp = null) {
    if($timestamp === null) $timestamp = time();
    $this->wire->cache->save(self::cachename, $timestamp, WireCache::expireNever);
  }

  /**
   * Uninstall module
   *
   * @param string|Module $name
   * @return void
   */
  public function uninstallModule($name) {
    $this->wire->session->noMigrate = true;
    $this->modules->uninstall((string)$name);
    $this->wire->session->noMigrate = false;
  }

  /**
   * PHP var_export() with short array syntax (square brackets) indented 2 spaces.
   *
   * NOTE: The only issue is when a string value has `=>\n[`, it will get converted to `=> [`
   * @link https://www.php.net/manual/en/function.var-export.php
   */
  function varexport($expression, $return=TRUE) {
    $export = var_export($expression, TRUE);
    $patterns = [
        "/array \(/" => '[',
        "/^([ ]*)\)(,?)$/m" => '$1]$2',
        "/=>[ ]?\n[ ]+\[/" => '=> [',
        "/([ ]*)(\'[^\']+\') => ([\[\'])/" => '$1$2 => $3',
    ];
    $export = preg_replace(array_keys($patterns), array_values($patterns), $export);
    if((bool)$return) return $export; else echo $export;
  }

  /**
   * Add file to watchlist
   *
   * Usage:
   * $rm->watch( file, path or object );
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
  public function watch($what, $migrate = true, $options = []) {
    if(!$this->watchEnabled()) return;

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
    if($what instanceof Page) {
      $reflector = new \ReflectionClass($what);
      $file = $reflector->getFileName();
      return $this->watchPageClass($file);
    }
    // instance of module
    elseif($what instanceof Module) {
      $module = $what;
      $file = $this->wire->modules->getModuleFile($module);
    }
    // callback
    elseif(!is_string($what) AND is_callable($what)) {
      $trace = debug_backtrace()[0];
      $tracefile = $trace['file'];
      $traceline = $trace['line'];
      $callback = $what;
      $file = $tracefile;
      $hash = ":".uniqid();
    }
    // path to folder
    elseif(is_dir($what)) {
      $dir = $what;

      // setup the recursive option
      // by default we do not recurse into subdirectories
      $recursive = false;
      if(array_key_exists('recursive', $options)) {
        $recursive = $options['recursive'];
      }

      $opt = [
        'extensions'=>['php'],
        'recursive' => $recursive,
      ];
      foreach($this->wire->files->find($dir, $opt) as $f) {
        $this->watch($f, $migrate, $options);
      }
    }

    // if we got no file until now we exit early
    if(!$path = $this->file($file)) return;

    // set migrate to false if extension is not valid
    // this can happen on $rm->watch("/my/file.js");
    if($migrate) {
      $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
      $allowed = ['php', 'yaml'];
      if(!in_array($ext, $allowed)) $migrate = false;
    }

    // if path already exists we skip adding this file
    $exists = $this->watchlist->get("path=$path");
    if($exists) {
      $this->log("Did not add $path to watchlist because it already exists. Called in $tracefile:$traceline");
      return;
    }

    // is it a pageclass file?
    if(array_key_exists('pageClass', $options)) {
      $pageClass = $options['pageClass'];
    }

    require_once($this->path."WatchFile.php");
    $data = $this->wire(new WatchFile()); /** @var WatchFile $data */
    $data->setArray([
      'path' => $path.$hash,
      'module' => $module,
      'callback' => $callback,
      'pageClass' => $pageClass,
      'migrate' => (float)$migrate,
      'trace' => "$tracefile:$traceline",
    ]);
    // bd($data, $data->path);

    // add item to watchlist and sort watchlist by migrate priority
    // see https://github.com/processwire/processwire-issues/issues/1528
    $this->watchlist->add($data)->sortFloat('migrate');
  }

  public function watchEnabled() {
    if($this->wire->user->isSuperuser()) return true;
    if(defined('RockMigrationsCLI')) return true;
    return false;
  }

  /**
   * Watch module migration files
   *
   * Note that files are only watched if you are logged in as superuser!
   *
   * @return void
   */
  public function watchModules() {
    if(!$this->wire->user->isSuperuser()) return;
    $path = $this->wire->config->paths->siteModules;
    foreach (new DirectoryIterator($path) as $fileInfo) {
      if(!$fileInfo->isDir()) continue;
      if($fileInfo->isDot()) continue;
      $name = $fileInfo->getFilename();
      $migrateFile = $fileInfo->getPath()."/$name/$name.migrate";
      $this->watch("$migrateFile.yaml");
      $this->watch("$migrateFile.json");
      $this->watch("$migrateFile.php");
    }
  }

  /**
   * Add a single pageclass file to watchlist
   * @return void
   */
  public function watchPageClass(string $file, $namespace = "ProcessWire", $options = [], $migrate = true) {
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
  public function watchPageClasses($path, $namespace = "ProcessWire", $options = [], $migrate = true) {
    if(!$this->watchEnabled()) return;
    if(is_dir($path)) {
      $opt = ['extensions' => ['php'], 'recursive' => 1];
      foreach($this->wire->files->find($path, $opt) as $file) {
        $this->watchPageClass($file, $namespace, $options, $migrate);
      }
    }
    elseif(is_file($path)) $this->watchPageClass($path, $namespace, $options, $migrate);
    else $this->log("Nothing to watch in $path");
  }

  /**
   * Interface to the YAML class based on Spyc
   *
   * Get YAML instance:
   * $rm->yaml();
   *
   * Get array from YAML file
   * $rm->yaml('/path/to/file.yaml');
   *
   * Save data to file
   * $rm->yaml('/path/to/file.yaml', ['foo'=>'bar']);
   *
   * @return mixed
   */
  public function yaml($path = null, $data = null) {
    require_once('spyc/Spyc.php');
    require_once('YAML.php');
    $yaml = $this->yaml ?: new YAML();
    if($path AND $data===null) return $yaml->load($path);
    elseif($path AND $data!==null) {
      $dir = dirname($path);
      if(!is_dir($dir)) $this->wire->files->mkdir($dir, true);
      return $yaml->save($path, $data);
    }
    return $yaml;
  }

  /**
  * Config inputfields
  * @param InputfieldWrapper $inputfields
  */
  public function getModuleConfigInputfields($inputfields) {

    $this->profileExecute();
    $f = new InputfieldSelect();
    $f->label = "Execute one of the existing profile migrations";
    $f->name = 'profile';
    foreach($this->profiles() as $path => $label) {
      $f->addOption($label, $label);
    }
    $path = $this->wire->config->paths->assets."RockMigrations/profiles";
    $f->notes = "You can place your own profiles in $path";
    $inputfields->add($f);

    // disabled as of 2022-06-04
    // this will create lots of useless files
    // maybe better create one project.yaml that can then be shown via git?
    // $inputfields->add([
    //   'name' => 'saveToProject',
    //   'type' => 'toggle',
    //   'label' => 'Save migration data to /site/assets/RockMigrations/[timestamp].yaml?',
    //   'value' => !!$this->saveToProject,
    //   'columnWidth' => 100,
    //   'description' => 'These files will NOT be watched for changes! Think of them as a read-only dumps of your project\'s config at the given time.',
    // ]);
    // $inputfields->add([
    //   'name' => 'saveToMigrate',
    //   'type' => 'toggle',
    //   'label' => 'Save migration data to /site/migrate.yaml?',
    //   'value' => !!$this->saveToMigrate,
    //   'columnWidth' => 50,
    //   'description' => 'This file will automatically be watched for changes! That means you can record changes and then edit migrate.yaml in your IDE and the changes will automatically be applied on the next reload.',
    // ]);

    return $inputfields;
  }

  public function __debugInfo() {
    $lastrun = "never";
    if($this->lastrun) {
      $lastrun = date("Y-m-d H:i:s", $this->lastrun)." ({$this->lastrun})";
    }
    return [
      'Version' => $this->getModuleInfo()['version'],
      'lastrun' => $lastrun,
      'recorders' => $this->recorders,
      'watchlist' => $this->watchlist,
    ];
  }

}
