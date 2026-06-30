# Once

RockMigrations provides two ways to run code **once per system** — useful for data backfills, one-time fixes, or reverting a change that must not repeat on every modules refresh.

1. **Once folder scripts** — PHP files in `RockMigrations/once/` (recommended for deploy-time backfills).
2. **Inline `$rm->once()` callback** — wrap code inside a migration file.

Both use the same execution history stored in the RockMigrations module config. View or clear history under **Setup → RockMigrations → Once History**.

---

## Once folder scripts

Add imperative PHP scripts that run automatically after all other migrations finish. Each script runs **once per system** (local, staging, production each track their own history).

Typical use case: you shipped a schema/feature migration and need a one-time data backfill on every remote environment — without copying a Tracy console snippet by hand.

### Supported directories

- `site/RockMigrations/once/[name].php`
- `site/modules/[module-name]/RockMigrations/once/[name].php` (installed modules only)

Module paths follow the same rules as [config migrations](../config-migrations/).

### File naming (required)

Every script **must** match:

```
YYYY-MM-DD-<slug>.php
```

Examples:

- `2026-06-30-backfill-order-economics.php`
- `2026-07-01-fix-group-delivery-defaults.php`

Rules:

- **Date:** creation date in ISO form (`YYYY-MM-DD`).
- **Slug:** lowercase, hyphenated, 2–6 words describing the script.
- Files that do **not** match this pattern are **skipped** and a warning is written to the migration log.

Scripts are executed in **filename sort order** (the date prefix gives chronological order when multiple scripts are pending).

### Script shape

Once folder scripts are plain imperative PHP. RockMigrations includes the file with `$rm` (the RockMigrations instance) in scope. No return value is required.

```php
<?php

namespace ProcessWire;

/**
 * Backfill internal economics JSON after schema change.
 */

$dryRun = false;

$selector = 'template=order, order_internal_economics_json!=';

foreach ($pages->find($selector) as $order) {
  /** @var OrderPage $order */
  // … backfill logic …
  if (!$dryRun) {
    $order->save();
  }
}

$rm->log('Backfill complete');
```

Notes:

