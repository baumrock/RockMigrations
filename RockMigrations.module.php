<?php namespace ProcessWire;

use RockMigrations\YAML;

/**
 * @author Bernhard Baumrock, 19.01.2022
 * @license COMMERCIAL DO NOT DISTRIBUTE
 * @link https://www.baumrock.com
 */
class RockMigrations extends WireData implements Module {

  const cachename = 'rockmigrations-last-run';

  /**
   * Timestamp of last run migration
   * @var int
   **/
  private $lastrun;

  /** @var WireData */
  private $watchlist;

  /** @var YAML */
  private $yaml;

  public static function getModuleInfo() {
    return [
      'title' => 'RockMigrations',
      'version' => '0.0.3',
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
    $this->watchlist = $this->wire(new WireData());
    $this->watch($this->wire->config->paths->site."migrate.yaml");
    $this->watch($this->wire->config->paths->site."migrate.php");
  }

  public function init() {
    $this->wire('rockmigrations', $this);
    $this->rm1(); // load RM1 (install it)
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
    $lastrun = $this->wire->cache->get(self::cachename);
    if($lastrun < $lastmodified) {
      $this->log('Running migrations...');
      $this->wire->cache->save(self::cachename, time(), WireCache::expireNever);
      // bd($this->watchlist);
      foreach($this->watchlist as $path=>$options) {
        if(!$options['migrate']) continue;
        $this->log("Migrating $path");
        $migrate = $this->wire->files->render($path, $options['vars']);
        $this->migrate($migrate);
      }
    }
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

  public function __debugInfo() {
    $lastrun = "never";
    if($this->lastrun) {
      $lastrun = date("Y-m-d H:i:s", $this->lastrun);
      $lastrun = $this->lastrun." ($lastrun)";
    }
    return [
      'Version' => $this->getModuleInfo()['version'],
      'lastrun' => $lastrun,
      'watch' => $this->watchlist,
    ];
  }

}
