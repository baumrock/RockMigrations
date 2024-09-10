# Autoloading

RockMigrations includes an autoloading feature that automatically loads all classes and traits in the following folders of your module:

- `classLoader` for traits or custom PHP classes
- `pageClasses` for ProcessWire Custom Pageclasses
- `repeaterPageClasses` for ProcessWire Custom Repeater Pageclasses

This feature simplifies the process of managing and organizing your code. Instead of manually loading each class or trait, RockMigrations does it for you. This can significantly reduce the amount of boilerplate code in your project and make your code easier to manage.

## classLoader

The `classLoader` directory is designed to autoload regular PHP classes or traits of your module.

For instance, consider the following file structure:

```
/site/modules/MyModule
/site/modules/MyModule/classLoader/Foo.php
/site/modules/MyModule/classLoader/Bar.php
```

In this example, `Foo.php` and `Bar.php` will automatically be attached to the PW classloader, which means that Foo and Bar class will be ready to use from within your module without the need for additional require_once or similar commands.

NOTE: All classes in the `classLoader` folder must use the fully qualified namespace of your module. In this case Foo and Bar would have to be namespaced like this:

```php
<?php

namespace MyModule;

class Foo extends WhatEver {
  // ...
}
```

### Example

In RockCommerce we use this technique to load the `Price` class that extends the `WireData` class.

`/site/modules/RockCommerce/classLoader/Price.php`

```php
<?php

namespace RockCommerce;

use ProcessWire\WireData;

class Price extends WireData
{
  public $net;
  public $vat;
  public $gross;
  public $taxrate;

  // more code here
}
```

## pageClasses

As soon as your module creates pages you might want that these pages have a dedicated class. ProcessWire has the concept of custom pageclasses, which is great, but by default those classes will live in /site/classes. To load classes from another folder a lot of things have to happen. That's why RockMigrations simplifies this by automatically loading classes from the `pageClasses` directory.

### Setup

- Create a folder "pageClasses" in your module.
- Add all your pageclasses to that folder.
- Add `$rm->watch($this);` to your module's `init()` method to watch your module for changes.
- Add `$rm->migrateModule($this);` to your module's `___install()` method to make sure it will install properly.

### Folder Structure

For instance, consider the following file structure:

```
/site/modules/MyModule
/site/modules/MyModule/pageClasses/Foo.php
/site/modules/MyModule/pageClasses/Bar.php
```

In this example, `Foo.php` and `Bar.php` are custom page classes that will be autoloaded by RockMigrations. Again, these classes must use the fully qualified namespace of your module (which is `MyModule` in this case).

### Loading Order

RockMigrations will load all classes in the order they are stored on the file system. This means that in this example we load Bar before Foo! Now what if Bar depends on Foo?

RockMigrations will take care of that! It will first create all templates, then migrate the module (aka trigger $yourmodule->migrate()) and then it will migrate all pageclasses of your module.

This means:

- You can safely reference all class properties of Foo in Bar and all class properties of Bar in Foo!
- You can create "global" fields that are used both in Foo and Bar in your module's migrate() method, as this one will be triggered BEFORE all pageclasses are migrated.

### Example

See this log of the RockCounter module:

```
### Migrate items with priority #99 ###
Watchfile: /site/modules/Site/Site.module.php
  ----- Migrate Module Site -----
  Migrate Site
  Install module RockCounter
    Refresh modules
    ----- Migrate Module RockCounter -----
    Create template rockcounter_hit
    Create template rockcounter_hits
    Create template rockcounter_root
    Create template rockcounter_user
    Create template rockcounter_users
    Migrate RockCounter
    Migrate PageClass \RockCounter\Hit
    Migrate PageClass \RockCounter\Hits
    Migrate PageClass \RockCounter\Root
    Migrate PageClass \RockCounter\User
    Migrate PageClass \RockCounter\Users
```

This is what happens:

- The migrations are triggered by the "Site" module. There we have a `$rm->installModule('RockCounter')` statement, which triggers the installation of that module.
- Before RockCounter is installed RockMigrations will trigger a modules refresh.
- All templates of RockCounter will be created.
- RockCounter will be migrated ($rockcounter->migrate()).
- All pageclasses of RockCounter will be migrated.

This ensures that for example in the "root" PageClass we can set the childTemplates of the "root" template to Users and Hits:

```php
<?php

namespace RockCounter;

use ProcessWire\Page;

use function ProcessWire\rockmigrations;

class Root extends Page
{
  const tpl = 'rockcounter_root';
  const prefix = 'rockcounter_root_';

  public function migrate(): void
  {
    $rm = rockmigrations();
    $rm->migrate([
      'fields' => [],
      'templates' => [
        self::tpl => [
          'childTemplates' => [
            Users::tpl,
            Hits::tpl,
          ],
        ],
      ],
    ]);
  }
}
```

Even though the "Users" class is loaded after the "Root" class this code will work, because all templates have already been created upfront!

### Installation

If you module is not installed yet, the module will not be part of the watchlist and the pageclass templates will not be created. To make sure that all pageclass templates are created when the module is installed, you need to add `$rm->migrateModule($this);` to your module's `___install()` method:

```php
  public function ___install(): void
  {
    $rm = rockmigrations();
    $rm->migrateModule($this);
  }
```

## repeaterPageClasses

The `repeaterPageClasses` directory is another autoloading feature of RockMigrations. This directory is designed to autoload custom repeater page classes of your module. This means that any custom repeater page class you place in this directory will be automatically loaded by RockMigrations, eliminating the need for manual loading.

For example, consider the following file structure:

```
/site/modules/MyModule
/site/modules/MyModule/repeaterPageClasses/CustomRepeaterPage.php
```

In this example, `CustomRepeaterPage.php` is a custom repeater page class located in the `repeaterPageClasses` directory of the `MyModule` module. When RockMigrations runs, it will automatically load this custom repeater page class, making it available for use in your module without any additional code.

Please ensure that all assets in that folder use the `MyModule` namespace!

### Example

In RockCommerce we use this technique to load the `Accessory` class that extends the `RepeaterPage` class.

`/site/modules/RockCommerce/repeaterPageClasses/Accessory.php`

```php
<?php

namespace RockCommerce;

use ProcessWire\RepeaterPage;

class Accessory extends RepeaterPage
{
  // you must define the "field" constant for your repeater page class!
  // it has to reference the name of the field used for repeater items of this type
  const field = "rc_product_accessories";

  // a lot more code here :)
}
```

Note that we do NOT use the MagicPage trait here as RepeaterPages are somewhat special. But RockMigrations will still trigger `init()` and `ready()` of that class so you can keep your code clean! Other MagicMethods are not available.

Pro-Tip: Instead of using strings, I find it a lot better to use class constants. Here's how I would do it:

```php
const field = ProductPage::field_accessories;
```

Since the ProductPage is auto-loaded by RockMigrations as well, the field name will be readily available without the need for additional require_once or similar commands.
