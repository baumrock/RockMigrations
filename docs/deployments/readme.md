# Deployments

You can use RockMigrations to create fully automated CI/CD pipelines for Github/Gitlab.

The resulting folder structure will look like this (where the triple letters stand for a release hash from github):

```php
current -> release-DDD   // symlink to latest release
release-AAA---           // old releases
release-BBB--
release-CCC-
release-DDD              // latest release
shared                   // shared folder
```

- You can define the number of releases in the file `/site/deploy.php` (see code below).
- The `current` symlink will link to the latest release by default
- The `shared` folder will contain the persistent data shared across all releases (like `site/assets/files` and `site/config-local.php`).

If a deployment goes wrong or you encounter bugs you can manually update the symlink to the latest working version and your site or app will instantly be up and running again. RockMigrations will also try to create a DB dump before deploying the new version. Instructions for both features are in the deployment log:

![image](https://user-images.githubusercontent.com/8488586/216834563-d1ff4cc1-726d-4c8e-ac34-b90839fbf5e6.png)

# Setup

- Setup SSH keys and add secrets to your repository
- Create workflow yaml file
- Push to your repo

## Setup SSH keys and add secrets to your repo

To use this workflow you need to set the referenced secrets in your git repo.

Create a keypair for your deploy workflow. Note that we are using a custom name `id_rockmigrations` instead of the default `id_rsa` to ensure that we do not overwrite an existing key. If you are using RockMigrations on multiple projects you can simply overwrite the key as you will only need it once during setup:

```sh
ssh-keygen -t rsa -b 4096 -f ~/.ssh/id_rockmigrations -C "rockmigrations-[project]"
```

Copy content of the private key to your git secret `SSH_KEY`:

```sh
cat ~/.ssh/id_rockmigrations
```

Copy content of keyscan to your git secret `KNOWN_HOSTS`

```sh
ssh-keyscan your.server.com
```

Add the public key to your remote user:

```sh
ssh-copy-id -i ~/.ssh/id_rockmigrations user@your.server.com
```

Or copy the content of the public key into the authorized_keys file

```sh
cat ~/.ssh/id_rockmigrations.pub
```

Try to ssh into your server without using a password:

```sh
ssh -i ~/.ssh/id_rockmigrations user@your.server.com
```

## Create the workflow yaml

Now create the following yaml file in your repo:

```yaml
# code .github/workflows/deploy.yaml
name: Deploy via RockMigrations

# Specify when this workflow will run.
# Change the branch according to your setup!
# The example will run on all pushes to main and dev branch.
on:
  push:
    branches:
      - main
      - dev

jobs:
  test-ssh:
    uses: baumrock/RockMigrations/.github/workflows/test-ssh.yaml@main
    with:
      SSH_HOST: your.server.com
      SSH_USER: youruser
    secrets:
      SSH_KEY: ${{ secrets.SSH_KEY }}
      KNOWN_HOSTS: ${{ secrets.KNOWN_HOSTS }}
```

Commit the change and push to your repo. You should see the workflow showing up in Github's Actions tab:

![img](https://i.imgur.com/JFvMqkE.png)

Once you got your SSH connection up and running you can setup the deployment. Remove or comment the job "test" and uncomment or add the job "deploy" to your `deploy.yaml`:

`label: /.github/workflows/deploy.yaml`
```yaml
name: Deploy via RockMigrations

on:
  push:
    branches:
      - main

jobs:
  deploy-top-production:
    uses: baumrock/RockMigrations/.github/workflows/deploy.yaml@main
    with:
      # specify paths for deployment as JSON
      # syntax: branch => path
      # use paths without trailing slash!
      PATH: "/path/to/your/production/webroot"
      SSH_HOST: your.server.com
      SSH_USER: youruser
    secrets:
      SSH_KEY: ${{ secrets.SSH_KEY }}
      KNOWN_HOSTS: ${{ secrets.KNOWN_HOSTS }}
```

If you are using submodules just set the `SUBMODULES` input variable and add a `CI_TOKEN` to your repo secrets:

`label: /.github/workflows/deploy.yaml`
```yaml
name: Deploy via RockMigrations

on:
  push:
    branches:
      - main

jobs:
  deploy-top-production:
    uses: baumrock/RockMigrations/.github/workflows/deploy.yaml@main
    with:
      # specify paths for deployment as JSON
      # syntax: branch => path
      # use paths without trailing slash!
      PATH: "/path/to/your/production/webroot"
      SSH_HOST: your.server.com
      SSH_USER: youruser
      SUBMODULES: true
    secrets:
      CI_TOKEN: ${{ secrets.CI_TOKEN }}
      SSH_KEY: ${{ secrets.SSH_KEY }}
      KNOWN_HOSTS: ${{ secrets.KNOWN_HOSTS }}
```

See https://bit.ly/3ru8a7e how to setup a Personal Access Token for Github. You need to create this token only once for your Github Account, not for every project, but you need to add it to every project that should be able to access private submodules!

Your workflow should copy files but fail at step `Trigger RockMigrations Deployment`. That is because you need to create a `site/deploy.php` file:

`label: /site/deploy.php`
```php
<?php

namespace RockMigrations;

require_once __DIR__."/modules/RockMigrations/classes/Deployment.php";
$deploy = new Deployment($argv, "/path/to/your/deployments");

// custom settings go here

$deploy->run();
```

Note that you must set a path as second argument when creating a new instance of `Deployment`. This path ensures that if you run your deployment script on another machine (for example on a local DDEV environment) it will run "dry" and will not execute any commands. This only works if your local path is different from your remote path of course!

This is how it looks like if everything worked well:

![img](https://i.imgur.com/hSML6Ym.png)

## Debugging

Debugging can be hard when using CI/CD pipelines. If you get unexpected results during the PHP deployment you can make the script more verbose like this:

```php
...
$deploy->verbose();
$deploy->run();
```

## Add Translations to GIT

In this example we will use the german language pack for our default language. We want to add all translations to GIT so that we can upload new translations on local DEV and then simply push to staging/production.

## Create Translations

You can either do that manually or by using RockMigrations:

```php
// install german language pack for the default language
// this will install language support, download the ZIP and install it
$rm->setLanguageTranslations('DE');
```

In our example that created the language with id `1025` (we will use this id for all following examples.

Then make sure that the content of this folder is added to your GIT repo:

```
# .gitignore
# exclude all files
/site/assets/files/*
# dont ignore files of given page (eg language files)
!/site/assets/files/1025
```

Then add those files to your repo and commit - this should look something like this:

<img src=https://i.imgur.com/irQLI4S.png height=300>

Now we just need to push this folder to the shared folder on deployment:

`label: /site/deploy.php`
```php
// push german translations to staging/production
$deploy->push('/site/assets/files/1025');
```

## Integrations

VSCode has a "github actions" extension that can help you create workflows or inspect workflow runs:

<img src=vscode.png class=blur>
