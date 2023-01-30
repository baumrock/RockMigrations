<?php

namespace RockMigrations\Tweaks;

use ProcessWire\HookEvent;

use function ProcessWire\wireIconMarkup;

class ImageDownload extends Tweak
{
  public $description = "Adds a download icon to image fields.
    <a href=https://processwire.com/talk/topic/28089-weekly-update-%E2%80%93%C2%A027-january-2023/ target=_blank><i class='fa fa-info-circle'></i></a>";

  public function ready()
  {
    if ($this->wire->page->template != 'admin') return;
    $this->wire->addHookAfter('InputfieldImage::getImageThumbnailActions', function (HookEvent $event) {
      $image = $event->arguments(0); // Pageimage
      $class = $event->arguments(3); // class to use on all returned actions
      $a = $event->return; // array
      $icon = wireIconMarkup('download');
      $a['download'] = "<a class='$class' href='$image->url' download>$icon</a>";
      $event->return = $a;
    });
    $this->wire->addHookAfter('InputfieldImage::getImageEditButtons', function (HookEvent $event) {
      $image = $event->arguments(0); // Pageimage
      $class = $event->arguments(3); // class(es) to use on all returned actions
      $buttons = $event->return; // array, indexed by action name
      $icon = wireIconMarkup('download');
      $buttons['download'] = "<button class='$class'><a download href='$image->url'>$icon Download</a></button>";
      $event->return = $buttons;
    });
  }
}
