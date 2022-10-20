# RockMigrationsApi

RockMigrations can easily be extended via custom API classes. Simply put your API extension file in `/site/modules/YourModule/RockMigrationsApi/Yourfile.php` and it will automatically get loaded and added to the `$rockmigrations` API.

## Example API Extension

```php
<?php
namespace RockMigrationsApi;
use ProcessWire\RockMigrations;
class Ping extends RockMigrations
{
  public function ping(): string
  {
    return "pong!";
  }
}
```

Important notes:

- The classname must be equal to the filename
- The namespace must be `RockMigrationsApi`

## RockMigrations Properties

API extensions are their very own classes extending RockMigrations. While they share all the methods via the `$this` variable you can't directly access/modify properties of the RockMigrations base class via `$this->foo = 'foo'`.

But you can get the base class instance easily via `$this->rm()` so the correct syntax to set the `foo` property would be: `$this->rm()->foo = 'foo'`.
