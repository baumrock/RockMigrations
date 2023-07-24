<?php

namespace RockMigrations\Tweaks;

use ProcessWire\Debug;
use ProcessWire\HookEvent;

/**
 * NOTE: This tweak might be removed soon if Adrian adds it to TracyDebugger
 * See https://processwire.com/talk/topic/24932-feature-requests/?do=findComment&comment=234733
 *
 * It's very easy to add redirections in ProcessWire - just call $session->redirect(...)
 *
 * But it's also very easy to get lost. Sometimes you get redirected and you don't know why!
 * Maybe you have many redirections in place and you don't know which one triggered
 * the redirection in your case. This Tweak helps you easily find out where the
 * redirection came from. Just enable the Tweak and make sure DEBUG mode is ON
 * and you will see a backtrace in the trace console. show you the file that triggered the redirection.
 */

class RedirectLogger extends Tweak
{
  public $description = "Show what triggered \$session->redirect in Tracy Debug Bar (DEBUG mode needs to be enabled).";

  public function ready()
  {
    if (!$this->wire->config->debug) return;
    $this->wire->addHookBefore("Session::redirect", $this, "dumpBacktrace");
  }

  public function dumpBacktrace(HookEvent $event)
  {
    if (!function_exists("bd")) return;
    bd(Debug::backtrace()[0]);
  }
}
