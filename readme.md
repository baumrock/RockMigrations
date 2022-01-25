# RockMigrations

This will be the new default migrations module. It can be used together with RockMigrations1 (RM1) for some time, but the support for using RM + RM1 will be dropped in v1.0.0 of RockMigrations!

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

## Field Migration Examples

### Ckeditor Field

```php
'mytextfield' => [
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
```
