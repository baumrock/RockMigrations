<?php namespace RockMigrations;

use ProcessWire\Config;
use ProcessWire\Paths;
use ProcessWire\WireData;

// we make sure that the current working directory is the PW root
chdir(dirname(dirname(dirname(__DIR__))));
require_once "wire/core/ProcessWire.php";
class Deployment extends WireData {

  const keep = 2;

  public $branch;
  public $delete = [];
  public $dry = false;
  private $isVerbose = false;
  public $paths;
  private $php = "php";
  private $robots;
  public $share = [];

  public function __construct($argv, $path) {
    $this->paths = new WireData();

    // get branch from script arguments
    $this->branch = '';
    if($argv AND count($argv)>1) $this->branch = $argv[1];

    // path to the current release
    $this->paths->release = getcwd();

    // path to the root that contains all releases and current + shared folder
    $this->paths->root = dirname($this->paths->release);
    if(strpos($this->paths->root, $path) !== 0) {
      // the current root path does not match the provided path argument
      // this means we are not on the deployment server, so we make it dry
      $this->echo("Not in defined path - running dry...");
      $this->dry();
    }

    // path to shared folder
    $this->paths->shared = $this->paths->root."/shared";

    // setup default share directories
    $this->share = [
      '/site/config-local.php',
      '/site/assets/files',
      '/site/assets/logs',
    ];

    // setup default delete directories
    $this->delete = [
      '/.ddev',
      '/.git',
      '/.github',
      '/site/assets/backups',
      '/site/assets/cache',
      '/site/assets/ProCache',
      '/site/assets/pwpc-*',
      '/site/assets/sessions',
    ];

  }

  public function addRobots() {
    if(!$this->robots()) return;
    $this->echo("Hiding site from search engines via robots.txt");
    $release = $this->paths->release;
    $src = __DIR__."/robots.txt";
    $this->exec("cp -f $src $release/robots.txt");
  }

