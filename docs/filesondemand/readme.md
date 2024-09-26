# Files on Demand

This feature is typically used in combination with RockShell's `db:pull` command.

Imagine you are responsible for a large project with lots of media files that has been launched a few weeks ago. The client now wants some changes to the markup of the site.

You open your project on your local development machine, but you have an outdated version of the project (both the database and the files are outdated).

Without filesOnDemand you would have to download the whole project, including the database and all files etc.; This is tedious and slow.

With filesOnDemand you only pull the latest database from the remote and RockMigrations will take care of downloading the files that are referenced in the database, but not yet present on your local development machine.

All you have to do to make this work is to add this to your local development config:

```php
$config->filesOnDemand = 'https://your-live-site.com';
```

So when ProcessWire tries to load /site/assets/files/1/foo.jpg it will grab it from https://your-live-site.com/site/assets/files/1/foo.jpg

This is a lot more efficient than downloading the whole project, especially if the live site is large.

## Warning

Make sure to use this only on local development sites!

This feature can slow down your local site if it tries to load files that don't exist on the remote, so use it wisely or turn it off if you don't need it.
