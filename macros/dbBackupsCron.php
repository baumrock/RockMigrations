<?php

namespace ProcessWire;

/**
 * Install DB backup strategy using LazyCron (backup every day, max 14 backups)
 */

$wire = wire();
$rm = rockmigrations();

if ($wire->modules->isInstalled("ProcessDatabaseBackups")) {
  $wire->message("ProcessDatabaseBackups is already installed");
} else {
  $rm->installModule(
    "ProcessDatabaseBackups",
    "https://github.com/ryancramerdesign/ProcessDatabaseBackups/archive/master.zip"
  );
}

$rm->installModule("LazyCron");
$wire->message("LazyCron has been installed");

if ($wire->modules->isInstalled("CronjobDatabaseBackup")) {
  $wire->message("CronjobDatabaseBackup is already installed");
} else {
  $rm->installModule(
    "CronjobDatabaseBackup",
    [
      "cycle" => "everyDay",
      "max" => 14,
    ],
    "https://github.com/kixe/CronjobDatabaseBackup/archive/master.zip"
  );
}