  /**
   * Delete files from release
   * @return void
   */
  public function delete($files = null, $reset = false) {
    if(is_array($files)) {
      if($reset) $this->delete = [];
      $this->delete = array_merge($files, $this->delete);
    }
    elseif($files === null) {
      // execute deletion
      $this->echo("Deleting files...");
      foreach($this->delete as $file) {
        $file = trim(Paths::normalizeSeparators($file), "/");
        $this->echo("  $file");
        $this->exec("rm -rf $file");
      }
      $this->echo("Done");
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
  public function deleteOldReleases($keep = null, $rename = true) {
    if(!$keep) $keep = self::keep;
    $this->echo("Cleaning up old releases...");
    $folders = glob($this->paths->root."/release-*");
    rsort($folders);
    $cnt = 0;
    $revert = "You can revert like this:";
    $revert .= "\n  cd {$this->paths->root}";
    foreach($folders as $folder) {
      $cnt++;
      $base = basename($folder);
      if($cnt>$keep+1) {
        $this->echo("delete $base", 2);
        $this->exec("rm -rf $folder");
        continue;
      }
      if($rename) {
        if($cnt>1) {
          $arrow = str_pad(">", 10-$cnt, " ", STR_PAD_LEFT);
          $this->echo("rename $base $arrow $base-", 2);
          $date = date("Y-m-d H:i:s", filemtime($folder));
          $revert .= "\n  $date >> ln -snf $base- current";
          $this->exec("mv $folder $folder-");
          $folder = "$folder-";
          $base = "$base-";
        }
        else $this->echo("create $base", 2);
      }
    }
    $folders = glob($this->paths->root."/tmp-release-*");
    if(count($folders)) $this->echo("Deleting tmp folders...");
    foreach($folders as $folder) {
      $base = basename($folder);
      $this->echo("delete $base", 2);
      $this->exec("rm -rf $folder");
    }
    $this->echo($revert);
    $this->echo("Done");
  }

  public function dry($flag = true) {
    $this->dry = $flag;
  }

  /**
   * Create DB dump
   */
  public function dumpDB() {
    if($this->dry) return $this->echo("Dry run - skipping dumpDB()...");
    $current = $this->paths->root."/current";
    $configFile = "$current/site/config.php";
    if(!is_file($configFile)) {
      return $this->echo("No current release - skipping dumpDB()...");
    }
    try {
      $this->echo("Trying to create a DB dump of old release...");

      // load config
      $config = new Config();
      include $configFile;
      $dir = "$current/site/assets/backups/database";
      $sql = "$dir/rm-deploy.sql";
      $this->exec("
        mkdir -p $dir
        mysqldump -h'{$config->dbHost}' -P'{$config->dbPort}' -u'{$config->dbUser}' -p'{$config->dbPass}' {$config->dbName} > $sql
        ");
      $this->echo("old: ".realpath($sql), 2);
      $this->echo("new: ".str_replace("/site/", "-/site/", realpath($sql)), 2);
      $this->echo("Done");
    } catch (\Throwable $th) {
      $this->echo($th->getMessage());
    }
  }

  /**
   * Echo message to stout
   */
  public function echo($msg = '', $indent = 0) {
    if(is_int($indent)) $indent = str_pad('', $indent);
    if(is_string($msg)) {
      echo "{$indent}$msg\n";
    }
    elseif(is_array($msg)) {
      if(count($msg)) echo print_r($msg, true)."\n";
    }
  }

  /**
   * Execute command and echo output
   */
  public function exec($cmd, $echoCmd = false) {
    if($this->dry) $echoCmd = true;
    if($echoCmd) $this->echo($cmd);
    if($this->dry) return;
    exec($cmd, $out);
    $this->echo($out);
    return $out;
  }

  /**
   * Finish deployment
   * This removes the tmp- prefix from the deployment folder
   * and updates the "current" symlink
   * @return void
   */
  public function finish($keep = null) {
    if($this->dry) {
      $this->echo("Dry run - skipping finish()...");
      return;
    }
    $oldPath = $this->paths->release;
    $newName = substr(basename($oldPath), 4);
    $this->echo("Finishing deployment - updating symlink...");
    $this->exec("mv $oldPath {$this->paths->root}/$newName");
    $this->exec("
      cd {$this->paths->root}
      ln -snf $newName current
    ");
    $this->deleteOldReleases($keep);
    $this->exec("touch {$this->paths->root}/current/site/deployed.txt");
  }

  public function halt($msg) {
    $this->echo($msg);
    die();
  }

  public function hello() {
    $this->echo("
      #########################################
      RockMigrations Deployment by baumrock.com
      #########################################
    ");
    $this->echo("Creating new release at {$this->paths->release}\n");
  }

  /**
   * Run RockMigrations
   */
  public function migrate() {
    $release = $this->paths->release;
    $file = "$release/site/modules/RockMigrations/migrate.php";
    if(!is_file($file)) return $this->echo("RockMigrations not found...");
    $this->echo("Trigger RockMigrations...");
    $php = $this->php();
    try {
      $out = $this->exec("$php $file", true);
    } catch (\Throwable $th) {
      $this->halt($th->getMessage());
    }
    if(!is_array($out)) return $this->halt("migrate.php failed");
  }

  /**
   * Print paths
   */
  public function paths() {
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
  public function php($phpCommand = '') {
    if($phpCommand) $this->php = $phpCommand;
    return $this->php;
  }

  /**
   * Get or set robots flag
   * TRUE = add robots.txt to root directory
   * FALSE = do not add robots.txt to root
   * NULL = get current value
   *
   * By default it will be FALSE for master and main branch and TRUE for
   * all other branches
   */
  public function robots($val = null) {
    if($val === null) {
      if($this->robots === null) {
        if($this->branch == 'main') $this->robots = false;
        elseif($this->branch == 'master') $this->robots = false;
        else $this->robots = true;
      }
      return $this->robots;
    }
    $this->robots = $val;
  }

  /**
   * Run default actions
   */
  public function run($keep = null) {
    $this->hello();
    $this->share();
    $this->delete();
    $this->secure();
    $this->dumpDB();
    $this->migrate();
    $this->addRobots();
    $this->finish($keep);
  }

  /**
   * Secure file and folder permissions
   * @return void
   */
  public function secure() {
    $release = $this->paths->release;
    $shared = $this->paths->shared;
    $this->echo("Securing file and folder permissions...");
    $this->exec("      find $release -type d -exec chmod 755 {} \;
      find $release -type f -exec chmod 644 {} \;
      chmod 440 $release/site/config.php
      chmod 440 $shared/site/config-local.php", true);
    $this->echo("Done");
  }

  /**
   * Share files and folders across releases
   * Shared assets will be symlinked: releases folder --> shared folder
   *
   * Usage:
   * $deploy->share([
   *   // symlink the foo folder
   *   '/foo',
   *
   *   // push data from repo to shared folder and then create a symlink
   *   '/site/assets/files/123' => 'push',
   * ]);
   * @return void
   */
  public function share($files = null, $reset = false) {
    if(is_array($files)) {
      if($reset) $this->share = [];
      $this->share = array_merge($files, $this->share);
    }
    elseif($files === null) {
      $this->echo("Setting up shared files...");

      $release = $this->paths->release;
      $shared = $this->paths->shared;
      $this->echo($this->share);
      foreach($this->share as $k=>$v) {
        $file = $v;

        // push to shared folder or just create link?
        $type = 'link';
        if(is_string($k)) {
          $file = $k;
          $type = $v;
        }

        // prepare the src path
        $file = trim(Paths::normalizeSeparators($file), "/");
        $from = Paths::normalizeSeparators("$release/$file");
        $toAbs = Paths::normalizeSeparators("$shared/$file");
        $isfile = !!pathinfo($from, PATHINFO_EXTENSION);
        $toDir = dirname($toAbs);

        // we create relative symlinks
        $level = substr_count($file, "/");
        $to = "shared/$file";
        for($i=0;$i<=$level;$i++) $to = "../$to";

        if($isfile) {
          $this->echo("  file $from");
          $this->exec("ln -sf $to $from");
        }
        else {
          $this->echo("  directory $from");

          // push means we only push files to the shared folder
          // but we do not create a symlink. This can be used to push site
          // translations where the files folder itself is already symlinked
          if($type == 'push') {
            $this->exec("
              rm -rf $toAbs
              mkdir -p $toDir
              mv $from $toDir
            ", $this->isVerbose);
          }
          else {
            $this->exec("
              mkdir -p $toAbs
              rm -rf $from
              ln -snf $to $from
            ", $this->isVerbose);
          }
        }
      }

      $this->echo("Done");
    }
  }

  /**
   * Make output more verbose
   */
  public function verbose() {
    $this->isVerbose = true;
  }

}
