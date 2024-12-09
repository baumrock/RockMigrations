<?php

namespace RockMigrations;

require_once __DIR__ . "/classes/Deployment.php";

$customDeployFile = __DIR__ . "/../../deploy.php";
if (file_exists($customDeployFile)) {
  // if /site/deploy.php exists, use it
  include $customDeployFile;
} else {
  // otherwise use the default deploy.php
  $deploy = new Deployment($argv ?? []);
  $deploy->run();
}
