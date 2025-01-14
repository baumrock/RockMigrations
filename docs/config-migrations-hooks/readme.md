# Config Migration Hooks

RockMigrations executes migrations in a specific order and provides hooks that allow you to run custom code at different stages of the migration process. This is particularly useful for handling dependencies and circular references.

## Available Hooks

The migration process follows this sequence:

1. `beforeAssets`
   - Executed before any assets (fields, templates, etc.) are created
   - Use this hook if you need to prepare anything before asset creation

2. `afterAssets`
   - Executed after all assets have been created but before any data migrations
   - Perfect for creating pages that depend on templates that were just created
   - At this stage, templates and fields exist but don't have their settings applied yet

3. `beforeData`
   - Executed before data migrations start
   - Useful for preparing data structures that your migrations might depend on

4. `afterData`
   - Executed after all data migrations are complete
   - Use this for cleanup tasks or final adjustments

## How to Use Hooks

To use a migration hook, create a PHP file in your `RockMigrations` directory (either `/site/RockMigrations` or `/site/modules/[module-name]/RockMigrations`) with the corresponding name:

```php
site/RockMigrations/beforeAssets.php
site/RockMigrations/afterAssets.php
site/RockMigrations/beforeData.php
site/RockMigrations/afterData.php
```

## Example Use Case

A common scenario where hooks are valuable is when dealing with circular dependencies. For example:

1. You need to create a page reference field
2. This field needs to reference a specific parent page
3. The parent page uses a template that is being created in the same migration

Solution using hooks:

1. Create the template in your regular migration file
2. Use the `afterAssets` hook to create the parent page (templates exist but settings aren't applied yet)
3. Create the page reference field in your regular migration file

```php
<?php

namespace ProcessWire;

$rm = rockmigrations();
$rm->createPage(
   template: RockMigrationsConstants::template_foo,
   parent: 1,
   name: 'my-pagename',
   title: 'My Page Title',
);
```

This structured approach ensures that dependencies are handled correctly and circular references can be resolved effectively.

## Example Log

When running config migrations, you'll see a detailed output of all the steps that are being executed. This includes the creation of constant traits, running of hooks, and the processing of your migration files. Here's an example of what the output looks like:

```
### Running Config Migrations ###
--- create PHP constant traits ---
Created /var/www/html/site/RockMigrationsConstants.php
--- config migration hook: beforeAssets (0 files) ---
--- first run: create assets ---
/site/RockMigrations/fields/foo.php
  Name: foo
  Tag:
/site/RockMigrations/templates/bar.php
  Name: bar
  Tag:
--- config migration hook: afterAssets (0 files) ---
--- config migration hook: beforeData (1 files) ---
/site/RockMigrations/beforeData.php
--- second run: migrate data ---
/site/RockMigrations/fields/foo.php
/site/RockMigrations/templates/bar.php
--- config migration hook: afterData (0 files) ---
```

## Dependencies

Another example where hooks are necessary are module dependencies. For example, in RockInvoice we need to install the Fieldtype and Inputfield modules before we can create fields of that type:

`label: beforeAssets.php`
```php
<?php

namespace ProcessWire;

$rm = rockmigrations();

// install fieldtype + inputfield modules
$rm->configMigrations(false);
wire()->modules->install('FieldtypeRockInvoiceitems');
wire()->modules->install('InputfieldRockInvoiceitems');
$rm->configMigrations(true);
```

Please note that it is important to disable config migrations temporarily before installing the modules. This is to prevent an endless loop when installing a module that depends on the modules you're installing.
