<?php

namespace RockMigrations;

use Exception;
use ProcessWire\Paths;
use ProcessWire\ProcessWire;
use ProcessWire\WireData;
use ProcessWire\WireDatabasePDO;

use function ProcessWire\rockmigrations;

chdir(__DIR__);
chdir("../../../../");
require_once "wire/core/ProcessWire.php";
class Deployment extends WireData
{

  const keep = 2;

  private $after = [];
  private $before = [];
  public $branch;
  public $chown = true; // chown by default
  public $delete = [];
  public $dry = false;
  private $isVerbose = false;
  public $paths;
  private $php = "php";
  private $robots;
  public $share = [];

  public function __construct($argv = null)
  {
    $this->paths = new WireData();
    $this->loadConfig();

    // get branch from script arguments
    $this->branch = '';
    if ($argv and count($argv) > 1) $this->branch = $argv[1];

    // path to the current release
    $this->paths->release = getcwd();

    // path to the root that contains all releases and current + shared folder
    $this->paths->root = dirname($this->paths->release);

    // path to shared folder
    $this->paths->shared = $this->paths->root . "/shared";

    // setup default share directories
    $this->share = [
      '/site/config-local.php',
      '/site/assets/files',
      '/site/assets/logs',
      '/site/assets/backups/database',
      '/site/assets/sessions',
    ];

    // setup default delete directories
    $this->delete = [
      '/.ddev',
      '/.git',
      '/.github',
      '/site/assets/cache',
      '/site/assets/ProCache',
      '/site/assets/pwpc-*',
    ];
  }

  /**
   * Run default actions
   */
  public function run($keep = null)
  {
    // this is for the auto-detect php feature
    if (defined('GET-PHP')) {
      echo $this->php() . "\n";
      return;
    }
    // only run in CLI environment!
    // if we are not in a CLI we are debugging via tracydebugger
    if (php_sapi_name() !== 'cli') {
      bd($this);
      return;
    }

    $this->hello();

    $this->trigger("share", "before");
    $this->share();
    $this->trigger("share", "after");

    $this->trigger("delete", "before");
    $this->delete();
    $this->trigger("delete", "after");

    $this->trigger("secure", "before");
    $this->secure();
    $this->trigger("secure", "after");

    $this->trigger("dumpDB", "before");
    $this->dumpDB();
    $this->trigger("dumpDB", "after");

    $this->trigger("cleanupDB", "before");
    $this->cleanupDB();
    $this->trigger("cleanupDB", "after");

    $this->trigger("migrate", "before");
    $this->migrate();
    $this->trigger("migrate", "after");

    $this->trigger("addRobots", "before");
    $this->addRobots();
    $this->trigger("addRobots", "after");

    $this->trigger("chown", "before");
    $this->chown();
    $this->trigger("chown", "after");

    $this->trigger("finish", "before");
    $this->finish();
    $this->trigger("finish", "after");

    $this->trigger("healthcheck", "before");
    $this->healthcheck();
    $this->trigger("healthcheck", "after");

    $this->section("Deployment done :)");
  }

  public function addRobots()
  {
    if (!$this->robots()) return;
    $this->section("Hide site from search engines via robots.txt");
    $release = $this->paths->release;
    $src = __DIR__ . "/../stubs/robots.txt";
    $this->exec("cp -f $src $release/robots.txt");
  }

  public function after($where, $callback)
  {
    $after = $this->after;
    $after[$where] = $callback;
    $this->after = $after;
  }

  public function before($where, $callback)
  {
    $before = $this->before;
    $before[$where] = $callback;
    $this->before = $before;
  }

  /**
   * Analyze output of a php exec() command
   * Show warnings or exit on errors
   */
  public function checkForWarningsAndErrors($output)
  {
    if (!is_array($output)) return;
    foreach ($output as $line) {
      if (str_starts_with($line, "Error: ")) $this->exit($line);
      if (str_starts_with(
        $line,
        "404 page not found (no site configuration or install.php available)"
      )) $this->exit($line);
    }
  }

  /**
   * chown files based on root folder
   * @return void
   */
  protected function chown()
  {
    if (!$this->chown) {
      $this->echo("chown was disabled in deploy.php");
      return;
    }
    $root = $this->paths->root;
    $owner = fileowner($root);
    $group = filegroup($root);
    $this->section("Setting owner and group based on root folder...");
    $this->echo("Usage: Can be disabled via \$deploy->chown = false;");
    $this->exec("chown -R $owner:$group $root", true);
    $this->ok();
  }

