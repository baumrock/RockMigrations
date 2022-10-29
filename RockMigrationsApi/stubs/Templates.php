<?php

namespace ProcessWire;

use ProcessWire\Fieldgroup;
use ProcessWire\FieldtypeFieldsetClose;
use ProcessWire\FieldtypeFieldsetOpen;

use ProcessWire\RockMigrationsApiTrait;
use ProcessWire\Template;

class RockMigrations
{
  use RockMigrationsApiTrait;

  /**
   * Add field to template
   *
   * @param Field|string $field
   * @param Template|string $template
   * @return void
   */
  public function addFieldToTemplate($field, $template, $afterfield = null, $beforefield = null)
  {
    $field = $this->getField($field);
    if (!$field) return; // logging is done in getField()
    $template = $this->getTemplate($template);
    if (!$template) return; // logging is done in getField()
    if (!$afterfield and !$beforefield) {
      if ($template->fields->has($field)) return;
    }

    $afterfield = $this->getField($afterfield);
    $beforefield = $this->getField($beforefield);
    $fg = $template->fieldgroup;
    /** @var Fieldgroup $fg */

    if ($afterfield) $fg->insertAfter($field, $afterfield);
    elseif ($beforefield) $fg->insertBefore($field, $beforefield);
    else $fg->add($field);

    // add end field for fieldsets
    if (
      $field->type instanceof FieldtypeFieldsetOpen
      and !$field->type instanceof FieldtypeFieldsetClose
    ) {
      $closer = $field->type->getFieldsetCloseField($field, false);
      $this->addFieldToTemplate($closer, $template, $field);
    }

    // TODO fix this!
    // quickfix to prevent integrity constraint errors in backend
    try {
      $fg->save();
    } catch (\Throwable $th) {
      $this->log($th->getMessage());
    }
  }

  /**
   * Add fields to template.
   *
   * Simple:
   * $rm->addFieldsToTemplate(['field1', 'field2'], 'yourtemplate');
   *
   * Add fields at special positions:
   * $rm->addFieldsToTemplate([
   *   'field1',
   *   'field4' => 'field3', // this will add field4 after field3
   * ], 'yourtemplate');
   *
   * @param array $fields
   * @param string $template
   * @param bool $sortFields
   * @return void
   */
  public function addFieldsToTemplate($fields, $template, $sortFields = false)
  {
    foreach ($fields as $k => $v) {
      // if the key is an integer, it's a simple field
      if (is_int($k)) $this->addFieldToTemplate((string)$v, $template);
      else $this->addFieldToTemplate((string)$k, $template, $v);
    }
    if ($sortFields) $this->setFieldOrder($fields, $template);
  }

  /**
   * Create a new ProcessWire Template
   *
   * Usage:
   * $rm->createTemplate('foo', [
   *   'fields' => ['foo', 'bar'],
   * ]);
   *
   * @param string $name
   * @param bool|array $data
   * @param bool $migrate
   * @return Template
   */
  public function createTemplate($name, $data = true, $migrate = true)
  {
    // quietly get the template
    // it is quiet to prevent "template xx not found" logs
    $t = $this->getTemplate($name, true);
    if (!$t) {
      // create new fieldgroup
      $fg = $this->wire(new Fieldgroup());
      $fg->name = $name;
      $fg->save();

      // create new template
      $t = $this->wire(new Template());
      $t->name = $name;
      $t->fieldgroup = $fg;
      $t->save();

      if ($migrate) {
        // trigger migrate() of that new template
        $p = $this->wire->pages->newPage(['template' => $t]);
        if (method_exists($p, "migrate")) $p->migrate();
      }
    }

    // handle different types of second parameter
    if (is_bool($data)) {
      // add title field to this template if second param = TRUE
      if ($data) $this->addFieldToTemplate('title', $t);
    } elseif (is_string($data)) {
      // second param is a string
      // eg "\MyModule\MyPageClass"
      $this->setTemplateData($t, ['pageClass' => $data]);
    } elseif (is_array($data)) {
      // second param is an array
      // that means we set the template data from array syntax
      $this->setTemplateData($t, $data);
    }

    return $t;
  }

  /**
   * Delete a ProcessWire Template
   * @param mixed $tpl
   * @param bool $quiet
   * @return void
   */
  public function deleteTemplate($tpl, $quiet = false)
  {
    $template = $this->getTemplate($tpl, $quiet);
    if (!$template) return;

    // remove all pages having this template
    foreach ($this->pages->find("template=$template, include=all") as $p) {
      $this->deletePage($p);
    }

    // make sure we can delete the template by removing all flags
    $template->flags = Template::flagSystemOverride;
    $template->flags = 0;

    // delete the template
    $this->templates->delete($template);

    // delete the fieldgroup
    $fg = $this->fieldgroups->get((string)$tpl);
    if ($fg) $this->fieldgroups->delete($fg);
  }

  /**
   * Delete templates
   *
   * Usage
   * $rm->deleteTemplates("tags=YourModule");
   *
   * @param string $selector
   */
  public function deleteTemplates($selector, $quiet = false)
  {
    $templates = $this->wire->templates->find($selector);
    foreach ($templates as $tpl) $this->deleteTemplate($tpl, $quiet);
  }
}
