# Config Migrations

Inline migrations are great and provide a lot of flexibility. However, they have a problem: You cannot have circular dependencies. For example when creating a template `my-parent` and a template `my-child` you'll likely want to set allowed children of `my-parent` to `my-child` and allowed parents of `my-child` to `my-parent`.

These kind of circular dependencies can be solved by using config migrations, which are part of the RockMigrations module as of version 5.5.0.

## Using Config Migrations

All you have to do to use config migrations is to create a PHP file in one of the supported directories:

- `site/RockMigrations/[type]/[name].php`
- `site/modules/[module-name]/RockMigrations/[type]/[name].php`

Where `[type]` is one of `fields`, `templates`, `roles`, `permissions` and `[name]` is the name of the field, template, role or permission.

## Example Migration Files

### Field

```php
// site/RockMigrations/fields/my-field.php
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

If you create a field or a template from within a module migrations folder (eg `/site/modules/MyModule/RockMigrations/fields/my-field.php`), RockMigrations will automatically tag it with the module name. This way you can easily find out from which module a field or template originates and you get a cleaner field and template management screen:

<img src=https://i.imgur.com/0RuqnjH.png height=300 class=blur>

### Using Intellisense

I decided to use PHP files rather than YAML or JSON, because in PHP files we get full intellisense support to make development faster and maintenance easier:

<img src=https://i.imgur.com/mHRVJX4.png class=blur height=300>

### Class Constants Helper

As you might have noticed I don't like to type long field or template names as strings like `rockcommerce_product_customfields`. Instead I use class constants, which have the benefit that I can't make typos and I get autocompletion again:

<img src=https://i.imgur.com/iFX7xcW.png class=blur height=200>

All objects that you create via config migrations will automatically be available via the following helper classes:

*For files in /site/RockMigrations/*

- `MyFields` for fields
- `MyTemplates` for templates
- `MyRoles` for roles
- `MyPermissions` for permissions

*For files in /site/modules/MyModule/RockMigrations/*

- `MyModuleFields` for fields
- `MyModuleTemplates` for templates
- `MyModuleRoles` for roles
- `MyModulePermissions` for permissions

Or a real world example from the RockCommerce module:

- `RockCommerceFields` for fields
- `RockCommerceTemplates` for templates
- `RockCommerceRoles` for roles
- `RockCommercePermissions` for permissions

<img src=https://i.imgur.com/8cVDpVx.png class=blur height=150>