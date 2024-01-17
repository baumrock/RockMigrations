# Magic Pages

## Introduction

If you are not using custom page classes yet I highly recommend to start using them now! Without them every page is a "stupid" page object, but when using custom page classes you add so much more logic to your code and suddenly every Page with template "event" is an EventPage and every Page with template "newsitem" is a NewsitemPage. You avoid hook-hell and your IDE can assist you because it suddenly understands your code!

## Usage

Simply add the `use RockMigrations\MagicPage;` statement to your custom page class:

```php
<?php

namespace ProcessWire;

class DemoPage extends Page {
  use RockMigrations\MagicPage;
}
```

## init() and ready()

ProcessWire does not automatically trigger `init()` and `ready()` methods of custom pageclasses. This means that by default, you cannot attach hooks directly in custom page classes and you need to add hooks that are related to your pageclass either in `/site/ready.php` or in a custom module.

That leads to logically connected code being spread over multiple files.

 However, with RockMigrations, you can enhance your page classes and attach hooks directly within the page class file itself. This not only organizes your code better but also makes it more intuitive.

Here's how you can do it:

```php
<?php

namespace ProcessWire;

use RockMigrations\MagicPage;

class DemoPage extends Page {
  use MagicPage;

  public function init() {
    // Your init code here
  }

  public function ready() {
    // Your ready code here
  }
}
```

In the above example, `init()` and `ready()` methods are defined within the `DemoPage` page class. Any hooks that are related to this page class can be written inside these methods, instead of writing them in `/site/ready.php`. This makes your code cleaner and easier to manage.

Note that `init()` and `ready()` will only be triggered once for every pageclass, not once for every existing page having this pageclass. Behind the scenes, RockMigrations will create one runtime page for every magic pageclass. That means that inside these `init()` and `ready()` methods you will have access to `$this`, but `$this->id` will always be zero.

This is necessary to make sure that everything defined in `init()` and `ready()` is executed even if no page of your pageclass exists yet. For example, you could create a blog module that creates a `BlogOverview` and a `BlogItem` pageclass and `BlogItem::init()` and `BlogItem::ready()` must be called even if no blog posts exist yet.

## Magic Methods

When customizing the page editing experience for custom page classes you often have to hook into several aspects of your application. For example you might want to hook ProcessPageEdit::buildForm or you might want to hook Pages::saveReady.

A regular hook in /site/ready.php could look like this:

```php
$wire->addHookAfter('ProcessPageEdit::buildForm', function(HookEvent $event) {
    $form = $event->return;
    $page = $event->object->getPage();
    if($page->template != 'newsitem') return;
    // Your code here
});
```

When using MagicPages you can just add an `editForm()` method to your pageclass:

```php
public function editForm($form) {
  // your code here
}
```

That's all! That `editForm` method will only be executed for pages that have a matching template.

The full code could look like this:

```php
<?php

namespace ProcessWire;

use RockMigrations\MagicPage;

class NewsitemPage extends Page {
  use MagicPage;

  public function editForm($form) {
    // your code here
  }
}
```

That means you write less code and your code will also be better organised!

### Available Magic Methods

- `editForm`: This method is called after the `ProcessPageEdit::buildForm` hook. It allows you to modify the form that is used to edit the page.
- `editFormContent`: This method is called after the `ProcessPageEdit::buildFormContent` hook. It allows you to modify the content of the form that is used to edit the page.
- `editFormSettings`: This method is called after the `ProcessPageEdit::buildFormSettings` hook. It allows you to modify the settings of the form that is used to edit the page.
- `onAdded`: This method is called after the `Pages::added` hook when the page ID is 0. It is executed when a new page is added.
- `onChanged`: This method is called after the `Page::changed` hook. It is executed when a field value on the page is changed.
- `onCreate`: This method is called after the `Pages::saveReady` hook when the page ID is 0. It is executed when a new page is created.
- `onProcessInput`: This method is called after the `InputfieldForm::processInput` hook. It is executed when the form input is processed.
- `onSaved`: This method is called after the `Pages::saved` hook. It is executed every time the page is saved.
- `onSaveReady`: This method is called after the `Pages::saveReady` hook. It is executed every time the page is ready to be saved.
- `onTrashed`: This method is called after the `Pages::trashed` hook. It is executed when a page is trashed.
- `pageListLabel`: This method is called after the `ProcessPageListRender::getPageLabel` hook. It allows you to modify the label of the page in the page list.
- `setPageName`: This method is called after the `Pages::saved(id>0)` hook. It allows you to set the page name from a callback.

See MagicPages.module.php method `addMagicMethods` for details.

## Magic Assets

If your page is a MagicPage it will load YourPage.css and YourPage.js files automatically in the PW backend when editing any page of type YourPage.

Example:
```php
// /site/classes/HomePage.php

// /site/classes/HomePage.css
div { outline: 2px solid red; }

// /site/classes/HomePage.js
alert('You are editing the HomePage');
```
