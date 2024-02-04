# Development Helpers

## Automatic Asset Minification

Frontend bundlers can be complex and time-consuming. That's why I prefer to use RockMigrations for this task.

Let's consider a scenario where you're developing a new module and you want to include some JavaScript. Assume the JavaScript file is located at /site/modules/MyModule/myscript.js

```php
// In an autoload module, you can place this in the init() method
$rm = rockmigrations();
$rm->minify("/path/to/MyModule/myscript.js");
```

This action will produce a compressed version of the file, named myscript.min.js, which can be loaded wherever required. You can continue developing within myscript.js and a compressed version will be regenerated on every page load - but only if the file has been modified and only if you are a superuser, to avoid unnecessary overhead in production.

## Prevent Publish

```php
// prevent publishing of pages with id 1010 and 1012
rockmigrations()->preventPublish("id=1010|1012");

// optionally allow publishing for superusers
rockmigrations()->preventPublish("id=1010|1012", true);
```

<img src=https://i.imgur.com/Z8GIdvS.png class=blur>
