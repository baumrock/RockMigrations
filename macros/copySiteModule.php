<?php

namespace ProcessWire;

/**
 * Install Site Module
 */

$wire = wire();
$rm = rockmigrations();

$file = $wire->config->paths->siteModules . "Site/Site.module.php";
if (is_file($file)) {
  $wire->message("Site module file already exists!");
  return;
}

$stub = __DIR__ . "/../stubs/SiteModule.php";
$wire->files->mkdir(dirname($file));
$wire->files->filePutContents($file, $wire->files->fileGetContents($stub));
$wire->message("Please do a modules refresh and install the site module.");
