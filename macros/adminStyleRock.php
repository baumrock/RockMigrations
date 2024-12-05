<?php

namespace ProcessWire;

/**
 * Install Less Module and AdminStyleRock
 */

$wire = wire();
$rm = rockmigrations();

if ($rm->modules->isInstalled("Less")) {
  $wire->message("Less is already installed");
} else {
  $rm->installModule(
    "Less",
    "https://github.com/ryancramerdesign/Less/archive/main.zip"
  );
  $wire->message("Less has been installed");
}

if ($rm->modules->isInstalled("AdminStyleRock")) {
  $wire->message("AdminStyleRock is already installed");
} else {
  $rm->installModule(
    "AdminStyleRock",
    "https://github.com/baumrock/AdminStyleRock/archive/main.zip"
  );
  $wire->message("AdminStyleRock has been installed");
}
