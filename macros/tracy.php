<?php

namespace ProcessWire;

/**
 * Install TracyDebugger
 */

$wire = wire();
$rm = rockmigrations();

if ($rm->modules->isInstalled("TracyDebugger")) {
  $wire->message("TracyDebugger is already installed");
} else {
  $rm->installModule(
    "TracyDebugger",
    "https://github.com/adrianbj/TracyDebugger/archive/refs/heads/master.zip"
  );
  $wire->message("TracyDebugger has been installed");
}
