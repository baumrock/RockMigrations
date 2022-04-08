# RockMigrations

RockMigrations has an easy API to do all the things you can do in the PW backend via code. This means you can fully version control your site or app simply by adding all the necessary fields and templates not via clicking but via writing simple scripts that do that tasks for you.

## QuickStart

The example code uses `bd()` calls for dumping data. You need TracyDebugger installed!

Put this in your `site/ready.php`

```php
/** @var RockMigrations $rm */
$rm = $modules->get("RockMigrations");
bd('Create field + template via RM');
$rm->createField('demo', 'text', [
  'label' => 'My demo field',
  'tags' => 'RMDemo',
]);
$rm->createTemplate('demo', [
  'fields' => [
    'title',
    'demo',
  ],
  'tags' => 'RMDemo',
]);
```

Reload your site and you will see the new field and template in the backend and you'll see the message in the tracy debug bar.

Now refresh the page and note that the migration run again (the same message appears in tracy). There are two important things to understand here:

1) Migrations can run multiple times and will always lead to the same result.
2) If you put your migrations in ready.php or an autoload module they will run on every request. This not a good idea as it may slow down your site significantly

So what can we do about this? RockMigrations has a concept of watching files and only running migrations once a file has changed or Modules::refresh was triggered.

## Watching files, paths or modules

RockMigrations can watch files, paths and modules for changes. It will detect changes on any of the files on the watchlist and trigger all migrations to run if anything changed.

Let's wrap the example from above into a watch callback:

```php
bd('ready.php');
/** @var RockMigrations $rm */
$rm = $modules->get("RockMigrations");
$rm->watch(function($rm) {
  bd('Create field + template via RM');
  $rm->createField('demo', 'text', [
    'label' => 'My demo field',
    'tags' => 'RMDemo',
  ]);
  $rm->createTemplate('demo', [
    'fields' => [
      'title',
      'demo',
    ],
    'tags' => 'RMDemo',
  ]);
})
```

Reload the page and you'll see the message in the debug bar. Reload it again and only "ready.php" will show up, but not "Create field + template via RM". Now modify ready.php (eg by adding some empty lines at the bottom), save the file and reload your backend. The migrations will be triggered!

### Watch priority

Similar to the priority of hooks you can define a priority for execution of the migrations. This can sometimes be necessary if one migration depends on another. See this example:

```php
bd('ready.php');
/** @var RockMigrations $rm */
$rm = $modules->get('RockMigrations');
$rm->watch(function($rm) {
  bd('migrate one');
}, 1.1);
$rm->watch(function($rm) {
  bd('migrate two');
}, 1.2);
$rm->watch(function($rm) {
  bd('migrate three');
}, 1.3);
```

The output will be:

```txt
ready.php
migrate three
migrate two
migrate one
```

The higher the priority you define as second parameter the earlier it will be triggered. The default value is TRUE which is converted to `1.00`. You can define any float value (eg `1.0001` would also be valid).

### Watching modules

You can easily watch any ProcessWire module for changes and trigger the `migrate()` method whenever the file is changed:

```php
// module needs to be autoload!
public function init() {
  $rm = $this->wire->modules->get('RockMigrations');
  if($rm) $rm->watch($this);
}
public function migrate() {
  bd('Migrating MyModule...');
}
```

### Watching files

You can watch single files or entire paths:

```php
$rm->watch(__FILE__, false);
$rm->watch(__DIR__."/foo");
```

Note that you need to define `FALSE` as second parameter if the file should not be migrated but only watched for changes. If you set it to `TRUE` the file will be included and executed as if it was a migration script (see examples below).

## Running migrations

RockMigrations will run migrations automatically when a watched file was changed. In case you want to trigger the migrations manually (eg after deployment) you can use the `migrate.php` file:

```php
php site/modules/RockMigrations/migrate.php
```

## File On Demand

