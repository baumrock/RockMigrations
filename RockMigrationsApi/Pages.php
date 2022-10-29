<?php

namespace RockMigrationsApi;

use ProcessWire\Page;
use ProcessWire\PageArray;
use ProcessWire\RockMigrations;
use ProcessWire\RockMigrationsApiTrait;
use ProcessWire\WireRandom;

class Pages extends RockMigrations
{
  use RockMigrationsApiTrait;

  /**
   * Create a new Page
   *
   * If the page exists it will return the existing page.
   * Note that all available languages will be set active by default!
   *
   * If you need to set a multilang title use
   * $rm->setFieldLanguageValue($page, "title", [
   *   'default'=>'foo',
   *   'german'=>'bar',
   * ]);
   *
   * @param string $title
   * @param string $name
   * @param Template|string $template
   * @param Page|string $parent
   * @param array $status
   * @param array $data
   * @return Page
   */
  public function createPage(string $title, $name, $template, $parent, array $status = [], array $data = [])
  {
    // create pagename from page title if it is not set
    if (!$name) $name = $this->sanitizer->pageNameTranslate($title);

    $log = "Parent $parent not found";
    $parent = $this->getPage($parent);
    if (!$parent->id) return $this->log($log);

    // get page if it exists
    $page = $this->getPage([
      'name' => $name,
      'template' => $template,
      'parent' => $parent,
    ], true);

    if ($page and $page->id) {
      $page->status($status);
      $page->setAndSave($data);
      return $page;
    }

    // create a new page
    $p = $this->wire(new Page());
    $p->template = $template;
    $p->title = $title;
    $p->name = $name;
    $p->parent = $parent;
    $p->status($status);
    $p->setAndSave($data);

    // enable all languages for this page
    $this->enableAllLanguagesForPage($p);

    return $p;
  }

  /**
   * Create or return a PW user
   *
   * This will use a random password for the user
   *
   * Usage:
   * $rm->createUser('demo', [
   *   'roles' => ['webmaster'],
   * ]);
   *
   * @param string $username
   * @param array $data
   * @return User|false
   */
  public function createUser($username, $data = [])
  {
    $user = $this->getUser($username, true);
    if (!$user) return false;
    if (!$user->id) {
      $user = $this->wire->users->add($username);

      // setup password
      $rand = $this->wire(new WireRandom());
      /** @var WireRandom $rand */
      $password = $rand->alphanumeric(null, [
        'minLength' => 10,
        'maxLength' => 20,
      ]);
      $data['password'] = $password;
    }
    $this->setUserData($user, $data);
    return $user;
  }

  /**
   * Delete the given page including all children.
   *
   * @param Page|string $page
   * @return void
   */
  public function deletePage($page, $quiet = false)
  {
    if (!$page = $this->getPage($page, $quiet)) return;

    // temporarily disable filesOnDemand feature
    // this prevents PW from downloading files that are deleted from a local dev
    // system but only exist on the live system
    $ondemand = $this->wire->config->filesOnDemand;
    $this->wire->config->filesOnDemand = false;

    // make sure we can delete the page and delete it
    // we also need to make sure that all descendants of this page are deletable
    // todo: make this recursive?
    $all = $this->wire(new PageArray());
    $all->add($page);
    $all->add($this->wire->pages->find("has_parent=$page, include=all"));
    foreach ($all as $p) {
      $p->addStatus(Page::statusSystemOverride);
      $p->status = 1;
      $p->save();
    }
    $this->wire->pages->delete($page, true);

    $this->wire->config->filesOnDemand = $ondemand;
  }

  /**
   * Delete a PW user
   *
   * @param string $username
   * @return void
   */
  public function deleteUser($username, $quiet = false)
  {
    if (!$user = $this->getUser($username, $quiet)) return;
    $this->wire->users->delete($user);
  }
}
