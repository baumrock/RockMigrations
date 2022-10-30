<?php

namespace ProcessWire;

/**
 * Script to trigger migrations from the commandline
 * Usage: php site/modules/RockMigrations/migrate.php
 **/
chdir(__DIR__);
define('RockMigrationsCLI', true);
include('../../../index.php');
if (!isset($wire)) die("SH... Bootstrapping ProcessWire failed!");
/** @var ProcessWire $wire */
/** @var RockMigrations $rm */
$rm = $wire->modules->get('RockMigrations');
$rm->run();