You can instruct RockMigrations to download files on demand from a remote server. This makes it possible to create content on the remote system (eg on the live server), pull data from the database to your local machine and as soon as you open a page RockMigrations will fetch the missing files from your remote server.

```php
// without authentication
$config->filesOnDemand = 'https://example.com';

// with http basic authentication
$config->filesOnDemand = 'https://user:password@example.com';
```

#### YAML

```php
$rm->watch("/your/file.yaml");
```
```yaml
fields:
  foo:
    type: text
    label: My foo field
```

#### PHP

```php
$rm->watch("/your/file.php");
```
```php
<?php namespace ProcessWire;
$rm->createField('foo', 'text');
```

### Auto-Watch

RockMigrations automatically watches files like `YourModule.migrate.php`.

## Working with YAML files

RockMigrations ships with the Spyc library to read/write YAML files:

```php
// get YAML instance
$rm->yaml();

// get array from YAML file
$rm->yaml('/path/to/file.yaml');

// save data to file
$rm->yaml('/path/to/file.yaml', ['foo'=>'bar']);
```

## Migration Examples

### Field migrations

CKEditor field

```php
$rm->migrate([
  'fields' => [
    'yourckefield' => [
      'type' => 'textarea',
      'tags' => 'MyTags',
      'inputfieldClass' => 'InputfieldCKEditor',
      'contentType' => FieldtypeTextarea::contentTypeHTML,
      'rows' => 5,
      'formatTags' => "h2;p;",
      'contentsCss' => "/site/templates/main.css?m=".time(),
      'stylesSet' => "mystyles:/site/templates/mystyles.js",
      'toggles' => [
        InputfieldCKEditor::toggleCleanDIV, // convert <div> to <p>
        InputfieldCKEditor::toggleCleanP, // remove empty paragraphs
        InputfieldCKEditor::toggleCleanNBSP, // remove &nbsp;
      ],
    ],
  ],
]);
```

Image field

```php
$rm->migrate([
  'fields' => [
    'yourimagefield' => [
      'type' => 'image',
      'tags' => 'YourTags',
      'maxFiles' => 0,
      'descriptionRows' => 1,
      'extensions' => "jpg jpeg gif png svg",
      'okExtensions' => ['svg'],
      'icon' => 'picture-o',
      'outputFormat' => FieldtypeFile::outputFormatSingle,
      'maxSize' => 3, // max 3 megapixels
    ],
  ],
]);
```

Files field

```php
$rm->migrate([
  'fields' => [
    'yourfilefield' => [
      'type' => 'file',
      'tags' => 'YourTags',
      'maxFiles' => 1,
      'descriptionRows' => 0,
      'extensions' => "pdf",
      'icon' => 'file-o',
      'outputFormat' => FieldtypeFile::outputFormatSingle,
    ],
  ],
]);
```

Options field

```php
$rm->migrate([
  'fields' => [
    'yourfield' => [
      'type' => 'options',
      'tags' => 'YourTags',
      'label' => 'Options example',
      'options' => [
        1 => 'ONE|This is option one',
        2 => 'TWO',
        3 => 'THREE',
      ],
    ],
  ],
]);
```

Page Reference field

```php
$rm->migrate([
  'fields' => [
    'yourfield' => [
      'type' => 'page',
      'label' => __('Select a page'),
      'tags' => 'YourModule',
      'derefAsPage' => FieldtypePage::derefAsPageArray,
      'inputfield' => 'InputfieldSelect',
      'findPagesSelector' => 'foo=bar',
      'labelFieldName' => 'title',
    ],
  ],
]);
```

Date field

```php
$rm->migrate([
  'fields' => [
    'yourfield' => [
      'type' => 'datetime',
      'label' => __('Enter date'),
      'tags' => 'YourModule',
      'dateInputFormat' => 'j.n.y',
      'datepicker' => InputfieldDatetime::datepickerFocus,
      'defaultToday' => 1,
    ],
  ],
]);
```
