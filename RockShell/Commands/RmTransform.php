<?php

namespace RockMigrations;

use RockShell\Command;

class RmTransform extends Command
{
  public function config()
  {
    $this->setDescription("Transform the projects folder structure to RockMigrations deployments");
  }

  public function handle()
  {
    // load root path via PHP
    // don't load $config via wire() because it will try to create some
    // new cache files on shutdown which will show warnings in the console
    $src = rtrim($this->app->root, "/");

    if (!$this->confirm("This will create a new folder structure in $src - continue?")) {
      return self::SUCCESS;
    }

    // backup current folder
    $name = 'backup-' . date('Y-m-d-His');
    $backupPath = dirname($src) . "/$name";
    if ($this->confirm("Backup current folder to $backupPath?", true)) {
      exec("cp -r $src $backupPath");
    }

    // cleanup
    exec("cd $src && rm -rf release-1");
    exec("cd $src && rm -rf current");
    exec("cd $src && rm -rf shared");

    // create folders
    $release = "$src/release-1";
    exec("mkdir -p $release");
    $shared = "$src/shared";
    exec("mkdir -p $shared");
    exec("cd $src && ln -snf release-1 current");

    // copy files
    foreach (glob("$src/{.,}*", GLOB_BRACE) as $file) {
      $f = basename($file);
      // skip
      if (
        $f === "."
        || $f === ".."
        || $f === "release-1"
        || $f === "current"
        || $f === "shared"
      ) continue;

      if ($f === ".github" || $f === ".vscode") {
        exec("rm -rf $file");
        continue;
      }

      $this->write("Copy $file ...");
      exec("cd $src && cp -r $f $release");
      if (is_file($file)) exec("rm $file");
      elseif (is_dir($file)) exec("rm -rf $file");
    }

    // copy shared assets to shared folder
    $this->write("Copy files to shared folder ...");
    exec("mkdir -p $shared/site/assets");
    exec("cp -r $release/site/assets/files $shared/site/assets");
    exec("cp -r $release/site/assets/backups $shared/site/assets");
    exec("cp $release/site/config-local.php $shared/site/config-local.php");

    // add symlinks for these files
    exec("cd $src && rm -rf $release/site/assets/files && ln -snf ../../../shared/site/assets/files $release/site/assets/files");
    exec("cd $src && rm -rf $release/site/assets/backups && ln -snf ../../../shared/site/assets/backups $release/site/assets/backups");
    exec("cd $src && rm -rf $release/site/config-local.php && ln -snf ../../shared/site/config-local.php $release/site/config-local.php");

    // remove backup folder?
    if ($this->confirm("Remove backup folder $backupPath?")) {
      exec("rm -rf $backupPath");
    }

    // this prevents the following error:
    // Class "Illuminate\Console\Events\CommandFinished" not found
    die();
  }

  public function sudo(): void
  {
    // do nothing
    // this will prevent loading of wire() which will prevent it
    // from trying to create cache files on shutdown
  }
}
