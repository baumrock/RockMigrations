<?php namespace ProcessWire;
/** @var RockMigrations $rm */
$rm = $this->wire->modules->get('RockMigrations');

// install tracydebugger
// I prefer to set settings in config-local.php
$rm->installModule(
  "TracyDebugger",
  "https://github.com/adrianbj/TracyDebugger/archive/refs/heads/master.zip"
);

$rm->installModule("SessionHandlerDB");

$rm->installModule(
  "ProcessDatabaseBackups",
  "https://github.com/ryancramerdesign/ProcessDatabaseBackups/archive/master.zip"
);

$rm->installModule(
  "ProcessWireUpgrade",
  "https://github.com/ryancramerdesign/ProcessWireUpgrade/archive/master.zip"
);

$rm->installModule("PagePathHistory");

$rm->installModule(
  "Less",
  "https://github.com/ryancramerdesign/Less/archive/main.zip"
);
$rm->installModule(
  "AdminStyleRock",
  "https://github.com/baumrock/AdminStyleRock/archive/refs/heads/main.zip",
);

$rm->installModule(
  "AdminHelperLinks",
  "https://github.com/uiii/AdminHelperLinks/archive/main.zip"
);