  /**
   * Remove all FileCompiler entries in the caches table
   * This is to remove filecompiler entries that hold old release paths.
   */
  public function cleanupDB()
  {
    if ($this->dry) return $this->echo("Dry run - skipping cleanupDB()...");
    try {
      $this->section("Cleanup database cache");
      $this->sql("DELETE FROM `caches` WHERE `name` LIKE 'FileCompiler__%'");

      // reset autoload cache to make sure we don't run into issues
      $this->sql("DELETE FROM `caches` WHERE `name` = 'autoload-classloader-classes'");
      $this->sql("DELETE FROM `caches` WHERE `name` = 'autoload-repeater-pageclasses'");

      $this->ok();
    } catch (\Throwable $th) {
      $this->echo($th->getMessage());
    }
  }

  /**
   * Delete files from release
   *
   * Usage:
   * $deploy->delete("/site/assets/foo");
   *
   * Usage with array:
   * $deploy->delete([
   *   "/site/assets/foo",
   *   "/site/assets/bar",
   * ]);
   *
   * @return void
   */
  public function delete($files = null, $reset = false)
  {
    if (is_string($files)) $files = [$files];
    if (is_array($files)) {
      if ($reset) $this->delete = [];
      $this->delete = array_merge($files, $this->delete);
    } elseif ($files === null) {
      // execute deletion
      $this->section("Deleting files...");
      $this->echo("Usage: \$deploy->delete('/site/assets/foo');");
      foreach ($this->delete as $file) {
        $file = trim(Paths::normalizeSeparators($file), "/");
        $this->echo("  $file");
        $this->exec("rm -rf $file");
      }
      $this->ok();
    }
  }

  /**
   * Cleanup old releases and keep given number
   *
   * keep=2 means we keep current + 2 old releases (overall 3)
   *
   * This does also rename old release folders to make symlinks aware of the
   * change without rebooting the server or reloading php-fpm
   */
  public function deleteOldReleases($keep = null, $rename = true)
  {
    if (!$keep) $keep = self::keep;
    $folders = glob($this->paths->root . "/release-*");
    rsort($folders);
    $cnt = 0;
    $revert = "You can revert like this:";
    $revert .= "\n  cd {$this->paths->root}";
    foreach ($folders as $folder) {
      $cnt++;
      $base = basename($folder);
      if ($cnt > $keep + 1) {
        $this->echo("[delete] $base", 2);
        $this->exec("rm -rf $folder");
        continue;
      }
      if ($rename) {
        if ($cnt > 1) {
          $this->echo("[rename] $base-", 2);
          $date = date("Y-m-d H:i:s", filemtime($folder));
          $revert .= "\n  [$date] ln -snf $base- current";
          $this->exec("mv $folder $folder-");
          $folder = "$folder-";
          $base = "$base-";
        } else $this->echo("create $base");
      }
    }
    $folders = glob($this->paths->root . "/tmp-release-*");
    if (count($folders)) $this->echo("Deleting tmp folders...");
    foreach ($folders as $folder) {
      $base = basename($folder);
      $this->echo("[delete] $base", 2);
      $this->exec("rm -rf $folder");
    }
    $this->echo($revert);
    $this->ok();
  }

  public function die($msg)
  {
    die("$msg\n");
  }

  public function dry($flag = true)
  {
    $this->dry = $flag;
  }

