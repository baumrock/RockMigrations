<?php

namespace ProcessWire;

/**
 * Add several fields to settings page (address, phone, social, ...)
 */

$rm = rockmigrations();
$wire = wire();


if (!$rm->getTemplate('settings')) {
  $wire->error("Settings template does not exist");
  return;
}

// create fields
$rm->migrate([
  'fields' => [
    'settings_phone' => [
      'type' => 'text',
      'label' => 'Phone',
      'icon' => 'phone-square',
      'textformatters' => [
        'TextformatterEntities',
      ],
      'tags' => 'Settings',
    ],
    'settings_facebook' => [
      'type' => 'URL',
      'label' => 'Facebook',
      'icon' => 'facebook',
      'textformatters' => [
        'TextformatterEntities',
      ],
      'tags' => 'Settings',
    ],
    'settings_insta' => [
      'type' => 'URL',
      'label' => 'Instagram',
      'icon' => 'instagram',
      'textformatters' => [
        'TextformatterEntities',
      ],
      'tags' => 'Settings',
    ],
    'settings_linkedin' => [
      'type' => 'URL',
      'label' => 'LinkedIn',
      'icon' => 'linkedin-square',
      'textformatters' => [
        'TextformatterEntities',
      ],
      'tags' => 'Settings',
    ],
    'settings_contact' => [
      'type' => 'textarea',
      'inputfieldClass' => 'InputfieldTinyMCE',
      'contentType' => FieldtypeTextarea::contentTypeHTML,
      'label' => 'Contact',
      'rows' => 5,
      'icon' => 'map-pin',
      'inlineMode' => true,
      'settingsFile' => '/site/modules/RockMigrations/TinyMCE/simple.json',
      'tags' => 'Settings',
    ],
    'settings_hours' => [
      'type' => 'textarea',
      'inputfieldClass' => 'InputfieldTinyMCE',
      'contentType' => FieldtypeTextarea::contentTypeHTML,
      'label' => 'Opening Hours',
      'rows' => 5,
      'icon' => 'clock-o',
      'inlineMode' => true,
      'settingsFile' => '/site/modules/RockMigrations/TinyMCE/simple.json',
      'tags' => 'Settings',
    ],
    'settings_footerlinks' => [
      'type' => 'page',
      'label' => 'Footer-Menu',
      'derefAsPage' => FieldtypePage::derefAsPageArray,
      'inputfield' => 'InputfieldPageListSelectMultiple',
      'findPagesSelector' => 'id>0,template!=admin',
      'labelFieldName' => 'title',
      'icon' => 'sitemap',
      'tags' => 'Settings',
    ],
  ],
  'templates' => [
    'settings' => [
      'fields' => [
        'title',

        'settings_phone'        => ['columnWidth' => 50],
        'email' => [
          'columnWidth' => 50,
          'icon' => 'envelope-o',
        ],

        'settings_facebook'     => ['columnWidth' => 33],
        'settings_insta'        => ['columnWidth' => 33],
        'settings_linkedin'     => ['columnWidth' => 33],

        'settings_contact'      => ['columnWidth' => 33],
        'settings_hours'        => ['columnWidth' => 33],
        'settings_footerlinks'  => ['columnWidth' => 33],
      ],
    ],
  ],
]);
