<?php

namespace ProcessWire;

use ProcessWire\HookEvent;
use ProcessWire\Page;


class RockMigrations
{

  /**
   * Add scripts to $config->scripts and add cache busting timestamp
   */
  public function addScripts($scripts)
  {
    if (!is_array($scripts)) $scripts = [$scripts];
    foreach ($scripts as $script) {
      $path = $this->filePath($script);
      // if file is not found we silently skip it
      // it is silent because of MagicPages::addPageAssets
      if (!is_file($path)) continue;
      $url = str_replace(
        $this->wire->config->paths->root,
        $this->wire->config->urls->root,
        $path
      );
      $this->wire->config->scripts->add($url . "?m=" . filemtime($path));
    }
  }

  /**
   * Add styles to $config->styles and add cache busting timestamp
   */
  public function addStyles($styles)
  {
    if (!is_array($styles)) $styles = [$styles];
    foreach ($styles as $style) {
      $path = $this->filePath($style);

      // check if it is a less file
      if (pathinfo($path, PATHINFO_EXTENSION) === 'less') {
        $path = $this->saveCSS($path);
      }

      // if file is not found we silently skip it
      // it is silent because of MagicPages::addPageAssets
      if (!is_file($path)) continue;
      $url = str_replace(
        $this->wire->config->paths->root,
        $this->wire->config->urls->root,
        $path
      );
      $this->wire->config->styles->add($url . "?m=" . filemtime($path));
    }
  }

  /**
   * Make all pages having given template be created on top of the list
   *
   * Usage in init():
   * $rm->createOnTop('my-template');
   *
   * @return void
   */
  public function createOnTop($tpl)
  {
    $tpl = $this->wire->templates->get((string)$tpl);
    $this->addHookAfter("Pages::added", function (HookEvent $event) {
      $page = $event->arguments(0);
      $this->wire->pages->sort($page, 0);
    });
  }

  /**
   * Remove non-breaking spaces in string
   * @return string
   */
  public function regularSpaces($str)
  {
    return preg_replace('/\xc2\xa0/', ' ', $str);
  }

  /**
   * Compile LESS file to CSS and save it in the same folder
   *
   * foo.less --> foo.less.css
   *
   * This is intended to be used for module development. You can work on a LESS
   * file and auto-compile it to CSS so that you can ship your module with
   * both files easily (to make it work if the Less module is not installed).
   */
  public function saveCSS($less, $onlySuperuser = true): string
  {
    $css = "$less.css";
    if (!is_file($less)) return $css;

    $mLESS = filemtime($less);
    $mCSS = is_file($css) ? filemtime($css) : 0;

    $sudoCheck = $onlySuperuser ? $this->wire->user->isSuperuser() : true;
    if ($mLESS > $mCSS and $sudoCheck) {
      if ($parser = $this->wire->modules->get('Less')) {
        // recreate css file
        /** @var Less $parser */
        $parser->addFile($less);
        $parser->saveCss($css);
        $mCSS = time();
        $this->log("Created new CSS file: $css");
      }
    }
    return $css;
  }

  /**
   * Set page name from field (or multiple fields) of template
   *
   * Usage:
   * $rm->setPageNameFromField("basic-page", "headline");
   * $rm->setPageNameFromField("basic-page", ["headline", "title"]);
   *
   * Make sure to install Page Path History module!
   *
   * @return void
   */
  public function setPageNameFromField($template, $fields = 'title')
  {
    if ($template instanceof Page) $template = $template->template;
    $template = $this->wire->templates->get((string)$template);
    if (!$template) return;
    $tpl = "template=$template";
    $this->addHookAfter("Pages::saved($tpl,id>0)", function (HookEvent $event) use ($fields) {
      /** @var Page $page */
      $page = $event->arguments(0);
      if ($page->rmSetPageName) return;
      $page->rmSetPageName = true;
      $langs = $this->wire->languages;
      if ($langs) {
        foreach ($langs as $lang) {
          try {
            // dont know why exactly that is necessary but had problems
            // at kaumberg "localName not callable in this context"??
            // though the method was definitely there...
            $old = $page->localName($lang);
          } catch (\Throwable $th) {
            $old = $page->name;
          }

          // get new value
          if (is_array($fields)) {
            $new = '';
            foreach ($fields as $field) {
              if ($new) continue;
              $new = $page->getLanguageValue($lang, (string)$field);
            }
          } else $new = $page->getLanguageValue($lang, (string)$fields);

          $new = $event->sanitizer->markupToText($new);
          $new = $event->sanitizer->pageNameTranslate($new);
          $new = $event->wire->pages->names()->uniquePageName($new, $page);
          if ($old != $new) {
            if ($lang->isDefault()) $page->setName($new);
            else $page->setName($new, $lang);
            $this->message($this->_("Page name updated to $new ($lang->name)"));
          }
        }
        $page->save(['noHooks' => true]);
      } else {
        $old = $page->name;

        if (is_array($fields)) $new = $page->get(implode("|", $fields));
        else $new = $page->get((string)$fields);

        $new = $event->sanitizer->markupToText($new);
        $new = $event->sanitizer->pageNameTranslate($new);
        if ($new and $old != $new) {
          $new = $event->wire->pages->names()->uniquePageName($new, $page);
          $page->name = $new;
        }
        $page->save(['noHooks' => true]);
        $this->message($this->_("Page name updated to $new"));
      }
    });
    $this->addHookAfter("ProcessPageEdit::buildForm", function (HookEvent $event) use ($template, $fields) {
      $field = is_array($fields) ? implode("|", $fields) : $fields;
      $page = $event->object->getPage();
      if ($page->template != $template) return;
      $form = $event->return;
      if ($f = $form->get('_pw_page_name')) {
        $f->prependMarkup = "<style>#wrap_{$f->id} input[type=text] { display: none; }</style>";
        $f->notes = $this->_("Page name will be set automatically from field '$field' on save.");
      }
    });
  }

  /**
   * Set page name from page title
   *
   * Usage:
   * $rm->setPageNameFromTitle("basic-page");
   *
   * Make sure to install Page Path History module!
   *
   * @param mixed $object
   */
  public function setPageNameFromTitle($template)
  {
    return $this->setPageNameFromField($template, 'title');
  }
}
