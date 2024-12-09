<?php

// Define a constant to indicate that we want to get the PHP path
// This will prevent $deploy->run() and print the php version instead.
define('GET-PHP', true);

// Include the deployment script
// This will load a new instance of Deployment which will then either load
// the php version via loadConfig or it will show the version set in deploy.php
// via $deploy->php('/foo/bar/php');
include __DIR__ . '/deploy.php';
