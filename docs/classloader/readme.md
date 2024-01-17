# Autoloading

RockMigrations now includes an autoloading feature that automatically loads all classes and traits in the following folders of your module:

- `classLoader` for traits or custom PHP classes
- `pageClasses` for ProcessWire Custom Pageclasses
- `repeaterPageClasses` for ProcessWire Custom Repeater Pageclasses

This feature simplifies the process of managing and organizing your code. Instead of manually loading each class or trait, RockMigrations does it for you. This can significantly reduce the amount of boilerplate code in your project and make your code cleaner and easier to manage.

This means that you can simply create a class or trait in the appropriate directory, and RockMigrations will take care of the rest. You don't need to worry about manually loading your classes or traits, allowing you to focus on writing your actual code.

This autoloading feature is a powerful tool that can help streamline your development process and improve the organization of your code.

## classLoader

The `classLoader` directory is designed to autoload regular PHP classes or traits of your module. This means that any class or trait you place in this directory will be automatically loaded by RockMigrations, eliminating the need for manual loading.

For instance, consider the following file structure:

```
/site/modules/MyModule
/site/modules/MyModule/classLoader/Foo.php
/site/modules/MyModule/classLoader/Bar.php
```

In this example, `Foo.php` and `Bar.php` are PHP classes or traits located in the `classLoader` directory of the `MyModule` module. When RockMigrations runs, it will automatically load these classes or traits, making them available for use in your module without any additional code.

Please ensure that all assets in that folder use the `MyModule` namespace!

### Example

In RockCommerce we use this technique to load the `Price` class that extend the `WireData` class.

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

The `pageClasses` directory is another autoloading feature of RockMigrations. This directory is designed to autoload custom page classes of your module. This means that any custom page class you place in this directory will be automatically loaded by RockMigrations, eliminating the need for manual loading.

For example, consider the following file structure:

```
/site/modules/MyModule
/site/modules/MyModule/pageClasses/CustomPage.php
```

In this example, `CustomPage.php` is a custom page class located in the `pageClasses` directory of the `MyModule` module. When RockMigrations runs, it will automatically load this custom page class, making it available for use in your module without any additional code.

Please ensure that all assets in that folder use the `MyModule` namespace!

### Example

In RockCommerce we use this technique to load the `Cart` class that extend the `Page` class.

`/site/modules/RockCommerce/pageClasses/Cart.php`

```php
<?php

namespace RockCommerce;

use ProcessWire\Page;
use RockMigrations\MagicPage;

class Cart extends Page
{
  use MagicPage;

  // you must define the "tpl" constant for your pageclass!
  // it has to reference the name of the template used for pages of this type
  const tpl = "rockcommerce_cart";

  // a lot more code here :)
}
```

Note that we use the `MagicPage` trait for our pageclass! This means, for example, that `init()` and `ready()` are automatically triggered so that you can add related hooks directly inside your pageclass. See docs about MagicPages for details.

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
