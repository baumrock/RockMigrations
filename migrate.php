<?php namespace ProcessWire;
/**
 * Script to trigger migrations from the commandline
 * Usage: php site/modules/RockMigrations/migrate.php
 **/
chdir(__DIR__);
define('RockMigrationsCLI', true);
include('../../../index.php');
if(!isset($wire)) die("SH... Bootstrapping ProcessWire failed!");
/** @var ProcessWire $wire */
/** @var RockMigrations $rm */
$rm = $wire->modules->get('RockMigrations');

// this is important to prevent permission issues
$rm->sudo();

// we refresh modules so that migrations can access new modules
$rm->refresh();

// now we trigger the migrations
$rm->run();
