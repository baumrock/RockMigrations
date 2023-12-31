# pageListLabel

Any custom pageclass in ProcessWire can define the `getPageListLabel()` method to define a custom page list label for the backend.

Great! But that method has a problem: If you define custom markup, that markup will also be added to the main menu and to ASM select fields etc.

The solution: Just make your page a `MagicPage` and add the `pageListLabel()` method instead:

```php
<?php

namespace ProcessWire;

use RockMigrations\MagicPage;

class YourPage extends Page {
  use MagicPage;

  public function pageListLabel() {
    return "<span>foo</span> bar";
  }
}
```
