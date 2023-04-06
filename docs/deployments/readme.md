# Deployments

You can use RockMigrations to create fully automated CI/CD pipelines for Github/Gitlab.

## Introduction

The idea is to replace a workflow that depends on several manual steps with an automated workflow where all you have to do manually is `git push`:

<img src=flow.drawio.svg class=blur alt="Deployment Concept">

This does not only have the benefit of eliminating one manual step on every deployment, you will also get a lot of magic from RockMigrations by default. For example RockMigrations will keep several old releases where you can simply revert to if anything should go wrong. RockMigrations will also create a database backup before deployment.

If you did all those steps manually on every deployment they'd add up. And we know what would happen in reality: We'd just not do it and one day we'd find ourselves in trouble without a quick way out!

**Save yourself from the hassle and use RockMigrations Deployment instead!**

The resulting folder structure of a deployment will look like this (where the triple letters stand for a release hash from github):

```php
current -> release-DDD   // symlink to latest release
release-AAA---           // old releases
release-BBB--
release-CCC-
release-DDD              // latest release
shared                   // shared folder
```

- The `current` symlink will link to the latest release
- Releases will be kept in the `release-*` folders
- The `shared` folder will contain the persistent data shared across all releases (like `site/assets/files` and `site/config-local.php`).

## Workflow Log

RockMigrations will create a nice and helpful log on every run:

<img src=log.png class=blur alt="Github Workflow Log">

### Setup Variables

This section will list the most important settings of your deployment:

`label: Variables Log`
```sh
BRANCH: main
PATH: /path/to/your/folder
SSH_USER: youruser
SSH_HOST: yourhost.com
SUBMODULES: true
```

### Deploy via RSYNC

This section will list all files that have been copied from the checked out release to your server.

### Trigger RockMigrations Deployment

This section lists the log that is produced by the RockMigrations Deployment from the `\RockMigrations\Deployment` class which is triggered by the invokation of `/site/deploy.php` after all files have been copied via rsync.

The log is quite long and verbose so everything should be clear from reading that log.

## Setup

### Add the /site/deploy.php file

The first thing we need to do is to create the PHP file that is triggered at the end of the rsync:

`label: /site/deploy.php`
```php
<?php

namespace RockMigrations;

require_once __DIR__ . "/modules/RockMigrations/classes/Deployment.php";
$deploy = new Deployment($argv);

// custom settings go here
// see docs about "Customising the Deployment"

$deploy->run();
```

For the first deployment you can copy and paste this file as it is!

### Setup SSH Keys

Github needs to be able to copy files to your remote server. That's why we need to setup SSH keys that we store in the Github Repo's secrets.

<div class="uk-alert uk-alert-warning">Note that we will create an SSH key with the custom name "id_rockmigrations" instead of the default "id_rsa" to ensure that we do not overwrite an existing key.</div>
<div class="uk-alert uk-alert-danger">Don't use the "id_rsa" key for RockMigration Deployments!</div>

To create the key use the following command and replace `[project]` with a unique and explanatory name:

```sh
ssh-keygen -t rsa -b 4096 -f ~/.ssh/id_rockmigrations -C "rockmigrations-[project]"
```

If you are using RockMigrations on multiple projects you can simply overwrite the key on the next deployment setup as you will only need it once during setup. You can also remove that key from your system after you have sucessfully setup your deployment.

### Add Secrets to your Repo

Copy the content of the private key to your git secret `SSH_KEY`:

```sh
cat ~/.ssh/id_rockmigrations
```

Copy the content of keyscan to your git secret `KNOWN_HOSTS`

```sh
ssh-keyscan your.server.com
```

Add the public key to your remote user:

```sh
ssh-copy-id -i ~/.ssh/id_rockmigrations user@your.server.com
```

Or copy and paste the content of the public key into the `~/.ssh/authorized_keys` file of your remote server. To get the content of the public key you can use this command:

```sh
cat ~/.ssh/id_rockmigrations.pub
```

Try to ssh into your server without using a password:

```sh
ssh -i ~/.ssh/id_rockmigrations user@your.server.com
```

<div class="uk-alert uk-alert-warning">The final step must work in order to make the whole deployment work! If the server does not let you connect with the `id_rockmigrations` key then github will not be able to push files to your server!</div>

