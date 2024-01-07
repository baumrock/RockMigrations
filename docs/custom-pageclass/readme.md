# Custom Page Classes

## Migrating custom pageclasses in /site/classes

If you have your pageclasses in /site/classes PW will autoload those pageclasses for you. The only thing you have to do is to create the template in your migration:

```php
$rm = rockmigrations();
$rm->createTemplate('your-template');
```

## Migrating custom pageclasses in modules

- Create a folder `/pageClasses` in your module's directory and place your pageclasses there
- Make sure the namespace of your pageclasses is equal to your module's name

### Example

```php
<?php

namespace RockSettings;

use ProcessWire\Page;
use RockMigrations\MagicPage;

use function ProcessWire\rockmigrations;

class SettingsPage extends Page
{
  use MagicPage;

  const tpl = "rocksettings";
  const prefix = "rocksettings_";

  public function migrate()
  {
    $rm = rockmigrations();
    $rm->migrate([
      'templates' => [
        self::tpl => [
          'fields' => [
            'title',
          ],
          'tags' => 'RockSettings',
          'icon' => 'cogs',
        ],
      ],
    ]);
    $rm->createPage(
      template: self::tpl,
      parent: 1,
      name: 'rocksettings',
      title: 'Settings',
      status: ['hidden'],
    );
  }
}
```
