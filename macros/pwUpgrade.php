<?php

namespace ProcessWire;

/**
 * Install ProcessWireUpgrade
 */

$wire = wire();
$rm = rockmigrations();

if ($rm->modules->isInstalled("ProcessWireUpgrade")) {
  $wire->message("ProcessWireUpgrade is already installed");
} else {
  $rm->installModule(
    "ProcessWireUpgrade",
    "https://github.com/ryancramerdesign/ProcessWireUpgrade/archive/master.zip"
  );
  $wire->message("ProcessWireUpgrade has been installed");
}
