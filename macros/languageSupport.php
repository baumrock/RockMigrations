<?php

namespace ProcessWire;

/**
 * Install Language Support + Language Tabs
 */

$wire = wire();
$rm = rockmigrations();

$rm->installModule('LanguageSupportFields');
$rm->installModule('LanguageSupportPageNames');
$rm->installModule('LanguageTabs');
$rm->setFieldData('title', ['type' => 'textLanguage']);

$wire->message("Installed Language Support");
