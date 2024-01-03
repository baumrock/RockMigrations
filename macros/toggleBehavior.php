<?php

namespace ProcessWire;

/**
 * Use consistent toggle behaviour in AdminThemeUikit
 */

$wire = wire();
$rm = rockmigrations();

$rm->setModuleConfig('AdminThemeUikit', [
  // use consistent inputfield clicks
  // see https://github.com/processwire/processwire/pull/169
  'toggleBehavior' => 1,
]);
$wire->message("Using consistent toggle behaviour");
