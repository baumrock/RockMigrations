<img src=rockmigrations.svg height=100>

<br>

See the video here:

<a href="https://www.youtube.com/watch?v=eBOB8dZvRN4"><img src=thumb.png></a><br>
<a href="https://www.youtube.com/watch?v=o6O859d3cFA"><img src=thumb2.webp></a>

<br>

### Module Description

RockMigrations has an easy API to do all the things you can do in the PW backend via code. This means you can **fully version control your site** or app simply by adding all the necessary fields and templates not via clicking but via writing simple scripts that do that tasks for you.

The module also contains several helpers that make it extremely easy to implement **fully automated CI/CD pipelines**.

# Wiki

Check out the [WIKI for a Quickstart and Docs](https://github.com/baumrock/RockMigrations/wiki)!

## Limitations

RockMigrations might not support all external fields, especially not ProFields like RepeaterMatrix. Adding support has no priority for me because I'm not using them. If you need support for any field that is currently not supported please provide a PR or if you are interested in sponsoring that feature please contact me via PM in the forum.

But not to forget: You can still use the regular PW API to create fields and manipulate all kinds of things the way you would do it if RockMigrations did not exist. It might just not be as convenient as when using the RockMigrations API.

## Where do I find out all those field and template properties?

1. You can edit your field or template and copy the code from there (I recommend to only copy the settings you need to make your migration files more readable):
   ![img](https://i.imgur.com/IAHV3VZ.png)

2. Hover the caret on the very right of the field of the setting you want to set:
   ![img](https://i.imgur.com/hmydzf5.png)

## Magic

RockMigrations does not only help you with your migrations and deployments but it also adds a lot of helpers that make developing with ProcessWire even more fun.

See WIKI for MagicPages!

### Snippets

Another option that helps you get started with migration syntax is using the shipped VSCode snippets. I highly recommend enabling the `syncSnippets` option in your config:

```php
// site/config.php
$config->rockmigrations = [
  "syncSnippets" => true,
];
```

## Watching files, paths or modules

RockMigrations can watch files, paths and modules for changes. It will detect changes on any of the files on the watchlist and trigger migrations to run if anything changed.

As from version 1.0.0 (29.8.2022) RockMigrations will not run all migrations if one file changes but will only migrate this single changed file. This makes the migrations run a lot faster!

When run from the CLI it will still run every single migration file to make sure that everything works as expected and no change is missed.

Sometimes it is necessary that even unchanged files are migrated. RockMatrix is an example for that, where the module file triggers the migrations for all Matrix-Blocks. In that case you can add the file to the watchlist using the `force` option:

```php
// inside RockMatrix::init
$rm->watch($this, true, ['force'=>true]);
```

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

Sometimes you want to work on a file and you want it to be watched for changes, but you don't want to trigger the migrations all the time. For example when working on markup or LESS. In that case you can disable automatic running of migrations:

```php
$config->noMigrate = true;
```

This prevents running migrations but files will still be watched for changes and you will still be able to trigger migrations from the CLI.

## Files On Demand

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

RockMigrations automatically watches `/site/migrate.php` and files like `YourModule.migrate.php`.

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

## Working with fieldsets

Working with fieldsets is a pain because they need to have an opening and a closing field. That makes it complicated to work with it from a migrations perspective, but RockMigrations has you covered with a nice little helper method that can wrap other fields at runtime:

```php
// syntax
$rm->wrapFields($form, $fields, $fieldset);

// usage
$wire->addHookAfter("ProcessPageEdit::buildForm", function($event) {
  $form = $event->return;

  /** @var RockMigrations $rm */
  $rm = $this->wire->modules->get('RockMigrations');
  $rm->wrapFields($form, [
    'title' => [
      // runtime settings for title field
      'columnWidth' => 50,
    ],
    // runtime field example
    [
      'type' => 'markup',
      'label' => 'foo',
      'value' => 'bar',
      'columnWidth' => 50,
    ],
    'other_field_of_this_template',
  ], [
    'label' => 'I am a new fieldset wrapper',
  ]);
})
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

Options field with multilang labels:

```php
$rm->createField('demo_field', 'options', [
  'label' => 'Test Field',
  'label1020' => 'Test Feld',
  'type' => 'options',
  'optionsLang' => [
    'default' => [
      1 => 'VERYLOW|Very Low',
      2 => 'LOW|Low',
      3 => 'MIDDLE|Middle',
      4 => 'HIGH|High',
      5 => 'VERYHIGH|Very High',
    ],
    'de' => [
      1 => 'VERYLOW|Sehr niedrig',
      2 => 'LOW|Niedrig',
      3 => 'MIDDLE|Mittel',
      4 => 'HIGH|Hoch',
      5 => 'VERYHIGH|Sehr hoch',
    ],
  ],
]);
```

Note that RockMigrations uses a slightly different syntax than when populating the options via GUI. RockMigrations makes sure that all options use the values of the default language and only set the label (title) of the options.

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
