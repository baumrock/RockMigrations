<?php

namespace ProcessWire;

// expose the site module as global site() function
function site(): Site
{
  return wire()->modules->get('Site');
}

// module code
class Site extends WireData implements Module
{

  public static function getModuleInfo()
  {
    return [
      'title' => 'Site',
      'version' => '0.0.1',
      'summary' => 'Site Module',
      'autoload' => true,
      'singular' => true,
      'icon' => 'bolt',
      'requires' => [
        'RockMigrations>=3.34',
      ],
    ];
  }

  public function init()
  {
    $this->wire('site', $this);
    /** @var RockMigrations $rm */
    $rm = $this->wire->modules->get('RockMigrations');

    // migrate site module before other modules so that if we create global
    // fields we make sure we can use them in other modules
    $rm->watch($this, 99);
  }

  public function migrate()
  {
    $rm = rockmigrations();
  }
}
