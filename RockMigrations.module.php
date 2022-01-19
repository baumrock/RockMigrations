<?php namespace ProcessWire;

use RockMigrations\YAML;

/**
 * @author Bernhard Baumrock, 19.01.2022
 * @license COMMERCIAL DO NOT DISTRIBUTE
 * @link https://www.baumrock.com
 */
class RockMigrations extends WireData implements Module, ConfigurableModule {

  const cachename = 'rockmigrations-last-run';

  /**
   * Timestamp of last run migration
   * @var int
   **/
  private $lastrun;

  /** @var WireArray */
  private $recorders;

  /** @var WireData */
  private $watchlist;

  /** @var YAML */
  private $yaml;

  public static function getModuleInfo() {
    return [
      'title' => 'RockMigrations',
      'version' => '0.0.5',
      'summary' => 'Brings easy Migrations/GIT support to ProcessWire',
      'autoload' => 2,
      'singular' => true,
      'icon' => 'magic',
      'requires' => [],
      'installs' => [],
    ];
  }

  public function __construct() {
    parent::__construct();
    $this->recorders = $this->wire(new WireArray());
    $this->watchlist = $this->wire(new WireData());
    $this->lastrun = $this->wire->cache->get(self::cachename);
    $this->watch($this->wire->config->paths->site."migrate.yaml");
    $this->watch($this->wire->config->paths->site."migrate.php");
  }

  public function init() {
    $this->wire('rockmigrations', $this);
    $this->rm1(); // load RM1 (install it)
    $this->addRecorderHooks();
  }

  public function ready() {
    $this->migrateWatchfiles();
  }

  /**
   * Proxy all failing method calls to this version of RM to RM1
   */
  public function __call($method, $args) {
    if(!method_exists($this, $method)) {
      $rm1 = $this->rm1();
      if($rm1) return $rm1->$method(...$args);
      return false;
    }
    return self::$method(...$args);
  }

  /**
   * Add hooks to field/template creation for recorder
   * @return void
   */
  public function addRecorderHooks() {
    $this->addHookAfter("Fields::saved", $this, "saveRecorders");
    $this->addHookAfter("Templates::saved", $this, "saveRecorders");
  }

  /**
   * Get lastmodified timestamp of watchlist
   * @return int
   */
  public function lastmodified() {
    $last = 0;
    foreach($this->watchlist as $path=>$options) {
      if(!is_file($path)) continue;
      $m = filemtime($path);
      if($m>$last) $last=$m;
    }
    return $last;
  }

  /**
   * Run migrations of all watchfiles
   * @return void
   */
  public function migrateWatchfiles() {
    $lastmodified = $this->lastmodified();
    if($this->lastrun < $lastmodified) {
      $this->log('Running migrations...');
      $this->updateLastrun();
      // bd($this->watchlist);
      foreach($this->watchlist as $path=>$options) {
        if(!$options['migrate']) continue;
        $this->log("Migrating $path");
        $migrate = $this->wire->files->render($path, $options['vars'], [
          'allowedPaths' => [dirname($path)],
        ]);
        if(is_string($migrate)) $migrate = $this->yaml($migrate);
        $this->migrate($migrate);
      }
    }
  }

  /**
   * Record settings to file
   * @param string $path
   * @param array $options
   * @return void
   */
  public function record($path, $options = []) {
    $defaults = [
      'path' => $path,
      'type' => 'yaml', // other options: php, json
    ];
    $this->recorders->add(array_merge($defaults, $options));
  }

  /**
   * Return old version of RockMigrations
   * Will be dropped with RM v1.0.0!
   * @return RockMigrations1
   */
  public function rm1() {
    return $this->wire->modules->get("RockMigrations1");
  }

  /**
   * Save config to recorder file
   * @return void
   */
  public function saveRecorders(HookEvent $event) {
    foreach($this->recorders as $recorder) {
      $path = $recorder['path'];
      $type = strtolower($recorder['type']);
      $this->log("Writing config to $path");

      $arr = [
        'fields' => [],
        'templates' => [],
      ];
      foreach($this->sort($event->wire->fields) as $field) {
        if($field->flags) continue;
        $arr['fields'][$field->name] = array_merge([
          'flags' => $field->flags,
        ], $field->getArray());
      }
      foreach($this->sort($event->wire->templates) as $template) {
        if($template->flags) continue;
        $arr['templates'][$template->name] = array_merge([
          'fields' => array_values($template->fields->each('name')),
        ], $template->getArray());
      }

      if($type == 'yaml') $this->yaml($path, $arr);
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
  public function updateLastrun() {
    $this->wire->cache->save(self::cachename, time(), WireCache::expireNever);
  }

  /**
   * Add file to watchlist
   *
   * Usage:
   * $rm->watch(__FILE__);
   *
   * Only watch the file but don't migrate it. This is useful if a migration
   * file depends on something else (like constants of a module). To make the
   * migrations run when the module changes you can add the module file to the
   * watchlist:
   * $rm->watch('/site/modules/MyModule.module.php', false);
   *
   * @param string $file Path to file to be watched for changes
   * @param bool $migrate Does the file return an array for $rm->migrate() ?
   * @param array $vars Array of variables to pass to the migrations file
   * @return void
   */
  public function watch($file, $migrate = true, $vars = []) {
    if(!is_file($file)) return;

    // default variables
    $defaults = [
      'rm' => $this,
    ];

    $path = Paths::normalizeSeparators(realpath($file));
    $options = [
      'migrate' => $migrate,
      'vars' => array_merge($defaults, $vars),
    ];
    $this->watchlist->set($path, $options);
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
    elseif($path AND $data!==null) return $yaml->save($path, $data);
    return $yaml;
  }


  /**
  * Config inputfields
  * @param InputfieldWrapper $inputfields
  */
  public function getModuleConfigInputfields($inputfields) {

    $inputfields->add([
      'name' => 'saveProject',
      'type' => 'toggle',
      'label' => 'Save migration data to /site/project.yaml?',
      'value' => !!$this->saveProject,
      'columnWidth' => 50,
      'description' => 'This file will NOT be watched for changes! Think of it as a read-only dump of your project config.',
    ]);
    $inputfields->add([
      'name' => 'saveMigrate',
      'type' => 'toggle',
      'label' => 'Save migration data to /site/migrate.yaml?',
      'value' => !!$this->saveMigrate,
      'columnWidth' => 50,
      'description' => 'This file will automatically be watched for changes! That means you can record changes and then edit migrate.yaml in your IDE and the changes will automatically be applied on the next reload.',
    ]);

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
      'watch' => $this->watchlist,
    ];
  }

}
