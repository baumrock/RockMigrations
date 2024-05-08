<?php

namespace RockMigrations\Tweaks;

use ProcessWire\HookEvent;

class PrevNextPage extends Tweak
{
  public $description = "Add buttons/options to edit prev/next page - Originally from AdminOnSteroids";


  public function ready()
  {
    if ($this->wire->page->template != 'admin') return;

    $this->editedPage = false;
    $editedPageId = $this->wire('sanitizer')->int($this->wire('input')->get->id);
    $editedPage = $this->wire('pages')->get($editedPageId);

    if ($editedPage->id && !($editedPage instanceof RepeaterPage)) {
      $this->editedPage = $editedPage;
    }

    if (
      in_array($this->wire('page')->process, array('ProcessPageEdit', 'ProcessUser', 'ProcessRole'))
      && $this->wire('input')->id
      && $this->editedPage
      //                    && !($this->editedPage->template->flags && $this->editedPage->template->flags === Template::flagSystem)
    ) {

      // sort precedence: template level - page level - "sort"
      $sortfield = 'sort';
      $parent = $this->editedPage->parent();

      if ($parent->id) {
        $sortfield = $parent->template->sortfield ?: $parent->sortfield;
      }

      $baseSelector = 'include=all, template!=admin, id!=' . $this->wire('config')->http404PageID . ', parent=' . $parent;
      $prevnextlinks = array();
      $isFirst = false;
      $isLast = false;
      $numSiblings = $parent->numChildren(true);

      if ($numSiblings > 1) {

        $selector = $baseSelector . ', sort=' . $sortfield;

        if (strpos($sortfield, '-') === 0) {
          $sortfieldReversed = ltrim($sortfield, '-');
        } else {
          $sortfieldReversed = '-' . $sortfield;
        }

        $next = $this->editedPage->next($selector);
        $prev = $this->editedPage->prev($selector);

        if (!$next->id) {
          $next = $this->editedPage->siblings($selector . ', limit=1')->first();
          $isFirst = true;
        }

        if (!$prev->id) {
          $prev = $this->editedPage->siblings($baseSelector . ', limit=1, sort=' . $sortfieldReversed)->first();
          $isLast = true;
        }

        $edit_next_text = $isFirst ? ' ' . $this->_('Edit first:') : $this->_('Edit next:');
        $edit_prev_text = $isLast ? ' ' . $this->_('Edit last:') : $this->_('Edit previous:');

        if ($prev && $prev->id && $prev->editable()) {
          $prevnextlinks['prev'] = array(
            'title' => $edit_prev_text . ' ' . ($prev->title ? $prev->title : $prev->name),
            'url' => $prev->editUrl,
          );
        }

        if ($next && $next->id && $next->editable()) {
          $prevnextlinks['next'] = array(
            'title' => $edit_next_text . ' ' . ($next->title ? $next->title : $next->name),
            'url' => $next->editUrl,
          );
        }

        // add js chunk to be expanded as HTML
        if (!empty($prevnextlinks)) {
          $this->wire('config')->js('AOS_prevnextlinks', $prevnextlinks);
        }
      }
    }
    $this->wire->addHookAfter("Page(template=admin)::render", $this, "addStyle");
  }


  public function addStyle(HookEvent $event)
  {
    $event->return = str_replace(
      "</head>",
      "<style>
      .aos-edit-prev,
      .aos-edit-next {
        padding: 0 0.3rem;
        position: relative;
        top: 1px;
      }

      .aos-edit-prev:not(:hover),
      .aos-edit-next:not(:hover) {
        color: #ccc;
      }

      .aos-edit-prev i,
      .aos-edit-next i {
        font-size: 17px !important;
      }

      html:not(.AdminThemeDefault) .aos-edit-prev i,
      html:not(.AdminThemeDefault) .aos-edit-next i {
        font-size: 27px !important;
      }

      .aos-edit-prev {
        margin-left: 0.6rem;
      }
      </style></head>",
      $event->return
    );
    $event->return = str_replace(
      "</body>",
      "<script>
       var PrevNextLinks = ProcessWire.config.AOS_prevnextlinks;
       if (PrevNextLinks) {
         var targetElement = $('h1, li.title span, li.title').first()
         if (targetElement.length) {
           var icon
           if (PrevNextLinks.prev) {
             icon = 'fa fa-angle-left'
             targetElement.append('<a href=\"' + PrevNextLinks.prev.url + '\" title=\"' + PrevNextLinks.prev.title + '\" class=\"aos-edit-prev\"><i class=\"' + icon + '\"></i></a>')
           }
           if (PrevNextLinks.next) {
             icon = 'fa fa-angle-right'
             targetElement.append('<a href=\"' + PrevNextLinks.next.url + '\" title=\"' + PrevNextLinks.next.title + '\" class=\"aos-edit-next\"><i class=\"' + icon + '\"></i></a>')
           }
         }
       }
      </script>
      </body>",
      $event->return
    );
  }


}
