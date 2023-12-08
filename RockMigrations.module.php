<?php namespace ProcessWire;

use RockMigrations\WatchFile;
use Symfony\Component\Yaml\Yaml;
use function array_key_exists;

/**
 * @author Bernhard Baumrock, 19.01.2022
 * @license MIT
 * @link https://www.baumrock.com
 */
class RockMigrations extends WireData implements Module, ConfigurableModule
{

  const debug = false;
  const cachename = 'rockmigrations-last-run';

  const outputLevelDebug = 'debug';
  const outputLevelQuiet = 'quiet';
  const outputLevelVerbose = 'verbose';

  // global processwire fields
  const field_pagename = "_pw_page_name";
  const field_email = "email";

  // time constants (seconds)
  // see https://i.imgur.com/vfTasHa.png
  const oneMinute = 60;
  const oneHour = self::oneMinute * 60;
  const oneDay = self::oneHour * 24;
  const oneWeek = self::oneDay * 7;
  const oneMonth = self::oneDay * 30;
  const oneYear = self::oneDay * 365;

  /** @var WireData */
  public $conf;

  /**
   * Flag that is set true when migrations are running
   * @var bool
   */
  public $ismigrating = false;

  /**
   * Timestamp of last run migration
   * @var int
   **/
  private $lastrun;

  private $migrateAll = false;

  private $migrated = [];

  private $noMigrate = false;

  public $noYaml = false;

  private $outputLevel = self::outputLevelQuiet;

  /** @var string */
  public $path;

  /** @var WireArray */
  private $watchlist;

  public function __construct() {
    parent::__construct();
    $this->path = $this->wire->config->paths($this);
    $this->wire->classLoader->addNamespace("RockMigrations", __DIR__ . "/classes");

    $this->watchlist = $this->wire(new WireArray());
    $this->lastrun = (int)$this->wire->cache->get(self::cachename);
  }

  public function init() {
    $this->wire->classLoader->addNamespace("RockMigrations", __DIR__ . "/classes");
    $config = $this->wire->config;
    $this->wire('rockmigrations', $this);
    if ($config->debug) $this->setOutputLevel(self::outputLevelVerbose);

    $this->conf = $this->wire(new WireData());
    $this->conf->setArray($this->getArray()); // get modules config
    if (is_array($config->rockmigrations)) {
      // set module settings from config file
      $this->setArray($config->rockmigrations);
    }

    // add /site/migrate.[yaml|php] to watchlist
    // we use a high priority to make sure this is the first file migrated
    $this->watch($config->paths->site . "migrate", 9999);

    // hooks
    $this->addHookAfter("Modules::refresh", $this, "triggerMigrations");
    $this->addHookBefore("InputfieldForm::render", $this, "showEditInfo");
    $this->addHookBefore("InputfieldForm::render", $this, "showCopyCode");
    $this->addHookBefore("InputfieldForm::render", $this, "addRmHints");
  }

  public function ready() {
    $this->forceMigrate();

    // other actions
    $this->migrateWatchfiles();

    // load RockMigrations.js on backend
    if ($this->wire->page->template == 'admin') {
      $this->config->scripts->add(__DIR__ . "/RockMigrations.js");
      $this->config->styles->add(__DIR__ . "/RockMigrations.admin.css");

      // fix ProcessWire language tabs issue
      if ($this->wire->languages) {
        $this->wire->config->js('rmUserLang', $this->wire->user->language->id);
      }
    }
  }

