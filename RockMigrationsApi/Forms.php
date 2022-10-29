<?php

namespace RockMigrationsApi;

use ProcessWire\Field;
use ProcessWire\Inputfield;
use ProcessWire\InputfieldWrapper;
use ProcessWire\RockMigrations;
use ProcessWire\RockMigrationsApiTrait;
use ProcessWire\WireException;

class Forms extends RockMigrations
{
  use RockMigrationsApiTrait;

  /**
   * Add a runtime field to an inputfield wrapper
   *
   * Usage:
   * $rm->addAfter($form, 'title', [
   *   'type' => 'markup',
   *   'label' => 'foo',
   *   'value' => 'bar',
   * ]);
   *
   * @return Inputfield
   */
  public function addAfter($wrapper, $existingItem, $newItem)
  {
    if (!$existingItem instanceof Inputfield) $existingItem = $wrapper->get($existingItem);
    $wrapper->add($newItem);
    $newItem = $wrapper->children()->last();
    $wrapper->insertAfter($newItem, $existingItem);
    return $newItem;
  }

  /**
   * Show a success message on an inputfield
   */
  public function fieldSuccess($field, $msg)
  {
    if ($field instanceof Field) {
      if (!$field->name) throw new WireException("Field must have a name");
      $field = $field->name;
    }
    if (!is_string($field)) throw new WireException("Must be string");
    $messages = $this->rm()->fieldSuccessMessages->set($field, $msg);
    $this->wire->session->rmFieldSuccessMessages = $messages->getArray();
  }

  /**
   * Wrap fields of a form into a fieldset
   *
   * Usage:
   * $rm->wrapFields($form, ['foo', 'bar'], [
   *   'label' => 'your fieldset label',
   *   'icon' => 'bolt',
   * ]);
   *
   * @return InputfieldFieldset
   */
  public function wrapFields(
    InputfieldWrapper $form,
    array $fields,
    array $fieldset,
    $placeAfter = null
  ) {
    $_fields = [];
    $last = false;
    foreach ($fields as $k => $v) {
      $noLast = false;
      $field = $v;
      $fieldData = null;
      if (is_string($k)) {
        $field = $k;
        $fieldData = $v;
      }

      if (is_array($field)) {
        $form->add($field);
        $field = $form->children()->last();
        $noLast = true;
      }

      if (!$field instanceof Inputfield) $field = $form->get((string)$field);
      if (!$field) continue;
      if ($fieldData) $field->setArray($fieldData);
      if ($field instanceof Inputfield) {
        $_fields[] = $field;

        // we update the "last" variable to be the current field
        // we do not use runtime fields (applied via array syntax)
        // this ensures that the wrapper is at the same position where
        // the field of the form was
        if (!$noLast) $last = $field;
      }
    }

    // no fields, no render
    // this can be the case in modal windows when the page editor is called
    // with a ?field or ?fields get parameter to only render specific fields
    if (!count($_fields)) return;

    /** @var InputfieldFieldset $fs */
    $fs = $this->wire('modules')->get('InputfieldFieldset');
    foreach ($fieldset as $k => $v) $fs->$k = $v;
    if ($placeAfter) {
      if (!$placeAfter instanceof Inputfield) $placeAfter = $form->get((string)$placeAfter);
      $form->insertAfter($fs, $placeAfter);
    } elseif ($last) $form->insertAfter($fs, $last);
    else $form->add($fs);

    // now remove fields from the form and add them to the fieldset
    foreach ($_fields as $f) {
      // if the field is a runtime only field we add a temporary name
      // otherwise the remove causes an endless loop
      if (!$f->name) $f->name = uniqid();
      $form->remove($f);
      $fs->add($f);
    }

    return $fs;
  }
}
