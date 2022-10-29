<?php

namespace RockMigrationsApi;

use ProcessWire\RockMigrations;
use ProcessWire\RockMigrationsApiTrait;

class Access extends RockMigrations
{
  use RockMigrationsApiTrait;

  /**
   * Add a permission to given role
   *
   * @param string|int $permission
   * @param string|int $role
   * @return boolean
   */
  public function addPermissionToRole($permission, $role)
  {
    $role = $this->getRole($role);
    if (!$role) return $this->log("Role $role not found");
    $role->of(false);
    $role->addPermission($permission);
    return $role->save();
  }

  /**
   * Add role to user
   *
   * @param string $role
   * @param User|string $user
   * @return void
   */
  public function addRoleToUser($role, $user)
  {
    $role = $this->getRole($role);
    $user = $this->getUser($user);
    $msg = "Cannot add role to user";
    if (!$role) return $this->log("$msg - role not found");
    if (!$user) return $this->log("$msg - user not found");
    $user->of(false);
    $user->addRole($role);
    $user->save();
  }

  /**
   * Add given permission to given template for given role
   *
   * Example for single template:
   * $rm->addTemplateAccess("my-template", "my-role", "edit");
   *
   * Example for multiple templates/roles/permissions:
   * $rm->addTemplateAccess([
   *   'home',
   *   'basic-page',
   * ],
   * [
   *   'admin',
   *   'author',
   * ],
   * [
   *   'add',
   *   'edit',
   * ]);
   *
   * @param mixed string|array $templates template name or array of names
   * @param mixed string|array $roles role name or array of names
   * @param mixed string|array $accs permission name or array of names
   * @return void
   */
  public function addTemplateAccess($templates, $roles, $accs)
  {
    if (!is_array($templates)) $templates = [$templates];
    if (!is_array($roles)) $roles = [$roles];
    if (!is_array($accs)) $accs = [$accs];
    foreach ($roles as $role) {
      if (!$role = $this->getRole($role)) continue;
      foreach ($templates as $tpl) {
        $tpl = $this->getTemplate($tpl);
        if (!$tpl) continue; // log is done above
        foreach ($accs as $acc) {
          $tpl->addRole($role, $acc);
        }
        $tpl->save();
      }
    }
  }

  /**
   * Create permission with given name
   *
   * @param string $name
   * @param string $description
   * @return Permission
   */
  public function createPermission($name, $description = null)
  {
    if (!$perm = $this->getPermission($name)) {
      $perm = $this->wire->permissions->add($name);
      $this->log("Created permission $name");
    }
    if (!$description) $description = $name;
    $perm->setAndSave('title', $description);
    return $perm;
  }

  /**
   * Create role with given name
   * @param string $name
   * @param array $permissions
   * @return Role|null
   */
  public function createRole($name, $permissions = [])
  {
    if (!$name) return $this->log("Define a name for the role!");

    $role = $this->getRole($name, true);
    if (!$role) $role = $this->roles->add($name);
    foreach ($permissions as $permission) {
      $this->addPermissionToRole($permission, $role);
    }

    return $role;
  }

  /**
   * Helper to create webmaster role
   * @return Role
   */
  public function createRoleWebmaster($permissions = [], $name = 'webmaster')
  {
    $permissions = array_merge([
      'page-edit',
      'page-edit-front',
      'page-delete',
      'page-move',
      'page-sort',
      'rockfrontend-alfred',
    ], $permissions ?: []);
    return $this->createRole($name, $permissions);
  }

  /**
   * Delete the given permission
   *
   * @param Permission|string $permission
   * @return void
   */
  public function deletePermission($permission, $quiet = false)
  {
    if (!$permission = $this->getPermission($permission, $quiet)) return;
    $this->permissions->delete($permission);
  }

  /**
   * Delete the given role
   * @param Role|string $role
   * @param bool $quiet
   * @return void
   */
  public function deleteRole($role, $quiet = false)
  {
    if (!$role = $this->getRole($role, $quiet)) return;
    $this->roles->delete($role);
  }
}
