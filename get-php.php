<?php

use RockMigrations\Deployment;

require_once __DIR__ . '/classes/Deployment.php';
$deploy = new Deployment();
echo $deploy->php() . "\n";