  /**
   * Add field to template
   *
   * @param Field|string $field
   * @param Template|string $template
   * @return void
   */
  public function addFieldToTemplate($field, $template, $afterfield = null, $beforefield = null) {
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
  public function addFieldsToTemplate($fields, $template, $sortFields = false) {
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
  public function addLanguage(string $name, string $title = null) {
    $lang = $this->getLanguage($name);
    if (!$lang->id) {
      $lang = $this->languages->add($name);
      $this->languages->reloadLanguages();
    }
    if ($title) $lang->setAndSave('title', $title);
    return $lang;
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
    if (!$role) return $this->log("Role $role not found");
    $role->of(false);
    $role->addPermission($permission);
    return $role->save();
  }

  /**
   * @param HookEvent $event
   * @return void
   *
   * @noinspection PhpUnused pw-hook
   */
  public function addRmHints(HookEvent $event) {
    if (!$this->wire->user->isSuperuser()) return;
    $form = $event->object;
    $showHints = false;
    if ($form->id == 'ProcessTemplateEdit') $showHints = true;
    elseif ($form->id == 'ProcessFieldEdit') $showHints = true;
    if (!$showHints) return;
    $form->addClass('rm-hints');
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
    if (!$role) return $this->log("$msg - role not found");
    if (!$user) return $this->log("$msg - user not found");
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
  public function addTemplateAccess($templates, $roles, $accs) {
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
   * @param string|array $type |$options
   * @param array $options
   * @return Field|false
   */
  public function createField($name, $type = 'text', $options = []) {
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
  public function createFields($fields): void {
    foreach ($fields as $name => $data) {
      if (is_int($name)) {
        $name = $data;
        $data = [];
      }
      $this->createField($name, $data);
    }
  }

  /**
   * Create a new Page
   *
   * If the page exists it will return the existing page.
   * Note that all available languages will be set active by default!
   *
   * Usage:
   * $rm->createPage(
   *   template: 'foo',
   *   title: 'My foo page',
   *   parent: 1,
   *   status: ['hidden'],
   * );
   *
   * If you need to set a multilang title use
   * $rm->setFieldLanguageValue($page, "title", [
   *   'default'=>'foo',
   *   'german'=>'bar',
   * ]);
   *
   * @param Template|string $template
   * @param Page|string|int $parent
   * @param string $name
   * @param string $title
   * @param array $status
   * @param array $data
   * @param bool $allLanguages
   * @return Page
   */
  public function createPage(
    $template,
    $parent,
    string $name = null,
    string $title = null,
    array $status = null,
    array $data = null,
    bool $allLanguages = true
  ) {
    // create pagename from page title if it is not set
    if (!$name) $name = $this->sanitizer->pageNameTranslate($title);
    if (!$name) $name = $this->wire->pages->names()->uniquePageName();

    $parentName = $parent;
    $log = "Parent $parent not found";
    $parent = $this->getPage($parent);
    if (!$parent) {
      $this->error("The parent '$parentName' for page '$title' can not be found. Did you choose the correct parent?");
      return $this->log($log);
    }

    // get page if it exists
    $page = $this->getPage([
      'name' => $name,
      'template' => $template,
      'parent' => $parent,
    ], true);

    if ($page and $page->id) {
      if ($title !== null) $page->setAndSave('title', $title);
      $page->status($status ?: []);

      // if some page field values are provided we save them now
      if (is_array($data)) $page->setAndSave($data);

      return $page;
    }

    // create a new page
    $p = $this->wire(new Page());
    $p->template = $template;
    $p->parent = $parent;
    if ($title !== null) $p->title = $title;
    $p->name = $name;
    $p->status($status);
    $p->setAndSave($data);
    $p->save();

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
  public function createRole($name, $permissions = []) {
    if (!$name) return $this->log("Define a name for the role!");

    $role = $this->getRole($name, true);
    if (!$role) $role = $this->roles->add($name);
    foreach ($permissions as $permission) {
      $this->addPermissionToRole($permission, $role);
    }

    return $role;
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
  public function createTemplate($name, $data = false, $migrate = true) {
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
        $this->triggerMigrate($p);
      }
    }

    // handle different types of second parameter
    if (is_bool($data)) {
      // add title field to this template if second param = TRUE
      if ($data) $this->addFieldToTemplate('title', $t);
    } elseif (is_array($data)) {
      // second param is an array
      // that means we set the template data from array syntax
      $this->setTemplateData($t, $data);
    }

    return $t;
  }

  /**
   * This makes sure that for every classfile the corresponding template exists
   */
  private function createTemplateFromClassfile(string $file, string $namespace) {
    $name = substr(basename($file), 0, -4);
    $classname = "\\$namespace\\$name";
    $tmp = new $classname();

    try {
      if ($this->isCLI()) $this->log("Setup Template " . $tmp::tpl);

      // if the template already exists we exit early
      $tpl = $this->getTemplate($tmp::tpl, true);
      if ($tpl) return $tpl;

      // template does not exist - create it!
      $tpl = $this->createTemplate($tmp::tpl, [
        'pageClass' => $classname,
        'tags' => $namespace,
        'fields' => ['title'],
      ]);

      return $tpl;
    } catch (\Throwable $th) {
      throw new WireException("Error setting up template - you must add the tpl constant to $classname");
    }
  }

  /**
   * Create or return a PW user
   * If a user exists it will update the user with specified data in 2nd argument.
   * If no password is specified a random password will be used when creating the user.
   *
   * If you don't specify a password you can get the generated password like this:
   * $user = $rm->createUser('foo');
   * $newPassword = $user->_pass;
   *
   * Usage:
   * $rm->createUser('demo', [
   *   'roles' => ['webmaster'],
   *   'pass' => 'supersecretpassword',
   * ]);
   *
   * @param string $username
   * @param array $data
   * @return User
   */
  public function createUser($username, $data = []) {
    $user = $this->getUser($username, true);
    if (!$user or !$user->id) {
      $user = $this->wire->users->add($username);

      // for backwards compatibility
      if (array_key_exists("password", $data)) {
        $data['pass'] = $data['password'];
      }

      // setup password
      $rand = $this->wire(new WireRandom());
      /** @var WireRandom $rand */
      $pass = $rand->alphanumeric(null, [
        'minLength' => 10,
        'maxLength' => 20,
      ]);
      // if a user-specified password exists it has priority
      $data = array_merge(['pass' => $pass], $data);
      $user->_pass = $pass;
    }
    $this->setUserData($user, $data);
    return $user;
  }

  /**
   * Delete the given field
   * @param mixed $name
   * @param bool $quiet
   * @return void
   */
  public function deleteField($name, $quiet = false) {
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
  public function deleteFields($fields, $quiet = false) {
    if (is_string($fields)) $fields = $this->wire->fields->find($fields);
    foreach ($fields as $field) $this->deleteField($field, $quiet);
  }

  /**
   * Deletes a language
   * @param mixed $language
   * @return void
   */
  public function deleteLanguage($language, $quiet = false) {
    if (!$lang = $this->getLanguage($language, $quiet)) return;
    $this->wire->languages->delete($lang);
  }

  /**
   * Delete the given page including all children.
   *
   * @param Page|string $page
   * @return void
   */
  public function deletePage($page, $quiet = false) {
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
  public function deletePermission($permission, $quiet = false) {
    if (!$permission = $this->getPermission($permission, $quiet)) return;
    $this->permissions->delete($permission);
  }

  /**
   * Delete the given role
   * @param Role|string $role
   * @param bool $quiet
   * @return void
   */
  public function deleteRole($role, $quiet = false) {
    if (!$role = $this->getRole($role, $quiet)) return;
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
  public function deleteTemplates($selector, $quiet = false) {
    $templates = $this->wire->templates->find($selector);
    foreach ($templates as $tpl) $this->deleteTemplate($tpl, $quiet);
  }

  /**
   * Delete a PW user
   *
   * @param string $username
   * @return void
   */
  public function deleteUser($username, $quiet = false) {
    if (!$user = $this->getUser($username, $quiet)) return;
    $this->wire->users->delete($user);
  }

  protected function doMigrate($file) {
    if ($this->migrateAll) return true;
    $watchFile = $file;
    if (is_string($watchFile)) $watchFile = $this->watchlist->get("path=$file");
    if (!$watchFile instanceof WatchFile) return false;
    if ($watchFile->changed) return true;
    if ($watchFile->force) return true;
    return false;
  }

  /**
   * Find migration file (tries all extensions)
   * @return string|false
   */
  public function file($path) {
    $path = Paths::normalizeSeparators($path);
    if (is_file($path)) return $path;
    foreach (['yaml', 'php'] as $ext) {
      if (is_file($f = "$path.$ext")) return $f;
    }
    return false;
  }

  public function filemtime($file) {
    $path = $this->getAbsolutePath($file);
    if (is_file($path)) return filemtime($path);
    return 0;
  }

  /**
   * Get filepath of file or object
   * @return string
   */
  public function filePath($file, $relative = false) {
    if (is_object($file)) {
      $reflector = new \ReflectionClass($file);
      $file = $reflector->getFileName();
    }
    $file = Paths::normalizeSeparators($file);
    if ($relative) $file = str_replace(
      $this->wire->config->paths->root,
      $this->wire->config->urls->root,
      $file
    );
    return $file;
  }

  /**
   * Run migrations during development on every request
   * This is handy to code rapidly and get instant output via RockFrontend's
   * livereload feature!
   *
   * To use this feature add this to your /site/init.php:
   * $config->forceMigrate = true;
   */
  private function forceMigrate() {
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
  public function getAbsolutePath($file) {
    $path = Paths::normalizeSeparators($file);
    $rootPath = $this->wire->config->paths->root;
    if (strpos($path, $rootPath) === 0) return $path;
    return $rootPath . ltrim($file, "/");
  }

  /**
   * Get changed watchfiles
   * @return array
   */
  public function getChangedFiles() {
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
  public function getCode($item, $raw = false) {
    if ($item instanceof Field) {
      $data = $item->getExportData();

      unset($data['id']);
      unset($data['name']);
      unset($data['rockmigrations']);

      // convert template ids to template names
      $key = 'template_ids';
      if (
        $item instanceof PageField
        and array_key_exists($key, $data)
        and is_array($data[$key])
      ) {
        $names = [];
        foreach ($data[$key] as $k => $id) {
          $names[] = $this->getTemplate($id)->name;
        }
        $data[$key] = $names;
      }

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
  public function getConfigObject(array $config) {
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
  public function getField($name, $quiet = false) {
    if (!$name) return false; // for addfieldtotemplate
    $field = $this->fields->get((string)$name);
    if ($field) return $field;
    if (!$quiet) $this->log("Field $name not found");
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
   * Get file from folders
   *
   * Usage:
   * $rm->getFile('foo.latte', [
   *   '/folder1',
   *   '/folder2',
   * ]);
   *
   * Returns the first file found, eg
   * /path/to/pw/folder1/foo.latte
   */
  public function getFile(string $file, array $folders): string|false {
    $root = $this->wire->config->paths->root;
    foreach ($folders as $dir) {
      $path = $this->path("$root/$dir/$file");
      if (is_file($path)) return $path;
    }
    return false;
  }

  /**
   * Get language
   * Returns FALSE if language is not found
   * @return Language|false
   */
  public function getLanguage($data, $quiet = false) {
    $lang = $this->wire->languages->get((string)$data);
    if ($lang and $lang->id) return $lang;
    if (!$quiet) $this->log("Language $data not found");
    return false;
  }

  /**
   * Get page
   * Returns FALSE if page is not found
   * @return Page|false
   */
  public function getPage($data, $quiet = false) {
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
  public function getPermission($data, $quiet = false) {
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
  public function getRole($name, $quiet = false) {
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
  public function getTrace($msg = null) {
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
  public function getTraceLog() {
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
  public function getUser($name, $quiet = false) {
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
  public function getTemplate($name, $quiet = false) {
    if ($name instanceof Page) {
      $name = $name->template;
    }
    $template = $this->templates->get((string)$name);
    if ($template and $template->id) return $template;
    if (!$quiet) {
      $this->log("Template $name not found");
      // $this->log(Debug::backtrace());
    }
    return false;
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
  public function installSystemPermissions($names) {
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
  public function isCLI(): bool {
    return php_sapi_name() == "cli" or defined('RockMigrationsCLI');
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
    if ($data === null) return json_decode(file_get_contents($path));
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT));
  }

  /**
   * Get WireData object from json file
   * Will look in PW root folder for file if a relative path is provided
   */
  public function jsonData($file) {
    $data = $this->wire(new WireData());
    if (!is_file($file)) $file = $this->wire->config->paths->root . $file;
    if (!is_file($file)) return $data;
    $arr = json_decode(file_get_contents($file), true);
    $data->setArray($arr);
    return $data;
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

    // convert message to a string
    // this makes it possible to log a Debug::backtrace for example
    // which can be handy for debugging
    $msg = $this->str($msg);

    if ($this->isVerbose()) {
      $opt = [];
      if ($this->wire->config->external) echo $msg;
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
  public function logOnly($msg) {
    $this->log($msg, false);
  }

  /**
   * Get array value
   * @return mixed
   */
  public function val($arr, $property) {
    if (!array_key_exists($property, $arr)) return;
    return $arr[$property];
  }

  /**
   * Merge fields
   *
   * This makes it possible to keep manually created fields at the same position
   * after a migrate() call happened that does not include the manually created
   * field. Prior to v1.0.9 manually created fields had always been added to the
   * top of the page editor if not explicitly listed in the migrate() call.
   */
  protected function mergeFields(Fieldgroup $old, Fieldgroup $new): Fieldgroup {
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
  public function migrate($config) {
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
      if (array_key_exists("permissions", $data)) {
        $this->setRolePermissions($role, $data['permissions']);
      }
      if (array_key_exists("permissions-", $data)) {
        $this->setRolePermissions($role, $data['permissions-'], true);
      }
      if (array_key_exists("access", $data)) {
        foreach ($data['access'] as $tpl => $access) {
          $this->setTemplateAccess($tpl, $role, $access);
        }
      }
      if (array_key_exists("access-", $data)) {
        foreach ($data['access'] as $tpl => $access) {
          $this->setTemplateAccess($tpl, $role, $access, true);
        }
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
        title: $d->title ?: $name,
        name: $name,
        template: $d->template,
        parent: $d->parent,
        status: $d->status,
        data: $d->data
      );
    }
  }

  /**
   * Migrate a single watchfile
   */
  private function migrateWatchfile(WatchFile $file): void {
    if ($this->wire->config->debug and $this->isCLI()) {
      $this->log("Watchfile: " . $file->path);
    }

    if (!$file->migrate) return;
    if (!$this->doMigrate($file)) {
      $this->log("--- Skipping {$file->path} (no change)");
      return;
    }

    // if it is a callback we execute it
    if ($callback = $file->callback) {
      $callback->__invoke($this);
      return;
    }

    // we have a regular file
    // first we render the file
    // this will already execute commands inside the file if it is PHP
    $this->log("Load {$file->path}");
    $migrate = $this->runFile($file->path);
    // if rendering the file returned a string we state that it is YAML code
    if (is_string($migrate)) $migrate = $this->yaml($migrate);
    if (is_array($migrate)) {
      $this->log("Returned an array - trigger migrate() of "
        . print_r($migrate, true));
      $this->migrate($migrate);
    }
  }

  /**
   * Run migrations of all watchfiles
   * @return void
   */
  private function migrateWatchfiles($force = false) {
    $debug = $this->wire->config->debug;

    if (!$this->isCLI() and $this->wire->config->noMigrate) {
      $this->log("Migrations disabled via \$config->noMigrate");
      return;
    }
    if (!$this->isCLI() and $this->disabled) {
      $this->log("Migrations disabled via module settings");
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
      $this->log('Running migrations from watchfiles ...');
    }

    $this->updateLastrun();

    $list = $this->sortWatchlist();
    if ($this->isCLI() and $debug) {
      $this->log("##### SORTED WATCHLIST #####");
      $this->log($list);
    }

    foreach ($list as $prio => $items) {
      if ($this->isCLI()) $this->log("");
      $this->log("### Migrate items with priority $prio ###");

      foreach ($items as $path) {
        $file = $this->watchlist->get("path=$path");
        $this->migrateWatchfile($file);
      }
    }

    if ($this->isCLI()) $this->log("---");
    $this->log("Trigger RockMigrations::migrationsDone");
    $this->migrationsDone();
  }

  public function ___migrationsDone() {
  }

  /**
   * Migrate yaml file
   */
  public function migrateYAML($path) {
    $data = $this->yaml($path);
    if (is_array($data)) $this->migrate($data);
  }

  /**
   * Move one page on top of another one
   * @return void
   */
  public function movePageAfter($page, $reference) {
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
  public function movePageBefore($page, $reference) {
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
  public function noMigrate() {
    $this->noMigrate = true;
  }

  /**
   * Execute profile
   */
  private function profileExecute() {
    $profile = $this->wire->input->post('profile', 'filename');
    foreach ($this->profiles() as $path => $label) {
      if ($label !== $profile) continue;
      $this->wire->files->include($path);
      $this->wire->message("Executed profile $label");
      return true;
    }
    return false;
  }

  private function profiles() {
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
  public function refresh() {
    $this->wire->session->noMigrate = true;
    $this->log('Refresh modules');
    $this->wire->modules->refresh();
    $this->wire->session->noMigrate = false;
  }

  /**
   * Remove Field from Template
   *
   * Will silently return if field has already been removed
   *
   * @param Field|string $field
   * @param Template|string $template
   * @param bool $force
   * @return void
   */
  public function removeFieldFromTemplate($field, $template, $force = false) {
    $field = $this->getField($field);
    if (!$field) return;
    $template = $this->getTemplate($template);
    if (!$template) return;

    $fg = $template->fieldgroup;
    /** @var Fieldgroup $fg */
    if ($force) $field->flags = 0;

    // if field is already removed we exit silently
    if (!$fg->get($field->name)) return;

    $fg->remove($field);
    $fg->save();
    $this->log("Removed field $field from template $template");
  }

  /**
   * See method above
   */
  public function removeFieldsFromTemplate($fields, $template, $force = false) {
    foreach ($fields as $field) $this->removeFieldFromTemplate($field, $template, $force);
  }

  /**
   * Remove a permission from given role
   *
   * @param string|int $permission
   * @param string|int $role
   * @return void
   */
  public function removePermissionFromRole($permission, $role) {
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
  public function removePermissionsFromRoles($permissions, $roles) {
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
  public function removeTemplateAccess($tpl, $role) {
    if (!$role = $this->getRole($role)) return;
    if (!$tpl = $this->getTemplate($tpl)) return;
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
    if (!$field->id) {
      return $this->log("removeTemplateContext: field not found");
    }
    $tpl->fieldgroup->setFieldContextArray($field->id, []);
  }

  /**
   * Rename given page
   * @return void
   */
  public function renamePage($page, $newName, $quiet = false) {
    if (!$page = $this->getPage($page, $quiet)) return;
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
    $user = $this->wire->user;
    $this->sudo();
    $this->migrateWatchfiles(true);
    $this->wire->users->setCurrentUser($user);
  }

  /**
   * Run migrations from file
   */
  private function runFile($file) {
    if (!is_file($file)) return;
    return $this->wire->files->render($file, [], [
      'allowedPaths' => [dirname($file)],
    ]);
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
      if ($key === "template_id" and is_string($val) and $val !== '') {
        $tpl = $this->getTemplate($val);
        if (!$tpl) throw new WireException("Invalid template_id");
        $data[$key] = $tpl->id;
        continue; // early exit
      }

      // support setting the "additional templates" via names instead of ids
      // see https://shorturl.at/pyDRS
      if ($key === "template_ids" && $val) {
        foreach ($val as $sub => $tpl_name) {
          if (is_string($tpl_name) and $tpl_name !== '') {
            $tpl = $this->getTemplate($tpl_name);
            if (!$tpl) throw new WireException("Invalid item value in template_ids");
            $val[$sub] = $tpl->id;
          }
          $data[$key] = $val;
          continue; // early exit
        }
      }

      // support defining parent_id as page path
      // eg 'parent_id' => '/comments'
      if ($key === "parent_id" and is_string($val) and $val !== '') {
        $parent = $this->getPage($val);
        if (!$parent) throw new WireException("Invalid parent_id $val");
        $data[$key] = $parent->id;
        continue; // early exit
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
        continue; // early exit
      }
      if ($key == "optionsLang") {
        $options = $data[$key];
        $this->setOptionsLang($field, $options, true);

        // this prevents setting the "options" property directly to the field
        // if not done, the field shows raw option values when rendered
        unset($data[$key]);
        continue; // early exit
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
  public function setFieldOrder($fields, $template) {
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
  public function setOptionsLang($field, $options, $removeOthers = false) {
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
   * Set output level
   * @return void
   */
  public function setOutputLevel($level) {
    $this->outputLevel = $level;
  }

  /**
   * Set parent child family settings for two templates
   */
  public function setParentChild($parent, $child, $onlyOneParent = true) {
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
   * Set permissions for given role
   * By default this will not remove old permissions just like all the other
   * setXXX methods behave.
   * @return void
   */
  public function setRolePermissions($role, $permissions, $remove = false) {
    $role = $this->getRole($role);
    if ($remove) {
      // remove all existing permissions from role
      foreach ($role->permissions as $p) $this->removePermissionFromRole($p, $role);
    }
    foreach ($permissions as $perm) $this->addPermissionToRole($perm, $role);
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
  public function setTemplateAccess($tpl, $role, $access, $remove = false) {
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
  public function setTemplateData($name, array $data) {
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
  public function setTemplateFields($template, $fields, $removeOthers = false) {
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
  public function setUserData($user, array $data) {
    $user = $this->getUser($user);
    if (!$user) return; // logging above
    $user->of(false);

    // for backwards compatibility
    if (array_key_exists("password", $data)) {
      $data['pass'] = $data['password'];
    }

    // setup options
    $opt = $this->wire(new WireData());
    /** @var WireData $opt */
    $opt->setArray([
      // dont set password here as this would reset passwords
      // when createUser() is used in a migration!
      'roles' => [],
      'admintheme' => 'AdminThemeUikit',
      'pass' => null,
    ]);
    $opt->setArray($data);

    // set roles
    if (is_string($opt->roles)) $opt->roles = [$opt->roles];
    foreach ($opt->roles as $role) $this->addRoleToUser($role, $user);

    // set password if it is set
    if ($opt->pass) $user->set('pass', $opt->pass);

    // save admin theme in 2 steps
    // otherwise the admin theme will not update (PW issue)
    if ($opt->admintheme) $user->set('admin_theme', $opt->admintheme);

    $user->save();
    $this->log("Set user data for user $user ({$user->name})");
    return $user;
  }

  /** START Repeater Matrix */


  /**
   * Convenience method that creates a repeater matrix field with its matrix types
   *
   * @param string $name The name of the field.
   * @param array $options The options for the field.
   * @param bool $wipe Whether to wipe the matrix items before setting new items. defaults to false.
   * @return RepeaterMatrixField|null The created repeater matrix field, or null if an error occurred.
   *
   * CAUTION: wipe = true will also delete all field data stored in the
   * repeater matrix fields!!
   *
   * EXAMPLE:
   * $rm->createRepeaterMatrixField('repeater_matrix_field_name', [
   *    'label' => 'Field Label',
   *    'tags' => 'your tags',
   *    'repeaterAddLabel' => 'Add New Block',
   *    'matrixItems' => [ // matrix types with their fields
   *        'type1' => [
   *            'label' => 'Type1',
   *            'fields' => [
   *                'title' => [
   *                    'label' => 'Custom Title',
   *                    'description' => 'Custom description',
   *                    'required' => 1,
   *                ],
   *            ]
   *        ],
   *        'type2' => [
   *            'label' => 'Type2',
   *            'fields' => [
   *                'text' => [],
   *            ]
   *        ],
   *    ]
   * ]);
   */
  public function createRepeaterMatrixField(string $name, array $options, bool $wipe = false) {
    $items = array_key_exists('matrixItems', $options) ? $options['matrixItems'] : null;
    if ($items) unset($options['matrixItems']);
    // create field
    $field = $this->createField($name, 'FieldtypeRepeaterMatrix', $options);
    // populate matrix items
    if ($field && wireInstanceOf($field, 'RepeaterMatrixField')) {
      $this->setMatrixItems($field, $items, $wipe);
    }

    return $field;
  }

  /**
   * Add matrix item to given field
   * @param RepeaterMatrixField|string $field
   * @param string $name name of the matrix type
   * @param array $data data for the matrix type
   * @return RepeaterMatrixField|null
   */
  protected function addMatrixItem($field, $name, $data) {
    if (!$field = $this->getField($field, false)) return;
    // do not add if there already is a matrix type with the same name
    if ($field->getMatrixTypeByName($name) !== false) return;
    $hasFielddata = isset($data['fields']) && count(array_filter(array_keys($data['fields']), 'is_string')) > 0;
    $info = array();
    // get number
    $n = 1;
    while (array_key_exists("matrix{$n}_name", $field->getArray())) $n++;
    $info['type'] = $n;
    $prefix = "matrix{$n}_";
    $field->set($prefix . "name", $name);
    $field->set($prefix . "sort", $n - 1); // 'sort' is 0 based
    $info['fields'] = array();
    foreach ($hasFielddata ? array_keys($data['fields']) : $data['fields'] as $fieldname) {
      if ($f = $this->wire->fields->get($fieldname)) $info['fields'][$fieldname] = $f;
    }
    $info['fieldIDs'] = array_map(function (Field $f) {
      return $f->id;
    }, $info['fields']);
    foreach ($this->getMatrixDataArray($data) as $key => $val) {
      // eg set matrix1_label = ...
      $field->set($prefix . $key, $val);
      if ($key === "fields") {
        $tpl = $field->type->getMatrixTemplate($field);
        $this->addFieldsToTemplate($val, $tpl);
        $this->setMatrixFieldDataInContext($data['fields'], $info, $tpl);
      }
    }

    $field = $this->resetMatrixRepeaterFields($field);
    $field->save();
    return $field;
  }

  /**
   * Remove matrix item from field
   *
   * CAUTION: removing a type will also remove all associated data
   * on pages which use that type
   *
   * @param RepeaterMatrixField|string $field
   * @param string $name
   * @return RepeaterMatrixField|null
   */
  public function removeMatrixItem($field, $name) {
    if (!$field = $this->getField($field, false)) return;
    $info = $field->type->getMatrixTypesInfo($field, ['type' => $name]);
    if (!$info) return;

    // reset all properties of that field
    foreach ($field->getArray() as $prop => $val) {
      if (strpos($prop, $info['prefix']) !== 0) continue;
      $field->set($prop, null);
    }

    $field = $this->resetMatrixRepeaterFields($field);
    $field->save();
    return $field;
  }

  /**
   * Set items (matrix types) of a RepeaterMatrix field
   *
   * If wipe is set to TRUE it will wipe all existing matrix types before
   * setting the new ones. Otherwise it will override settings of old types
   * and add the type to the end of the matrix if it does not exist yet.
   *
   * CAUTION: wipe = true will also delete all field data stored in the
   * repeater matrix fields!!
   *
   * Usage:
   *  $rm->setMatrixItems('your_matrix_field', [
   *      'foo' => [ // matrixtype name
   *          'label' => 'foo label', // matrixtype label
   *          'fields' => [ // matrixtype fields
   *              'field1' => [
   *                  'label' => 'foolabel', // matrixtype field options
   *                  'columnWidth' => 50, // matrixtype field options
   *              ],
   *              'field2' => [
   *                  'label' => 'foolabel', // matrixtype field options
   *                  'columnWidth' => 50, // matrixtype field options
   *              ],
   *          ],
   *      ],
   *      'bar' => [ // matrixtype name
   *          'label' => 'bar label', // matrixtype label
   *          'fields' => [ // matrixtype fields
   *              'field1' => [
   *                  'label' => 'foolabel', // matrixtype field options
   *                  'columnWidth' => 50, // matrixtype field options
   *              ],
   *              'field2' => [
   *                  'label' => 'foolabel', // matrixtype field options
   *                  'columnWidth' => 50, // matrixtype field options
   *              ],
   *          ],
   *      ],
   *  ], true);
   *
   * @param RepeaterMatrixField|string $field
   * @param array $items matrix types to set
   * @param bool $wipe
   * @return RepeaterMatrixField|null
   */
  public function setMatrixItems($field, $items, $wipe = false) {
    if (!$this->modules->isInstalled('FieldtypeRepeaterMatrix')) return;
    if (!$field = $this->getField($field, false)) return;
    /** @var RepeaterMatrixField $field */
    // get all matrix types of that field
    $types = $field->getMatrixTypes();
    // if wipe is turned on we remove all existing items
    // this is great when you want to control the matrix solely by migrations
    if ($wipe) {
      foreach ($types as $type => $v) $this->removeMatrixItem($field, $type);
    }

    // loop all provided items
    foreach ($items as $name => $data) {
      $type = $field->getMatrixTypeByName($name);
      if (!$type) $field = $this->addMatrixItem($field, $name, $data);
      else $this->setMatrixItemData($field, $name, $data);
    }

    // sort $items in the order they were passed in
    $this->sortMatrixItemsinMatrix($field, $items);

    return $field;
  }

  /**
   * sort matrix items in the order they were passed in
   * @param RepeaterMatrixField $field
   * @param array $items
   * @return RepeaterMatrixField
   */
  protected function sortMatrixItemsinMatrix(RepeaterMatrixField $field, array $items) {
    // add property 'order' to each item based on the array index
    $names = array_keys($items);
    for ($i = 0; $i < count($names); $i++) {
      $name = $names[$i];
      $typeInfo = $field->getMatrixTypesInfo();
      if (!array_key_exists($name, $typeInfo)) continue;
      $info = $typeInfo[$name];
      $field->set($info['prefix'] . 'sort', $i);
    }
    $field->save();
  }

  /**
   * Set matrix item data
   * @param RepeaterMatrixField|string $field
   * @param string $name
   * @param array $data
   * @return RepeaterMatrixField|null
   */
  public function setMatrixItemData($field, $name, $data) {
    if (!$field = $this->getField($field, false)) return;
    $info = $field->getMatrixTypesInfo(['type' => $name]);
    if (!$info) return;
    $hasFielddata = isset($data['fields']) && count(array_filter(array_keys($data['fields']), 'is_string')) > 0;
    foreach ($this->getMatrixDataArray($data, $info) as $key => $val) {
      // eg set matrix1_label = ...
      $field->set($info['prefix'] . $key, $val);
      if ($key === "fields") {
        $tpl = $field->type->getMatrixTemplate($field);
        $this->addFieldsToTemplate($val, $tpl);
        if ($hasFielddata) $this->setMatrixFieldDataInContext($data['fields'], $info, $tpl);
      }
    }

    $field = $this->resetMatrixRepeaterFields($field);
    $field->save();
    return $field;
  }

  /**
   * Sets the matrix field data in the context of the template.
   *
   * @param array $fieldData The field data to be set in the context.
   * @param array $info The information about the fields.
   * @param string $template The template to be used.
   * @return void
   */
  private function setMatrixFieldDataInContext($fieldData, $info, $template) {
    foreach ($fieldData as $fieldname => $data) {
      /** @var Field $field */
      if (!isset($info['fields'][$fieldname])) continue;
      $field = $info['fields'][$fieldname]->getContext($template);
      $contextData = $field->get('NS_matrix' . $info['type']);
      foreach ($data as $_key => $_val) {
        $contextData[$_key] = $_val;
      }
      $field->set('NS_matrix' . $info['type'], $contextData);
      if ($fieldgroup = $field->_contextFieldgroup) $this->wire->fields->saveFieldgroupContext($field, $fieldgroup);
    }
  }

  /**
   * Sanitize repeater matrix array
   * @param array $data
   * @return array
   */
  private function getMatrixDataArray($data) {
    $newdata = [];
    foreach ($data as $key => $val) {
      // make sure fields is an array of ids
      if ($key === 'fields') {
        $ids = [];
        foreach (array_keys($val) as $_field) {
          if (!$field = $this->wire->fields->get($_field)) continue;
          $ids[] = $field->id;
        }
        $val = $ids;
      }
      $newdata[$key] = $val;
    }
    return $newdata;
  }

  /**
   * Reset repeaterFields property of matrix field
   * @param RepeaterMatrixField $field
   * @return RepeaterMatrixField
   */
  private function resetMatrixRepeaterFields(RepeaterMatrixField $field) {
    $ids = [$this->fields->get('repeater_matrix_type')->id];
    //enumerate only existing fields
    $keys = array_keys($field->getArray());
    $items = preg_grep("/matrix(\d+)_fields/", $keys);
    foreach ($items as $item) {
      $ids = array_merge($ids, $field->get($item) ?: []);
    }

    $field->set('repeaterFields', $ids);

    // remove unneeded fields
    $tpl = $field->type->getMatrixTemplate($field);
    foreach ($tpl->fields as $f) {
      if ($f->name === 'repeater_matrix_type') continue;
      if (in_array($f->id, $ids)) continue;
      $this->removeFieldFromTemplate($f, $tpl);
    }

    return $field;
  }

  /** END Repeater Matrix */

  /**
   * Show edit info on field and template edit screen
   * @return void
   *
   * @noinspection PhpUnused pw-hook
   */
  public function showCopyCode(HookEvent $event) {
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
   *
   * @noinspection PhpUnused pw-hook
   */
  public function showEditInfo(HookEvent $event) {
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

    if (!$item) throw new WireException("Item does not exist");

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
  public function sort($data) {
    $arr = $this->wire(new WireArray());
    /** @var WireArray $arr */
    foreach ($data as $item) $arr->add($item);
    return $arr->sort('name');
  }

  /**
   * Sort watchlist by priority and by file path
   * This is very important to ensure that migrations always run in the
   * same order.
   */
  private function sortWatchlist(): array {
    $list = [];
    foreach ($this->watchlist as $file) {
      if (!$file->migrate) continue;
      $key = "#" . $file->migrate;
      if (!array_key_exists($key, $list)) $list[$key] = [];
      $list[$key][] = $file->path;
    }
    ksort($list);
    foreach ($list as $k => $sublist) {
      sort($sublist);
      $list[$k] = $sublist;
    }
    return array_reverse($list);
  }

  /**
   * Convert data to string (for logging)
   */
  public function str($data): string {
    if (is_array($data)) return print_r($data, true);
    elseif (is_string($data)) return "$data\n";
    else {
      ob_start();
      var_dump($data);
      return ob_get_clean();
    }
  }

  /**
   * Convert a comma separated string into an array of single values
   */
  public function strToArray($data): array {
    if (is_array($data)) return $data;
    if (!is_string($data)) throw new WireException("Invalid data in strToArray");
    return array_map('trim', explode(",", $data));
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
    if (!$su->id) return $this->log("No superuser found");
    $this->wire->users->setCurrentUser($su);
  }

  /**
   * Make sure that the given file/directory path is absolute
   * This will NOT check if the directory or path exists!
   * It will always prepend the PW root directory so this method does not work
   * for absolute paths outside of PW!
   */
  public function toPath($url): string {
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
  public function toUrl($path, $cachebuster = false): string {
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
   * Return file reference of debug backtrace
   */
  public function traceFile($find): string {
    $trace = Debug::backtrace();
    foreach ($trace as $item) {
      $file = $item['file'];
      if (strpos($file, $find) === false) continue;
      return $file;
    }
    return '';
  }

  /**
   * Return the given path and make sure it has
   * - normalised separators
   * - no multiple slashes
   * - optionally a trailing slash
   *
   * /foo///bar///baz --> /foo/bar/baz
   *
   * This is great for quickly joining paths where you might not know if they
   * have trailing slashes or not:
   *
   * $rm->path(
   *   $config->paths->root.
   *   "\foo\bar\".
   *   "/baz.php"
   * );
   * --> /var/www/html/foo/bar/baz.php
   */
  public function path(string $path, $slash = null): string {
    $path = Paths::normalizeSeparators($path);
    if ($slash === true) $path .= "/";
    elseif ($slash === false) $path = rtrim($path, "/");
    while (strpos($path, "//")) $path = str_replace("//", "/", $path);
    return $path;
  }

  /**
   * Trigger migrate() method if it exists
   */
  private function triggerMigrate($object, $silent = false): void {
    if (!$silent) $this->log("Migrate $object");
    if (method_exists($object, "migrate")) $object->migrate();
    if (method_exists($object, "___migrate")) $object->migrate();
  }

  /**
   * Trigger migrations after Modules::refresh
   * @return void
   *
   * @noinspection PhpUnused pw-hook
   */
  public function triggerMigrations(HookEvent $event) {
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
  public function unwatchAll() {
    $this->watchlist->removeAll();
  }

  /**
   * Update last run timestamp
   * @return void
   */
  public function updateLastrun($timestamp = null) {
    if ($timestamp === null) $timestamp = time();
    $this->wire->cache->save(self::cachename, $timestamp, WireCache::expireNever);
  }

  /**
   * PHP var_export() with short array syntax (square brackets) indented 2 spaces.
   *
   * NOTE: The only issue is when a string value has `=>\n[`, it will get converted to `=> [`
   * @link https://www.php.net/manual/en/function.var-export.php
   */
  function varexport($expression, $return = TRUE) {
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
   * Add lines of warning to log
   */
  public function warn(string $str): void {
    $lines = explode("\n", $str);
    $len = 0;
    foreach ($lines as $line) {
      if (strlen($line) > $len) $len = strlen($line);
    }
    $hr = str_pad("", $len + 3, "-");
    $this->log($hr);
    foreach ($lines as $line) $this->log(" ! $line");
    $this->log($hr);
  }

  /**
   * Add file to watchlist
   *
   * Usage:
   * Default priority = 1 (higher = earlier)
   * $rm->watch(what, migrate/priority, options);
   *
   * If you dont specify an extension it will watch all available extensions:
   * $rm->watch('/path/to/module'); // watches module.[yaml|php]
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
   * @param mixed $what File or directory to be watched
   * @param bool|float $migrate Execute migration? Float = priority (high=earlier, 1=default)
   * @param array $options Array of options
   * @return void
   */
  public function watch($what, $migrate = true, $options = []) {
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
    $hash = false;

    $trace = debug_backtrace()[1];
    $tracefile = $trace['file'];
    $traceline = $trace['line'];

    if (!is_string($what) and is_callable($what)) {
      $trace = debug_backtrace()[0];
      $tracefile = $trace['file'];
      $traceline = $trace['line'];
      $callback = $what;
      $file = $tracefile;
      $hash = "::" . uniqid();
    } // path to folder
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

    // add item to watchlist
    // sorting of list will happen before migration of all items
    $this->watchlist->add($data);
  }

  public function watchEnabled() {
    if (!$this->wire->user) return false;
    if ($this->wire->user->isSuperuser()) return true;
    if ($this->wire->config->forceWatch) return true;
    if ($this->isCLI()) return true;
    return false;
  }

  /**
   * Interface to the Symfony YAML class
   *
   * Get array from YAML file
   * $rm->yaml('/path/to/file.yaml');
   *
   * Get yaml from php array
   * $rm->yaml(['foo' => 'bar']);
   *
   * Save data to file
   * $rm->yaml('/path/to/file.yaml', ['foo'=>'bar']);
   *
   * @return mixed
   */
  public function yaml($pathOrArray, $data = null) {
    if (!$pathOrArray) return;
    require_once(__DIR__ . '/vendor/autoload.php');

    if (is_array($pathOrArray)) {
      $yaml = Yaml::dump($pathOrArray);
      return $yaml;
    }

    // write yaml data to file
    if ($data) {
      // early exit if noYaml flag is set
      if ($this->noYaml) return;

      // remove properties that are not helpful in yaml files
      unset($data['configPhpHash']);

      $yaml = Yaml::dump($data, 99, 2);
      $yaml = str_replace("''", '""', $yaml);
      $yaml = str_replace(" '", ' "', $yaml);
      $yaml = str_replace("'\n", "\"\n", $yaml);
      $this->wire->files->mkdir(dirname($pathOrArray), true);
      $this->wire->files->filePutContents($pathOrArray, $yaml);
      return $yaml;
    }

    if (!is_file($pathOrArray)) return false;
    return Yaml::parseFile($pathOrArray);
  }

  public function yamlParse($yaml) {
    require_once(__DIR__ . '/vendor/autoload.php');
    return Yaml::parse($yaml);
  }

  /**
   * Config inputfields
   * @param InputfieldWrapper $inputfields
   */
  public function getModuleConfigInputfields($inputfields) {
    // prepare fileconfig string
    $fileConfig = $this->wire->config->rockmigrations;
    if (is_array($fileConfig)) {
      $fileConfig = "<p>Current config from file:</p><pre>" . print_r($fileConfig, true) . "</pre>";
    } else $fileConfig = "";

    $inputfields->add([
      'type' => 'markup',
      'label' => 'RockMigrations Config Options',
      'value' => 'You can set all settings either here via GUI or alternatively via config array:<br>
        <pre>$config->rockmigrations = [<br>'
        . '  "disabled" => true,<br>'
        . '];</pre>'
        . 'Note that settings in config.php have precedence over GUI settings!'
        . $fileConfig,
      'icon' => 'cogs',
      'collapsed' => $fileConfig ? 0 : 1,
    ]);

    $inputfields->add([
      'type' => 'checkbox',
      'name' => 'disabled',
      'label' => 'Disable all migrations',
      'notes' => 'This can be helpful for debugging or if you just want to use some useful methods of RockMigrations (like the asset minify feature).',
      'checked' => $this->disabled ? 'checked' : '',
    ]);

    return $inputfields;
  }

  public function __debugInfo() {
    $lastrun = "never";
    if ($this->lastrun) {
      $lastrun = date("Y-m-d H:i:s", $this->lastrun) . " ({$this->lastrun})";
    }
    return [
      'lastrun' => $lastrun,
      'watchlist' => $this->watchlist,
      'sortWatchlist' => $this->sortWatchlist(),
    ];
  }
}
