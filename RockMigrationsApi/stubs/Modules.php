<?php

namespace ProcessWire;

use ProcessWire\Modules as ProcessWireModules;
use ProcessWire\ProcessModuleInstall;

use ProcessWire\RockMigrationsApiTrait;

class RockMigrations
{
  use RockMigrationsApiTrait;

  /**
   * Delete module
   * This deletes the module files and then removes the entry in the modules
   * table. Removing the module via uninstall() did cause an endless loop.
   * @param mixed $name
   * @return void
   */
  public function deleteModule($name, $path = null)
  {
    $name = (string)$name;
    if ($this->wire->modules->isInstalled($name)) $this->uninstallModule($name);
    if (!$path) $path = $this->wire->config->paths->siteModules . $name;
    if (is_dir($path)) $this->wire->files->rmdir($path, true);
    $this->wire->database->exec("DELETE FROM modules WHERE class = '$name'");
  }

  /**
   * Disable module
   *
   * This is a quickfix for modules that are not uninstallable by
   * uninstallModule() - I don't know why this does not work for some modules...
   * if you do please let me know!
   *
   * @param string|Module $name
   * @return void
   */
  public function disableModule($name)
  {
    $this->wire->modules->setFlag(
      (string)$name,
      ProcessWireModules::flagsDisabled,
      true
    );
  }

  /**
   * Download module from url
   *
   * @param string $url
   * @return mixed bool|string Returns destinationDir on success, false on failure.
   */
  public function downloadModule($url)
  {
    if (!class_exists('ProcessWire\ProcessModuleInstall')) {
      require_once $this->config->paths->modules
        . "Process/ProcessModule/ProcessModuleInstall.php";
    }
    /** @var ProcessModuleInstall $installer */
    $installer = $this->wire(new ProcessModuleInstall());
    $downloaded = $installer->downloadModule($url);
    if ($downloaded !== false) return $downloaded;
    $this->log("Tried to download module from $url but failed");
    return false;
  }
}