- ProcessWire is already bootstrapped — do **not** `require` `index.php`.
- Use `$rm->log()` for output that should appear in the RockMigrations lastrun log.
- Implement your own `$dryRun` flag while developing locally if needed.
- Prefer idempotent logic or guard clauses where possible; a retry after a partial failure may re-process early items (same as inline `once()` — see [Errors](#errors)).

### When scripts run

Once folder scripts are the **last step** of a migrate run:

1. Config migrations (fields, templates, roles, permissions)
2. Config migration lifecycle hooks (`beforeAssets`, `afterAssets`, `beforeData`, `afterData`)
3. Other watchlist / module migration files
4. `migrationsDone()`
5. **Once folder scripts** (pending only, filename order)

Pending once scripts count as migration work: RockMigrations runs `migrate()` even when no other watchlist files changed, so a newly deployed script executes on the next modules refresh or `migrate.php` CLI run on staging and production.

### Tracking and history

- **Tracking key:** path relative to the site root, e.g. `RockMigrations/once/2026-06-30-backfill.php` or `modules/Site/RockMigrations/once/2026-06-30-fix.php`.
- **Storage:** RockMigrations module config (`once` key), same store as inline `$rm->once()` callbacks.
- **Admin UI:** **Setup → RockMigrations → Once History** — view executed keys, clear one or all to force re-run.

### Errors

If a once script throws an exception, it is **not** marked as completed. The error is logged to the lastrun log; the script retries on the next migrate run.

If several scripts are pending, RockMigrations runs **all** of them in one pass (filename order). A failure in one script does not block others; only the failed script stays unmarked.

Use `try/catch` inside heavy backfills to avoid duplicate work on partial progress — same guidance as inline callbacks below.

### Caution

- **Never rename** a script after it has run — the new filename is treated as a new script and will execute again.
- **Editing** a script after it ran does **not** re-trigger it (tracking is by path/key, not file hash).
- To re-run intentionally, clear the entry in Once History or use the inline callback debug flag during development.

---

## Inline `$rm->once()` callback

You can wrap migration code in the `once()` callback inside any migration file:

```php
$rm->once("once-demo", function() {
  bd('I will be executed only once!');
});
```

Now do a modules refresh to trigger all migrations and you'll see that the dump will only be shown on the very first run!

For debugging purposes, you can force the `once()` callback to execute on every migration by adding a third parameter with the value `true`. This can be particularly useful when developing or testing your migrations. Here's how you can do it:

```php
$rm->once("once-demo", function() {
  bd('I will be executed as long as the third param is true!');
}, true);
```

Inline callbacks run **where they are called** in the migration pipeline (not deferred to the once-folder phase).

### Naming keys

To ensure the uniqueness of the migration key across your entire system, it's crucial to adopt a naming convention that minimizes the risk of duplication. A simple or generic key, such as "revert", could lead to conflicts if another migration uses the same key, resulting in only one of the migrations being executed.

It's recommended to prefix your migration key with the date of creation followed by a descriptive name that clearly indicates the purpose of the migration. This approach significantly reduces the likelihood of key collisions and improves the readability and manageability of your migrations.

### Real world example

Imagine you've previously applied a template context to a field named "coverpic" and now wish to revert this change. Directly removing the template context without caution could lead to unintended consequences, especially if future migrations apply their own context to the same field. To safely revert this change while ensuring that any subsequent modifications remain intact, you can use the `once()` callback. This approach guarantees that the reversion only occurs once, preserving any later migrations' changes.

Here's an example of how to revert the template context for the "coverpic" field using the `once()` callback:

```php
$rm->once(
  "2024-02-11: Remove template context from coverpic",
  function ($rm) {
    $rm->removeTemplateContext("my_template", "coverpic");
  }
);
```

Note: An instance of RockMigrations is passed to the callback for instant use. This allows you to directly use the `$rm` variable within the callback to perform migrations.

### Caution

It's important to note that once a key is used in a `once()` callback, it should never be changed. Modifying the key after it has been set and used will cause the migration to be triggered again, which could lead to unintended consequences or duplicate actions. Always choose your keys wisely and consider them permanent to avoid such issues.

### Errors

It's important to handle errors within the `once()` callback to ensure that migrations are executed correctly. If an error occurs during the execution of the callback, the migration is not marked as completed. This means that upon the next migration attempt, the callback will execute again, potentially leading to duplicate actions or other unintended consequences.

For instance, consider a scenario where you're adding images to each of your 100 pages. If an error occurs while processing the 50th page, the callback execution is halted, and the migration is not marked as done. When the migration is run again, it will attempt to add images to pages 1-50 once more, leading to duplication.

To prevent such issues, it's crucial to implement your own error handling within the `once()` callback. A common approach is to use a `try/catch` block to gracefully handle any errors that occur, allowing you to log the error, perform cleanup if necessary, and prevent the migration from being incorrectly marked as completed.

### Confirm callback

To further enhance the control over the execution of migrations, RockMigrations allows you to define a confirmation callback. This callback serves as a mechanism to confirm whether the execution of the migration was successful. Only if the callback returns `true`, the migration will be marked as completed. This feature is particularly useful in scenarios where you need to ensure that certain conditions are met before considering a migration as successfully done.

Here's an example of how to use a confirmation callback with the `once()` method:

```php
// install process module if it is not installed
$rm->once(
  "11.02.2024: Install RockMigrations Process Module",
  function (RockMigrations $rm) {
    $rm->installModule("ProcessRockMigrations");
  },
  confirm: function () {
    return $this->wire->modules->isInstalled("ProcessRockMigrations");
  },
);
```
