<?php

namespace ProcessWire;

/**
 * @author Bernhard Baumrock, 11.02.2024
 * @license Licensed under MIT
 * @link https://www.baumrock.com
 */
class ProcessRockMigrations extends Process
{
  public static function getModuleInfo()
  {
    return [
      'title' => 'RockMigrations GUI',
      'version' => json_decode(file_get_contents(__DIR__ . "/package.json"))->version,
      'requires' => [
        'RockMigrations',
      ],
      'icon' => 'magic',
      'permission' => 'superuser',
      'page' => [
        'name' => 'rockmigrations',
        'parent' => 'setup',
        'title' => 'RockMigrations',
      ],
      // saved for later :)
      // 'nav' => [
      //   [
      //     'url' => 'once/',
      //     'label' => 'Once',
      //     'icon' => 'calendar',
      //   ]
      // ]
    ];
  }

  public function execute()
  {
    $this->wire->session->redirect("./once/");
  }

  public function executeOnce()
  {
    $this->headline('Migrations "once" History');
    $this->browserTitle('Migrations "once" History');
    /** @var InputfieldForm $form */
    $form = $this->wire->modules->get('InputfieldForm');

    $rm = rockmigrations();
    $table = "<table class='uk-table uk-table-small uk-table-striped'>";
    $table .= "<tr>
      <th>Executed</th>
      <th>Key</th>
      <th>Actions</th>
    </tr>";
    foreach ($rm->getOnceHistory() as $key => $item) {
      $data = new WireData();
      if (is_array($item)) $data->setArray($item);
      $hash = base64_encode($key);
      $label = $this->wire->sanitizer->entities($key);
      $file = $this->wire->sanitizer->entities((string) $data->file);
      $time = (int) $data->time;
      $table .= "<tr>
        <td class='uk-text-nowrap'>"
        . date('Y-m-d', $time)
        . " <small>" . date('H:i:s', $time) . "</small>"
        . "<div class='uk-text-muted'><small>" . wireRelativeTimeStr($time, true) . "</small></div>"
        . "</td>
        <td class='uk-width-expand'>
          {$label}
          <div class='uk-text-small uk-text-muted' style='margin-top:3px;'>{$file}</div>
        </td>
        <td class='uk-text-center'>
          <a
            class='uk-button uk-button-small uk-button-default'
            href='../once-clear-item?hash=$hash'
          ><i class='fa fa-trash-o'></i></a>
        </td>
      </tr>";
    }
    $table .= "<tr><td colspan=3 class='uk-text-center'>
      <a href='../once-clear-all' class='uk-button uk-button-small uk-button-default'><i class='fa fa-trash-o'></i> Clear All</a>
      </td></tr>";
    $table .= "</table>";

    $form->add([
      'type' => 'markup',
      'label' => 'Table',
      'value' => "<div class='uk-overflow-auto'>$table</div>",
    ]);

    return $form->render();
  }

  public function executeOnceClearAll()
  {
    // Check if confirmation has been given
    if ($this->wire->input->get->confirm == 1) {
      rockmigrations()->clearOnceHistory();
      $this->wire->session->redirect("./once/");
    }

    $this->headline('Clear all once history');
    $this->browserTitle('Clear all once history');
    /** @var InputfieldForm $form */
    $form = $this->wire->modules->get('InputfieldForm');
    $form->action = "?confirm=1";

    $form->add([
      'type' => 'markup',
      'label' => 'Are you sure?',
      'value' => "If the history is deleted, once folder scripts and inline once() callbacks with previously used keys will be executed again upon the next modules refresh, as the system will have lost the record of their initial execution. This could lead to unintended consequences or duplicate actions if those migrations were intended to run only once.",
      'icon' => 'question-circle-o',
    ]);

    // Add a submit button if confirmation has not been given
    $form->add([
      'type' => 'submit',
      'value' => 'Delete all history',
      'icon' => 'trash',
    ]);

    return $form->render();
  }

  public function executeOnceClearItem()
  {
    $hash = $this->wire->input->get('hash', 'string');
    if (!$hash) $this->wire->session->redirect('./once/');

    $key = base64_decode($hash);

    // Check if confirmation has been given
    if ($this->wire->input->get->confirm == 1) {
      rockmigrations()->clearOnceHistoryItem($key);
      $this->wire->session->redirect("./once/");
    }

    $this->headline('Clear once history item');
    $this->browserTitle('Clear once history item');
    /** @var InputfieldForm $form */
    $form = $this->wire->modules->get('InputfieldForm');
    $form->action = "?hash=$hash&confirm=1";
    $label = $this->wire->sanitizer->entities($key);

    $form->add([
      'type' => 'markup',
      'label' => "Are you sure?",
      'value' => "If the history entry is deleted, the once migration with the given key will be executed again upon the next modules refresh, as the system will have lost the record of its initial execution. This could lead to unintended consequences or duplicate actions if that migration was intended to run only once.",
      'icon' => 'question-circle-o',
    ]);

    // Add a submit button if confirmation has not been given
    $form->add([
      'type' => 'submit',
      'value' => "Delete history for $label",
      'icon' => 'trash',
    ]);

    return $form->render();
  }
}
