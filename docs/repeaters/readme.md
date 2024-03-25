# Migrating Repeaters

Utilizing repeaters with RockMigrations is straightforward. By employing the `rmf-repeater` snippet, you can achieve the following configuration:

```php
'your_field' => [
  'label' => '',
  'type' => 'FieldtypeRepeater',
  'fields' => [
    'title',
    'foo',
    'bar',
  ],
  'repeaterTitle' => '#n: {title}',
  'familyFriendly' => 1,
  'repeaterDepth' => 0,
  'tags' => '',
  'repeaterAddLabel' => 'Add New Item',
  'columnWidth' => 100,
],
```

In essence, that's all there is to it. However, it's crucial to ensure that all fields listed in the 'fields' array are pre-existing before incorporating them into the repeater. Thus, within a single migration call, the process would resemble the following:

```php
$rm->migrate([
  'fields' => [
    // first, make sure that all fields exist
    'foo' => [
      'type' => 'text',
      'label' => 'Foo field',
      'icon' => 'align-left',
      'textformatters' => [
        'TextformatterEntities',
      ],
    ],
    'bar' => [
      'type' => 'text',
      'label' => 'Bar field',
      'icon' => 'align-left',
      'textformatters' => [
        'TextformatterEntities',
      ],
    ],

    // then create the repeater field
    'your_repeater_field' => [
      'label' => 'My repeater field',
      'type' => 'FieldtypeRepeater',
      'fields' => [
        'title',
        'foo',
        'bar',
      ],
      'repeaterTitle' => '#n: {title}',
      'familyFriendly' => 1,
      'repeaterDepth' => 0,
      'tags' => '',
      'repeaterAddLabel' => 'Add New Item',
      'columnWidth' => 100,
    ],
  ],
  'templates' => [
    // finally add the repeater to your template
    'your_template' => [
      'fields' => [
        'title',
        'your_repeater',
      ],
    ],
  ],
]);
```

Here's a helpful tip: You have the ability to directly configure the context of fields. This means you can, for instance, arrange three fields to display side-by-side for a more streamlined layout:

```php
'your_repeater_field' => [
  ...
  'fields' => [
    'title' => ['columnWidth' => 33],
    'foo'   => ['columnWidth' => 33],
    'bar'   => ['columnWidth' => 33],
  ],
  ...
],
```
