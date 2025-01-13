# Deployments Advanced

## Customising Deployments

You can customise the deployment process by creating a custom `deploy.php` file in your site directory. This file will be executed instead of the default `deploy.php` file in the `site/modules/RockMigrations` folder.

A good example are translations. ProcessWire stores translations in `/site/assets/files/{languageID}/...`. When having multiple environments (dev/staging/production), where do you translate your translatable strings?

I recommend to handle translations just like code features. This means we work on them on development and then push them to staging or production.

Now we have a problem, because usually during deployments we exclude `/site/assets/files` from the deployment process to not lose any user data. How to solve this?

We can tell RockMigrations to `push` certain directories during the deployment process, which will make sure that if we change translations on development, they are also pushed to staging and production:

```php
<?php

namespace RockMigrations;

$deploy = new Deployment($argv ?? []);

$deploy->push("site/assets/files/1030"); // german translations
$deploy->push("site/assets/files/1031"); // english translations

$deploy->run();
```

This will tell RockMigrations to grab those folders from the checked out repository and push them to the remote server, overwriting any existing files.
