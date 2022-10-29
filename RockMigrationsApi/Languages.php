<?php

namespace RockMigrationsApi;

use ProcessWire\RockMigrations;
use ProcessWire\RockMigrationsApiTrait;

class Languages extends RockMigrations
{
  use RockMigrationsApiTrait;

  /**
   * Add a new language or return existing
   *
   * Also installs language support if missing.
   *
   * @param string $name Name of the language
   * @param string $title Optional title of the language
   * @return Language Language that was created
   */
  public function addLanguage(string $name, string $title = null)
  {
    // Make sure Language Support is installed
    $languages = $this->addLanguageSupport();
    if (!$languages) return $this->log("Failed installing LanguageSupport");

    $lang = $this->getLanguage($name);
    if (!$lang->id) {
      $lang = $languages->add($name);
      $languages->reloadLanguages();
    }
    if ($title) $lang->setAndSave('title', $title);
    return $lang;
  }

  /**
   * Install the languagesupport module
   * @return Languages
   */
  public function addLanguageSupport()
  {
    if (!$this->modules->isInstalled("LanguageSupport")) {
      $this->wire->pages->setOutputFormatting(false);
      $ls = $this->installModule("LanguageSupport", ['force' => true]);
      if (!$this->wire->languages) $ls->init();
    }
    return $this->wire->languages;
  }

  /**
   * Deletes a language
   * @param mixed $language
   * @return void
   */
  public function deleteLanguage($language, $quiet = false)
  {
    if (!$lang = $this->getLanguage($language, $quiet)) return;
    $this->wire->languages->delete($lang);
  }

  /**
   * Enable all languages for given page
   *
   * @param mixed $page
   * @return void
   */
  public function enableAllLanguagesForPage($page)
  {
    if (!$page) return;
    $page = $this->getPage($page);
    foreach ($this->languages ?: [] as $lang) $page->set("status$lang", 1);
    $page->save();
  }
}
