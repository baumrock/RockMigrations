# Once

<img src=screenshot.png class=blur>

You can make sure that some migrations are only ever executed once on your system by wrapping the migration in the `once()` callback:

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

## Naming Keys

To ensure the uniqueness of the migration key across your entire system, it's crucial to adopt a naming convention that minimizes the risk of duplication. A simple or generic key, such as "revert", could lead to conflicts if another migration uses the same key, resulting in only one of the migrations being executed.

It's recommended to prefix your migration key with the date of creation followed by a descriptive name that clearly indicates the purpose of the migration. This approach significantly reduces the likelihood of key collisions and improves the readability and manageability of your migrations.

## Real World Example

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

## Caution

It's important to note that once a key is used in a `once()` callback, it should never be changed. Modifying the key after it has been set and used will cause the migration to be triggered again, which could lead to unintended consequences or duplicate actions. Always choose your keys wisely and consider them permanent to avoid such issues.

## Errors

It's important to handle errors within the `once()` callback to ensure that migrations are executed correctly. If an error occurs during the execution of the callback, the migration is not marked as completed. This means that upon the next migration attempt, the callback will execute again, potentially leading to duplicate actions or other unintended consequences.

For instance, consider a scenario where you're adding images to each of your 100 pages. If an error occurs while processing the 50th page, the callback execution is halted, and the migration is not marked as done. When the migration is run again, it will attempt to add images to pages 1-50 once more, leading to duplication.

To prevent such issues, it's crucial to implement your own error handling within the `once()` callback. A common approach is to use a `try/catch` block to gracefully handle any errors that occur, allowing you to log the error, perform cleanup if necessary, and prevent the migration from being incorrectly marked as completed.

## Confirm Callback

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
