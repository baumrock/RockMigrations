<?php

namespace ProcessWire;

use RockMigrations\MagicPage;

function settings(): SettingsPage
{
  return wire()->pages->get('/settings');
}

class SettingsPage extends Page
{
  use MagicPage;

  const tpl = "settings";
  const prefix = "settings_";

  public function init(): void
  {
    $this->wire('settings', $this->wire->pages->get("/settings"));
  }

  /** magic */
  /** backend */

  public function migrate()
  {
    $rm = $this->rockmigrations();
    $rm->migrate([
      'fields' => [],
      'templates' => [
        self::tpl => [
          'fields' => [
            'title' => [
              'collapsed' => Inputfield::collapsedHidden,
            ],
          ],
          'icon' => 'cogs',
          'noSettings' => true,
          'noChildren' => true,
        ],
      ],
    ]);
    $rm->createPage(
      template: self::tpl,
      parent: 1,
      name: 'settings',
      title: 'Settings',
      status: ['hidden'],
    );
  }
}