  /**
   * Create DB dump
   */
  public function dumpDB($pwroot = null)
  {
    if ($this->dry) return $this->echo("Dry run - skipping dumpDB()...");
    if (!$pwroot) $pwroot = $this->paths->root . "/current";
    try {
      $this->section("Database Dump");
      $this->echo("Trying to create a DB dump of old release...");

      if (!is_file($f = "$pwroot/wire/config.php")) throw new Exception("$f not found");
      if (!is_file($f = "$pwroot/site/config.php")) throw new Exception("$f not found");
      $config = ProcessWire::buildConfig($pwroot);

      if (!$config->dbHost) throw new Exception("No dbHost");
      if (!$config->dbUser) throw new Exception("No dbUser");
      if (!$config->dbPass) throw new Exception("No dbPass");
      if (!$config->dbPort) throw new Exception("No dbPort");

      $dir = "$pwroot/site/assets/backups";
      $sql = "$dir/rm-deploy.sql";
      $this->exec("
        mkdir -p $dir
        mysqldump --protocol tcp -h'{$config->dbHost}' -P'{$config->dbPort}' -u'{$config->dbUser}' -p'{$config->dbPass}' {$config->dbName} > $sql
        ");

      $file = realpath($sql);
      if (is_file($file)) $this->echo("Dumped to $file");
      else $this->echo("WARNING: DB dump failed");
      $this->ok();
    } catch (\Throwable $th) {
      $this->echo($th->getMessage());
    }
  }

  /**
   * Echo message to stout
   */
  public function echo($msg = '', $indent = 0)
  {
    if (is_int($indent)) $indent = str_pad('', $indent);
    if (is_string($msg)) {
      echo "{$indent}$msg\n";
    } elseif (is_array($msg)) {
      if (count($msg)) echo print_r($msg, true);
    }
  }

  /**
   * Execute command and echo output
   */
  public function exec($cmd, $echoCmd = false)
  {
    if ($this->dry) $echoCmd = true;
    if ($echoCmd) $this->echo($cmd);
    if ($this->dry) return;
    exec($cmd, $out);
    $this->echo($out);
    return $out;
  }

  public function exit($msg)
  {
    $this->echo("❌ $msg");
    // dont use a string here otherwise the github action will not fail!
    exit(1);
  }

  /**
   * Finish deployment
   * This removes the tmp- prefix from the deployment folder
   * and updates the "current" symlink
   * @return void
   */
  public function finish($keep = null)
  {
    $oldPath = $this->paths->release;
    $newName = substr(basename($oldPath), 4);
    $this->section("Finishing deployment - updating symlink...");
    $this->exec("mv $oldPath {$this->paths->root}/$newName");
    $this->exec("
      cd {$this->paths->root}
      ln -snf $newName current
    ");

    // chown symlink?
    if ($this->chown) {
      $this->echo("Updating symlink permissions");
      $root = $this->paths->root;
      $owner = fileowner($root);
      $group = filegroup($root);
      $this->exec("chown $owner:$group $root/current", true);
    }

    $this->deleteOldReleases($keep);
  }

  /**
   * @return WireDatabasePDO|void
   */
  public function getDB()
  {
    try {
      $pwroot = $this->paths->root . "/current";
      if (!is_file($f = "$pwroot/wire/config.php")) throw new Exception("$f not found");
      if (!is_file($f = "$pwroot/site/config.php")) throw new Exception("$f not found");
      $config = ProcessWire::buildConfig($pwroot);

      if (!$config->dbHost) throw new Exception("No dbHost");
      if (!$config->dbUser) throw new Exception("No dbUser");
      if (!$config->dbName) throw new Exception("No dbName");
      if (!$config->dbPass) throw new Exception("No dbPass");
      if (!$config->dbPort) throw new Exception("No dbPort");

      $dsn = "mysql:dbname={$config->dbName};host={$config->dbHost};port={$config->dbPort}";
      return new WireDatabasePDO($dsn, $config->dbUser, $config->dbPass);
    } catch (\Throwable $th) {
      $this->echo($th->getMessage());
    }
  }

  public function healthcheck()
  {
    $this->section("Health-Check");

    // are there any tmp folders left?
    $folders = glob($this->paths->root . "/tmp-release-*");
    if (count($folders)) $this->exit("Found some tmp-folders. It seems something went wrong...");
    else $this->ok("No tmp-folders left");
  }

  public function hello()
  {
    // https://patorjk.com/software/taag/#p=display&f=Standard&t=RockMigrations
    $this->echo("
      _____            _    __  __ _                 _   _
      |  _ \ ___   ___| | _|  \/  (_) __ _ _ __ __ _| |_(_) ___  _ __  ___
      | |_) / _ \ / __| |/ / |\/| | |/ _` | '__/ _` | __| |/ _ \| '_ \/ __|
      |  _ < (_) | (__|   <| |  | | | (_| | | | (_| | |_| | (_) | | | \__ \
      |_| \_\___/ \___|_|\_\_|  |_|_|\__, |_|  \__,_|\__|_|\___/|_| |_|___/
                                     |___/                 by baumrock.com
    ");
    $this->echo("Creating new release at {$this->paths->release}");
    $this->echo("Root folder name: " . $this->rootFolderName());
  }

  private function loadConfig(): void
  {
    // read php version to use from file rockshell-config.php
    // you can either place this config one level above the "current" symlink
    // or in /site/config-rockshell.php
    // later files have priority and overwrite properties already set
    $configs = [
      __DIR__ . '/../../../../../config-rockshell.php',
      __DIR__ . '/../../../config-rockshell.php',
    ];
    foreach ($configs as $config) {
      if (!is_file($config)) continue;
      require_once $config;
      try {
        $config = (array)$config;
      } catch (\Throwable $th) {
        throw new Exception("Config must expose a \$config array variable.");
      }
      // set config params
      foreach ($config as $key => $val) {
        if ($key === 'php') $this->php($val);
      }
    }
  }

  /**
   * Run RockMigrations
   */
  public function migrate()
  {
    $release = $this->paths->release;
    $file = "$release/site/modules/RockMigrations/migrate.php";
    if (!is_file($file)) return $this->echo("RockMigrations not found ...");
    $this->section("Trigger RockMigrations ...");
    $php = $this->php();
    try {
      $out = $this->exec("$php $file", true);
      $this->checkForWarningsAndErrors($out);
    } catch (\Throwable $th) {
      $this->exit($th->getMessage());
    }
    if (!$this->dry and !is_array($out)) return $this->exit("migrate.php failed");
    $this->ok();
  }

  /**
   * Remove path from delete array
   */
  public function nodelete(string|array $path): void
  {
    if (is_array($path)) {
      foreach ($path as $p) $this->undelete($p);
      return;
    }
    if (($key = array_search($path, $this->delete)) !== false) {
      unset($this->delete[$key]);
    }
  }

  public function ok($msg = "Done")
  {
    $this->echo("✅ $msg");
  }

  /**
   * Print paths
   */
  protected function paths()
  {
    $this->echo($this->paths->getArray());
  }

  /**
   * Get or set php command that will be used to trigger the migrate script
   * This needs to be configurable in case the CLI php version is different
   * than the webroot (eg 7.4 vs. 8.1)
   *
   * Example:
   * $deploy->php('keyhelp-php81');
   *
   * @return string
   */
  public function php($phpCommand = '')
  {
    if ($phpCommand) $this->php = $phpCommand;
    return $this->php;
  }

  /**
   * Push folder to shared folder and create symlink
   *
   * Usage:
   * $deploy->push('/site/assets/files/123');
   */
  public function push($folder)
  {
    return $this->share([$folder => 'push']);
  }

  /**
   * Get or set robots flag
   *
   * TRUE means that the deny-all robots.txt will be written to root
   *
   * By default it will be FALSE for master and main branch and TRUE for
   * all other branches
   */
  public function robots($val = null)
  {
    if ($val === null) {
      if ($this->robots === null) {
        if ($this->branch == 'main') $this->robots = false;
        elseif ($this->branch == 'master') $this->robots = false;
        else $this->robots = true;
      }
      return $this->robots;
    }
    $this->robots = $val;
  }

  /**
   * Add robots.txt to deny all robot requests
   *
   * This method is the same as robots() but the name is more verbose so the
   * deployment script gets better readable.
   *
   * Usage:
   * $deploy->robotsDenyAll(true); // overwrite robots.txt (deny all)
   *
   * $deploy->robotsDenyAll(false); // dont overwrite robots.txt
   *
   * deny robot requests of the root folder name is NOT yoursite.com
   * that means if the root folder name is staging.yoursite.com all robot
   * requests will be denied because on deploy RM will copy the deny-all
   * robots.txt to the root folder of your staging site
   * $deploy->robotsDenyAll(
   *   $deploy->rootFolderName() != 'yoursite.com'
   * );
   */
  public function robotsDenyAll($bool = false)
  {
    return $this->robots(!!$bool);
  }

  public function rootFolderName(): string
  {
    return basename($this->paths->root);
  }

  /**
   * Write a new section line to the log
   */
  public function section($str)
  {
    $len = is_string($str) ? strlen($str) : 20;
    $this->echo("");
    $this->echo(str_repeat("#", $len));
    $this->echo($str);
    $this->echo(str_repeat("#", $len));
  }

  /**
   * Secure file and folder permissions
   * @return void
   */
  public function secure()
  {
    $release = $this->paths->release;
    $shared = $this->paths->shared;
    $this->section("Securing file and folder permissions...");
    $this->exec("chmod 440 $release/site/config.php
      chmod 440 $shared/site/config-local.php", true);
    $this->ok();
  }

  /**
   * Share files and folders across releases
   * Shared assets will be symlinked: releases folder --> shared folder
   *
   * Usage:
   * $deploy->share("/your/file.txt");
   *
   * $deploy->share([
   *   // symlink the foo folder
   *   '/foo',
   *
   *   // push data from repo to shared folder and then create a symlink
   *   '/site/assets/files/123' => 'push',
   * ]);
   * @return void
   */
  public function share($files = null, $reset = false)
  {
    if (is_string($files)) $files = [$files];
    if (is_array($files)) {
      if ($reset) $this->share = [];
      $this->share = array_merge($files, $this->share);
    } elseif ($files === null) {
      $this->section("Setting up shared files...");
      $this->echo("Usage:");
      $this->echo("Symlink to existing shared asset: \$deploy->share('/your/file.txt');");
      $this->echo("Copy asset to shared folder and then symlink: \$deploy->push('/your/file.txt');");

      $release = $this->paths->release;
      $shared = $this->paths->shared;

      $this->echo("Config of shared items:");
      $this->echo($this->share);
      $this->echo("Processing items...");
      foreach ($this->share as $k => $v) {
        $file = $v;

        // push to shared folder or just create link?
        $type = 'link';
        if (is_string($k)) {
          $file = $k;
          $type = $v;
        }

        // prepare the src path
        $file = trim(Paths::normalizeSeparators($file), "/");
        $from = Paths::normalizeSeparators("$release/$file");
        $toAbs = Paths::normalizeSeparators("$shared/$file");
        $isfile = !!pathinfo($from, PATHINFO_EXTENSION);
        $toDir = dirname($toAbs);
        $fromDir = dirname($from);

        // we create relative symlinks
        $level = substr_count($file, "/");
        $to = "shared/$file";
        for ($i = 0; $i <= $level; $i++) $to = "../$to";

        if ($isfile) {
          $this->echo("  [file]        $from");
          if (!is_file($toAbs)) {
            if (basename($toAbs) == 'config-local.php') {
              $configDir = dirname($toAbs);
              $this->exec("mkdir -p $configDir");
              $rand1 = bin2hex(random_bytes(rand(15, 30)));
              $rand2 = bin2hex(random_bytes(rand(15, 30)));
              file_put_contents(
                $toAbs,
                "<?php\n// file created by RockMigrations"
                  . "\n// put your site-specific config here\n"
                  . "\n// you can use these random salts:"
                  . "\n// \$config->userAuthSalt = '$rand1';"
                  . "\n// \$config->tableSalt = '$rand2';"
              );
            }
          }
          $this->exec("ln -sf $to $from");
        } else {
          $this->echo("  [directory]   $from");

          // push means we only push files to the shared folder
          // but we do not create a symlink. This can be used to push site
          // translations where the files folder itself is already symlinked
          if ($type == 'push') {
            $this->exec(
              "    rm -rf $toAbs\n" .
                "    mkdir -p $toDir\n" .
                "    mv $from $toDir",
              $this->isVerbose
            );
          } else {
            // regular shared directory
            // first wipe that directory in the temporary release
            // then create a symlink to the shared folder instead
            $this->exec(
              "    mkdir -p $toAbs\n" .
                "    mkdir -p $fromDir\n" .
                "    rm -rf $from\n" .
                "    ln -snf $to $from",
              $this->isVerbose
            );
          }
        }
      }

      $this->ok();
    }
  }

  public function sql(string $query): void
  {
    if (php_sapi_name() !== 'cli') return;
    if (defined('GET-PHP')) return;
    $db = $this->getDB();
    $db->prepare($query)->execute();
  }

  /**
   * Execute before/after callback
   */
  public function trigger($what, $when)
  {
    $array = $this->$when;
    if (!array_key_exists($what, $array)) return;
    $array[$what]($this); // execute callback
  }

  /**
   * Make output more verbose
   */
  public function verbose()
  {
    $this->isVerbose = true;
  }

  public function __debugInfo()
  {
    return [
      'share' => $this->share,
      'delete' => $this->delete,
    ];
  }
}