### Optional: Create the test-ssh workflow

If you are new to RockMigrations Deployment I recommend an additional step to check if the SSH connection between Github and your server works. This workflow will not copy any files and will therefore be a lot faster. This makes debugging easier.

`label: .github/workflows/deploy.yaml`
```yaml
name: Deploy via RockMigrations

# run this test-workflow on every push to every branch
on:
  push

jobs:
  test-ssh:
    uses: baumrock/RockMigrations/.github/workflows/test-ssh.yaml@main
    with:
      SSH_USER: youruser
      SSH_HOST: your.server.com
    secrets:
      SSH_KEY: ${{ secrets.SSH_KEY }}
      KNOWN_HOSTS: ${{ secrets.KNOWN_HOSTS }}
```

Commit the change and push to your repo. You should see the workflow showing up in Github's Actions tab:

<img src=actions.png class=blur alt="Github Actions Tab">

### Create the final workflow file

Once you got your SSH connection up and running you can setup the deployment.

I always create a workflow file for every branch: `main.yaml` that fires on pushes to the `main` branch and `dev.yaml` that fires when I push to `dev`:

`label: /.github/workflows/main.yaml`
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
      PATH: "/path/to/www.yoursite.com"
      SSH_USER: youruser
      SSH_HOST: your.server.com
    secrets:
      SSH_KEY: ${{ secrets.SSH_KEY }}
      KNOWN_HOSTS: ${{ secrets.KNOWN_HOSTS }}
```

### Optional: Using Submodules

If you are using submodules just set the `SUBMODULES` input variable to `true` and add a `CI_TOKEN` to your repo secrets:

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
      PATH: "/path/to/www.yoursite.com"
      SSH_USER: youruser
      SSH_HOST: your.server.com
      SUBMODULES: true
    secrets:
      CI_TOKEN: ${{ secrets.CI_TOKEN }}
      SSH_KEY: ${{ secrets.SSH_KEY }}
      KNOWN_HOSTS: ${{ secrets.KNOWN_HOSTS }}
```

See [here](https://docs.github.com/en/authentication/keeping-your-account-and-data-secure/creating-a-personal-access-token) how to setup a Personal Access Token for Github. You need to create this token only once for your Github Account, not for every project. But you need to add it to every project that should be able to access your private submodules!

### First Deployment and Cleanup

If everything worked well you should see a success icon in your Github's `Actions` tab.

You can now remove the SSH keypair `id_rockmigrations` and `id_rockmigrations.pub` from your local system if you want. The private key is stored in your Github's Repository Secrets and the public key is stored on the remote server that accepts connections from your Github action.

### Setting up the shared folder

RockMigrations will create the shared folder for you on the first deployment, but it will be empty. To make it useful you need to add at least these two things:

- Add all necessary config settings in `/site/config-local.php`
- Upload all files to `/site/assets/files`

You only need to do this during setup. Once setup it will just work for all following deployments!

## Customising the Deployment

### share()

The share method tells RockMigrations that the given file or folder should be symlinked from the shared folder. A good example is the `/site/assets/files` folder that is not part of the Github repository but needs to exist in every release.

`share()` tells RockMigrations that it should create a symlink to that folder in the `shared` directory. You need to upload/create that folder or file yourself. Another example is the file `/site/config-local.php` which is also a shared file used by all releases but never touched on deploy.

### delete()

You can tell RockMigrations to delete files or folders after deployment. By default it will remove several folders:

```sh
/.ddev
/.git
/.github
/site/assets/cache
/site/assets/ProCache
/site/assets/pwpc-*
/site/assets/sessions
```

As you can see every deployment will wipe the cache and ProCache folder which will make sure that you don't serve outdated versions of your site!

## Debugging

Debugging can be hard when using CI/CD pipelines. If you get unexpected results during the PHP deployment you can make the script more verbose like this:

`label: /site/deploy.php`
```php
...
$deploy->verbose();
$deploy->run();
```

You can also make it run in dry mode where no files will be copied and only the list of to be executed commands will be shown:

```php
...
$deploy->dry();
$deploy->run();
```

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
