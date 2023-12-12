<?php

namespace RockMigrations;

use RockShell\Command;

class RmDeploy extends Command
{
  public function config()
  {
    $this->setDescription("Setup the initial folder structure for RM deployments");
  }

  public function handle()
  {
    $wire = $this->wire();
    $src = rtrim($wire->config->paths->root, "/");

    if (!$this->confirm("This will create a new folder structure in $src - continue?")) {
      return self::SUCCESS;
    }

    // cleanup
    exec("cd $src && rm -rf release-1");
    exec("cd $src && rm -rf current");
    exec("cd $src && rm -rf shared");

    // create folders
    $wire->files->mkdir($release = "$src/release-1");
    $wire->files->mkdir($shared = "$src/shared");
    exec("cd $src && ln -snf release-1 current");

    // copy files
    foreach (glob("$src/{.,}*", GLOB_BRACE) as $file) {
      $f = basename($file);
      if (
        $f === "."
        || $f === ".."
        || $f === "release-1"
        || $f === "current"
        || $f === "shared"
      ) continue;
      if ($f === ".github" || $f === ".vscode") {
        $wire->files->rmdir($file);
        continue;
      }

      $this->write("Copy $file ...");
      exec("cd $src && cp -r $f $release");
      if (is_file($file)) exec("rm $file");
      elseif (is_dir($file)) exec("rm -rf $file");
    }

    // copy shared assets to shared folder
    // symlinks will be created by first deployment
    $this->write("Copy files to shared folder ...");
    exec("mkdir -p $shared/site/assets");
    exec("cp -r $release/site/assets/files $shared/site/assets");
    exec("cp -r $release/site/assets/backups $shared/site/assets");
    exec("cp $release/site/config-local.php $shared/site/config-local.php");

    return self::SUCCESS;
  }
}
