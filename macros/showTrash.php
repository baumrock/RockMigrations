<?php

namespace ProcessWire;

/**
 * Show trash in the pagetree for non-superusers
 */

$wire = wire();
$rm = rockmigrations();

$rm->setModuleConfig('ProcessPageList', [
  'useTrash' => true, // show trash in tree for non superusers
]);
$wire->message("Showing trash option in the page tree");
