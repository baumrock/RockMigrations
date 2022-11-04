<?php

namespace ProcessWire;

/** @var RockMigrations $rm */
$rm = $modules->get('RockMigrations');

// install tracydebugger
// no settings here because I prefer to set them in config-local.php
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
// install adminstylerock as submodule

$rm->installModule(
  "AdminHelperLinks",
  "https://github.com/uiii/AdminHelperLinks/archive/main.zip"
);

$rm->installSiteModule();
