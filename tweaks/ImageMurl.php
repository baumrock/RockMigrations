<?php

namespace RockMigrations\Tweaks;

use ProcessWire\HookEvent;

use function ProcessWire\wire;

class ImageMurl extends Tweak
{
  public $description = "Adds \$pagefile->murl property";

  public function ready()
  {
    wire()->addHookProperty("Pagefile::murl", $this, "murl");
  }

  public function murl(HookEvent $event): void
  {
    $file = $event->object;
    $url = ltrim($file->url, "/");
    $path = $this->wire->config->paths->root . $url;
    $m = $this->rockmigrations()->filemtime($path);
    $event->return = "/$url?m=$m";
  }
}
