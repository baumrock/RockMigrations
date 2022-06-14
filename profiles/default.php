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
  "ProcessWireUpgrade",
  "https://github.com/ryancramerdesign/ProcessWireUpgrade/archive/master.zip"
);
