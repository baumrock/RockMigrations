# Migating Repeaters

write nice: using repeaters with RockMigrations is quite simple. using the `rmf-repeater` snippet you'll get this:

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

write nice: thats basically it. the only thing you need to take care of is that all fields defined in the fields array need to exist before you add them to the repeater. So in one migrate call it would be something like this:

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
