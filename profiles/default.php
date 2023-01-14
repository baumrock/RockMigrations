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
$rm->installModule("LazyCron");
$rm->installModule(
  "CronjobDatabaseBackup",
  [
    "cycle" => "everyDay",
    "max" => 14,
  ],
  "https://github.com/kixe/CronjobDatabaseBackup/archive/master.zip"
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
  [
    // 'rockprimary' => '#00ff00',
    // 'logo' => 'site/templates/img/yourlogo.svg',
  ],
  "https://github.com/baumrock/AdminStyleRock/archive/refs/heads/main.zip"
);

$rm->installModule(
  "RockFrontend",
  [
    'features' => [
      'postCSS',
      'minify',
      'topbar',
    ],
    'migrations' => [
      'favicon',
      'ogimage',
      'footerlinks',
    ],
  ],
  "https://github.com/baumrock/RockFrontend/archive/refs/heads/main.zip"
);

$rm->installModule(
  "AdminHelperLinks",
  "https://github.com/uiii/AdminHelperLinks/archive/main.zip"
);

$rm->installSiteModule();
