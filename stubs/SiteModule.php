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
  }
}
