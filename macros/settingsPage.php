<?php

namespace ProcessWire;

/**
 * Create SettingsPage pageclass and page in tree.
 */

$rm = rockmigrations();
$wire = wire();

$dst = $wire->config->paths->classes . "SettingsPage.php";
if (is_file($dst)) {
  $wire->error("$dst already exists - aborting.");
  return;
}
if ($tpl = $rm->getTemplate('settings')) {
  $wire->error("Template $tpl already exists");
  return;
}

// copy file to /site/classes
$wire->files->copy(__DIR__ . "/../stubs/SettingsPage.php", $dst);

// create template and trigger migrate()
$rm->createTemplate('settings');

$wire->message("SettingsPage has been created!");
