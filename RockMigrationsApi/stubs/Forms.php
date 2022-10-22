<?php

namespace ProcessWire;

use ProcessWire\Field;

use ProcessWire\WireException;

class RockMigrations
{

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
}
