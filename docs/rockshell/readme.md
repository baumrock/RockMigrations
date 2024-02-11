# RockShell Integration

You can load RockShell or any registered RockShell command directly from RockMigrations.

## Use Case

Imagine you are developing a command for RockShell. While developing you want to quickly iterate and see if what you are coding is actually working, right? You could code, then fire the command in the console, then code, then fire the command again...

That's not fun and you lose all the great dumping and debugging that you get from TracyDebugger.

As of now there is a better way!

## Loading Commands in site/ready.php

Let's say we had this command in `/site/modules/Site/RockShell/Commands/SiteDoSomething.php`:

```php
<?php

namespace Site;

use RockShell\Command;

class SiteDoSomething extends Command
{
  public function handle()
  {
    $this->doSomething();
    return self::SUCCESS;
  }

  public function doSomething(): void
  {
    // do something

    // log when not in CLI
    if(!$this->isCLI()) bd("I'm doing something!");
  }
}
```

While developing you could add this to `ready.php` and when using LiveReload you'll get results whenever you save your file without ever leaving your IDE!

```php
rockmigrations()
  ->rockshell()
  ->get("site:do:something")
  ->doSomething();
```

Once you are done and everything works as expected you can simply execute your command from the command line!
