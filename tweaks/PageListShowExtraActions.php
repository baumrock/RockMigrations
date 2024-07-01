<?php

namespace RockMigrations\Tweaks;

use ProcessWire\HookEvent;

class PageListShowExtraActions extends Tweak
{
  public $description = "Shows extra page action items in page tree for SuperUsers - Originally from AdminOnSteroids";

  public function ready()
  {
    if (!$this->wire->user->isSuperuser()) return;
    if ($this->wire->page->template != 'admin') return;
    $this->wire->addHookAfter("Page(template=admin)::render", $this, "addStyle");
  }

  public function addStyle(HookEvent $event)
  {
    $event->return = str_replace(
      "</body>",
      "<script>
      /* Originally by tpr in the AdminOnSteroids module */
      \$(document).on('hover', '.PageListItem', function () {
         var \$extrasToggle = $(this).find('.clickExtras'),
             \$templateEditAction = $(this).find('.PageListActionEdit ~ .PageListActionEdit');
         if (\$extrasToggle.length) {
             \$extrasToggle.trigger('click').remove();
             if ($(this).find('.PageListActionExtras').length) {
                 $(this).find('.PageListActionExtras').remove();
             }
             // move template edit link to the end
             if (\$templateEditAction.length) {
                 \$templateEditAction.parent().append(\$templateEditAction);
             }
         }
      });
      </script>
      </body>",
      $event->return
    );
  }
}
