<?php

namespace RockMigrations\Tweaks;

use ProcessWire\HookEvent;
use ProcessWire\Inputfield;

class BypassTrash extends Tweak
{
  public $description = "Add buttons/options to bypass trash for SuperUsers - Originally from AdminOnSteroids";


  public function ready()
  {
    if (!$this->wire->user->isSuperuser()) return;
    if ($this->wire->page->template != 'admin') return;

    $this->addHookAfter('ProcessPageListActions::getExtraActions', $this, 'addDeleteButton');
    $this->addHookAfter('ProcessPageListActions::processAction', $this, 'addDeleteButtonAction');

    // add delete field to page edit Delete tab
    $this->addHookAfter('ProcessPageEdit::buildFormDelete', $this, 'addDeletePermanentlyField');
    $this->addHookBefore('Pages::trash', $this, 'addDeletePermanentlyHook');

    $this->wire->addHookAfter("Page(template=admin)::render", $this, "addStyle");

    // Translatable strings
    $this->strings = new \StdClass();
    $this->strings->cancel = $this->_("Cancel Deletion");
    $this->strings->confirm = $this->_("Delete Permanently");
    $this->strings->skip_trash = $this->_('Skip Trash?');
    $this->strings->desc = $this->_('Check to permanently delete this page.');
    $this->strings->deleted = $this->_('Deleted page: %s');

    $this->editedPage = false;
    $editedPageId = $this->wire('sanitizer')->int($this->wire('input')->get->id);
    $editedPage = $this->wire('pages')->get($editedPageId);

    if ($editedPage->id && !($editedPage instanceof RepeaterPage)) {
      $this->editedPage = $editedPage;
    }
  }



  public function addStyle(HookEvent $event)
  {
    $event->return = str_replace(
      "</head>",
      "<style>
      a.aos-pagelist-confirm + a.aos-pagelist-confirm {
        margin-left: 3px;
      }
      </style></head>",
      $event->return
    );

    $str_cancel  = json_encode($this->strings->cancel);
    $str_confirm = json_encode($this->strings->confirm);

    $event->return = str_replace(
      "</body>",
      "<script>
      var str_cancel = $str_cancel
      var str_confirm = $str_confirm
      // Delete + non-superuser Trash actions
      // $(document).on('mousedown', 'a.PageListActionTrash.aos-pagelist-confirm, .PageTrash.aos-pagelist-confirm', function (e) {
      $(document).on('mousedown', 'a.aos-pagelist-confirm', function (e) {

          e.preventDefault();

          if (e.which === 3 || e.which === 2) return false;

          var link = $(this),
              url = link.attr('href'),
              linkTextDefault;

          if (!link.attr('data-text-original')) {

              var currentText = $(this).get(0).childNodes[1] ? $(this).get(0).childNodes[1].nodeValue : $(this).html();

              link.attr('data-text-original', currentText);

              if (link.hasClass('PageListActionDelete') || link.hasClass('PageDelete')) {
                  link.attr('data-text-confirm', str_confirm);
              }
          }


          if (url.indexOf('&force=1') === -1) {

              var linkCancel;

              linkTextDefault = link.attr('data-text-original');

              if (link.hasClass('cancel')) {
                  linkCancel = link.next('a');

                  linkCancel
                      .removeClass('cancel')
                      .attr('href', link.attr('href').replace('&force=1', ''))
                      .contents().last()[0].textContent = linkTextDefault;

                  link.replaceWith(linkCancel);

                  return false;
              }

              linkTextDefault = link.attr('data-text-confirm') ? link.attr('data-text-confirm') : link.attr('data-text-original');

              linkCancel = link.clone(true);
              linkCancel
                  .addClass('cancel')
                  .contents().last()[0].textContent = ' ' + str_cancel;

              // replace text only (keep icon)
              link.contents().last()[0].textContent = linkTextDefault;
              link.attr('href', link.attr('href') + '&force=1');

              link.before(linkCancel);
          }

          return false;
      });
      </script>
      </body>",
      $event->return
    );
  }


  /**
   * Add Delete button to pagelist
   */
  public function addDeleteButton(HookEvent $event)
  {
    $page = $event->arguments('page');

    if (!$this->wire('user')->isSuperuser()) {
      return false;
    }

    // do not allow for pages having children
    if ($page->numChildren > 0) {
      return false;
    }

    //  not trashable and not in Trash
    if (!$page->trashable() && !$page->isTrash()) {
      return false;
    }

    $actions = array();
    $adminUrl = $this->wire('config')->urls->admin . 'page/';
    $icon = '';

    $actions['delete'] = array(
      'cn' => 'Delete aos-pagelist-confirm',
      'name' => $icon . 'Delete',
      'url' => $adminUrl . '?action=delete&id=' . $page->id,
      'ajax' => true,
    );

    $event->return += $actions;
  }


  /**
   * Process action for addDeleteButton.
   *
   * @return bool
   */
  public function addDeleteButtonAction(HookEvent $event)
  {

    $page = $event->arguments(0);
    $action = $event->arguments(1);

    // do not allow for pages having children
    if ($page->numChildren > 0) {
      return false;
    }

    if ($action == 'delete') {

      //                            $page->setAndSave('title', $page->title . '-hello');
      $page->delete();

      $event->return = array(
        'action' => $action,
        'success' => true,
        'page' => $page->id,
        'updateItem' => $page->id,
        'message' => 'Page deleted.',
        'remove' => true,
        'refreshChildren' => false,
      );
    }
  }


  public function addDeletePermanentlyField(HookEvent $event)
  {
    if ($this->editedPage && !$this->editedPage->trashable()) {
      return false;
    }

    $form = $event->return;

    $trashConfirmField = $form->get('delete_page');
    if (!$trashConfirmField) {
      return false;
    }

    $f = $this->wire('modules')->get('InputfieldCheckbox');
    $f->attr('id+name', 'delete_permanently');
    $f->checkboxLabel = $this->strings->confirm;
    $f->label = $this->strings->skip_trash;
    $f->description = $this->strings->desc;
    $f->value = '1';

    $trashConfirmField->columnWidth = 50;
    $f->columnWidth = 50;

    $f->collapsed = Inputfield::collapsedNever;
    $trashConfirmField->collapsed = Inputfield::collapsedNever;

    // add fieldset (Reno top spacing bug)
    if ($this->adminTheme === 'AdminThemeReno') {
      $fset = $this->wire('modules')->get('InputfieldFieldset');
      $fset->add($trashConfirmField);
      $fset->add($f);
      $form->remove($trashConfirmField);
      $form->insertBefore($fset, $form->get('submit_delete'));
    } else {
      $form->insertAfter($f, $trashConfirmField);
    }
  }


  // delete page instead trashing if delete_permanently was checked
  public function addDeletePermanentlyHook(HookEvent $event)
  {
    if (isset($this->wire('input')->post->delete_permanently)) {
      $p = $event->arguments[0];
      $session = $this->wire('session');
      $afterDeleteRedirect = $this->wire('config')->urls->admin . "page/?open={$p->parent->id}";
      if ($p->deleteable()) {
        $session->message(sprintf($this->strings->deleted, $p->url)); // Page deleted message
        $this->wire('pages')->delete($p, true);
        $session->redirect($afterDeleteRedirect);
      }
    }
  }
}
