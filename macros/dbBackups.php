<?php

namespace ProcessWire;

/**
 * Install ProcessDatabaseBackups
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
