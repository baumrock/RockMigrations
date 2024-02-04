# Custom Page Classes

## Migrating custom pageclasses in /site/classes

If you have your pageclasses in /site/classes PW will autoload those pageclasses for you. The only thing you have to do is to create the template in your migration:

```php
$rm = rockmigrations();
$rm->createTemplate('your-template');
```

## Migrating custom pageclasses in modules

RockMigrations can also auto-load pageclasses from within your module's folder structure. Please see the <a href=../classloader>docs about the class autoloading</a> for details.

### Example

```
/site/modules/RockSettings/pageClasses/SettingsPage.php
```

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
