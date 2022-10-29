<?php

namespace RockMigrationsApi;

use ProcessWire\Field;
use ProcessWire\FieldtypeFieldsetOpen;
use ProcessWire\FieldtypeRepeater;
use ProcessWire\RockMigrations;
use ProcessWire\WireException;

class Fields extends RockMigrations
{

  /**
   * Create a field of the given type
   *
   * If run multiple times it will only update field data.
   *
   * Usage:
   * $rm->createField('myfield');
   *
   * $rm->createField('myfield', 'text', [
   *   'label' => 'My great field',
   * ]);
   *
   * Alternate array syntax:
   * $rm->createField('myfield', [
   *   'type' => 'text',
   *   'label' => 'My field label',
   * ]);
   *
   * @param string $name
   * @param string|array $type|$options
   * @param array $options
   * @return Field|false
   */
  public function createField($name, $type = 'text', $options = [])
  {
    if (is_array($type)) {
      $options = $type;
      if (!array_key_exists('type', $options)) $options['type'] = 'text';
      $type = $options['type'];
    }
    $field = $this->getField($name, true);

    // field does not exist
    if (!$field) {
      // get type
      $type = $this->getFieldtype($type);
      if (!$type) return; // logging above

      // create the new field
      $_name = $this->wire->sanitizer->fieldName($name);
      if ($_name !== $name) throw new WireException("Invalid fieldname ($name)!");
      $field = $this->wire(new Field());
      $field->type = $type;
      $field->name = $_name;
      $field->label = $_name; // set label (mandatory since ~3.0.172)
      $field->save();

      // create end field for fieldsets
      if ($field->type instanceof FieldtypeFieldsetOpen) {
        $field->type->getFieldsetCloseField($field, true);
      }

      // this will auto-generate the repeater template
      if ($field->type instanceof FieldtypeRepeater) {
        $field->type->getRepeaterTemplate($field);
      }
    }

    // set options
    $options = array_merge($options, ['type' => $type]);
    $field = $this->setFieldData($field, $options);

    return $field;
  }

  /**
   * Create fields from array
   *
   * Usage:
   * $rm->createFields([
   *   'field1' => [...],
   *   'field2' => [...],
   * ]);
   */
  public function createFields($fields): void
  {
    foreach ($fields as $name => $data) {
      if (is_int($name)) {
        $name = $data;
        $data = [];
      }
      $this->createField($name, $data);
    }
  }

  /**
   * Delete the given field
   * @param mixed $name
   * @param bool $quiet
   * @return void
   */
  public function deleteField($name, $quiet = false)
  {
    $field = $this->getField($name, $quiet);
    if (!$field) return; // logging in getField()

    // delete _END field for fieldsets first
    if ($field->type instanceof FieldtypeFieldsetOpen) {
      $closer = $field->type->getFieldsetCloseField($field, false);
      $this->deleteField($closer, $quiet);
    }

    // make sure we can delete the field by removing all flags
    $field->flags = Field::flagSystemOverride;
    $field->flags = 0;

    // remove the field from all fieldgroups
    foreach ($this->fieldgroups as $fieldgroup) {
      /** @var Fieldgroup $fieldgroup */
      $fieldgroup->remove($field);
      $fieldgroup->save();
    }

    return $this->fields->delete($field);
  }
}
