<?php

namespace ProcessWire;

$info = [
  'title' => 'RockMigrations',
  'version' => json_decode(file_get_contents(__DIR__ . "/package.json"))->version,
  'summary' => 'The Ultimate Automation and Deployment-Tool for ProcessWire',
  'autoload' => 2,
  'singular' => true,
  'icon' => 'magic',
  // requires php8.0 because of symfony yaml (also set in composer.json)
  'requires' => [
    'PHP>=8.0',
  ],
  'installs' => [
    'MagicPages',
  ],
];
