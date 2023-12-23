<?php

namespace ProcessWire;

/**
 * Install PagePathHistory Module
 */

$wire = wire();
$rm = rockmigrations();

$rm->installModule("PagePathHistory");
$wire->message("PagePathHistory has been installed");
