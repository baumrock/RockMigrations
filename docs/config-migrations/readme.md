# Config Migrations

Inline migrations are great and provide a lot of flexibility. However, they have a problem: You cannot have circular dependencies. For example when creating a template `my-parent` and a template `my-child` you'll likely want to set allowed children of `my-parent` to `my-child` and allowed parents of `my-child` to `my-parent`.

These kind of circular dependencies can be solved by using config migrations, which are part of the RockMigrations module as of version 6.0.0;

## Using Config Migrations

All you have to do to use config migrations is to create a PHP file in one of the supported directories:

- `site/RockMigrations/[type]/[name].php`
- `site/modules/[module-name]/RockMigrations/[type]/[name].php`

Where `[type]` is one of `fields`, `templates`, `roles`, `permissions` and `[name]` is the name of the field, template, role or permission.

## Class Constant Helpers

RockMigrations can create class constant traits/classes for you that make working with your assets a breeze. This feature is NOT enabled by default, so be sure to check out the instructions about them at the end of this page!

## Example Migration Files

### Field

```php
// site/RockMigrations/fields/myfield.php
return [
  'label' => 'My Field Label',
  'type' => 'textarea*Language',
];
```

Please note the type `textarea*Language`. This is a magic property that tells RockMigrations to use type `textarea` if language support is not enabled and `textareaLanguage` otherwise. You can use any type that RockMigrations supports, like `text`, `textarea`, `checkbox`, etc.;

### Template

```php
// site/RockMigrations/templates/my-template.php
// return template data
return [
  'fields' => [
    'title',
    'foo',
    'bar',
  ],
  'childTemplates' => [
    'my-child-template',
  ],
  'noSettings' => true,
];
```

### Role

```php
// site/RockMigrations/roles/my-role.php
// return permissions of this role
return [
  'page-edit',
  'page-delete',
];
```

### Permission

```php
// site/RockMigrations/permissions/my-permission.php
// return permission description as string
return 'My Permission Description';
```

## Benefits

Using config migrations has several benefits, the most important being to avoid circular dependencies and to have a single source of truth for your data. Other benefits include:

### Faster migrations while developing

RockMigrations will only migrate changed files during development. That means if you work on a complex pageClass with 100 fields and change one field inside that file, RockMigrations will have to run all migrations for all fields.

When using config migrations, you will have one file for each field, template, role or permission. That means if you change one of them, only that file will be migrated.

### Automatic Tagging

If you create a field or a template from within a module migrations folder (eg `/site/modules/MyModule/RockMigrations/fields/myfield.php`), RockMigrations will automatically tag it with the module name. This way you can easily find out from which module a field or template originates and you get a cleaner field and template management screen:

<img src=https://i.imgur.com/0RuqnjH.png height=300 class=blur>

### Using Intellisense

I decided to use PHP files rather than YAML or JSON, because in PHP files we get full intellisense support to make development faster and maintenance easier:

<img src=https://i.imgur.com/mHRVJX4.png class=blur height=300>

## Class Constant Traits/Classes

<div class='uk-alert'>This feature requires at least PHP8.2 to work.</div>

As you might have noticed I don't like to type long field or template names as strings like `rockcommerce_mylongfieldname`. Instead I use class constants, which have two benefits: First, I can't make typos and second, I get autocompletion again.

The idea is that all fields that are created from within a module are available as a class constant from that module. The easiest way to explain this is by example.

Let's say we have a module called `MyModule` and we create a field called `myfield` in it. This field's name would be `mymodule_myfield`, which is a bit verbose, right? Typos are inevitable.

Wouldn't it be great if our IDE knew about all the fields and templates created by RockMigrations so that we get suggestions while typing?

**That's what this feature is about!**

Instead of writing the string `mymodule_myfield` we can enable constant helpers and then type something like this: `MyModule::field_myfield`.

The result will look like this:

<img src=https://i.imgur.com/Vqnq50U.png class=blur alt='Constant Helpers'>

### Enabling Constant Helpers

To enable constant helpers you need to manually create the constants file, so that RockMigrations knows that it should populate that file.

### Case 1: Site-wide migrations

For site-wide assets all you have to do is to create the following file:

`/site/RockMigrationsConstants.php`

Once you created that file and you did a modules refresh, the file should look something like this (with all the fields and templates created via config migrations):

```php
<?php

namespace ProcessWire;

// DO NOT MODIFY THIS FILE!
// IT IS AUTO-GENERATED BY ROCKMIGRATIONS!

class RockMigrationsConstants
{
  const field_from = 'from';
  const field_hours = 'hours';
  const field_notes = 'notes';
  const field_to = 'to';
  const template_effort = 'effort';
  const template_efforts = 'efforts';
  const template_todo = 'todo';
  const template_todos = 'todos';
}
```

In your codebase you can then refer to all assets via the `RockMigrationsConstants` class!

### Case 2: Module-specific migrations

Module specific migrations need an additional step, because we want the constants be accessible via the module itself like so:

```php
MyModule::field_myfield
```

- Create the file `site/modules/MyModule/RockMigrations/RockMigrationsConstants.php`
- Do a modules refresh

RockMigrations should now have created a trait file like this in `/site/modules/MyModule/RockMigrations/RockMigrationsConstants.php`:

```php
<?php

namespace MyModule;

// DO NOT MODIFY THIS FILE!
// IT IS AUTO-GENERATED BY ROCKMIGRATIONS!

trait RockMigrationsConstants
{
  const field_myfield = 'mymodule_myfield';
}
```

This file will be auto-generated by RockMigrations and should not be modified. Whenever you add a new config migration this file will be updated automatically.

Finally we have to tell our module to use this trait to expose our new constants to our codebase. For that we simply add a `require` statement and a `use` statement to our module file `MyModule.module.php`:

```php
require_once __DIR__ . '/RockMigrationsConstants.php';
class MyModule extends WireData implements Module, ConfigurableModule
{
  use \MyModule\RockMigrationsConstants;
  // rest of the class
}
```

Congrats! You should now be able to access your assets via `MyModule::...` syntax!
