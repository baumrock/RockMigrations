# Deployments Fastlane

<div class='uk-alert uk-alert-warning'>This guide is a fastlane guide without explanations. If you are new to Github Actions or RockMigrations you might want to read the dedicated guides first.</div>

## GOAL

I understand that this topic might look overwhelming. But I promise you that it will open up a whole new world of possibilities for you:

- Deploy with a simple `git push`
- Deploy to multiple environments (production, staging)
- Get the latest content as easy as `rockshell db:pull`
- Work on a PW project in a team

To name just a few things. Let's get started!

## Agenda

Setting up Deployment with RockMigrations is a 3 step process:

1. Connect to the server
2. Deploy Manually
3. Deploy via Github Actions

## Prerequisites

When following this guide you will need:

- A ProcessWire project running on your local development machine
  - I recommend starting with the blank profile
  - RockMigrations installed
  - RockShell installed
  - Split config.php
- A server that allows SSH connections
- A Github account

### Split config.php

We need different config files for different environments. For that we will split the `config.php` file into two files:

- `config.php` will hold all global settings
- `config-local.php` will hold environment specific settings.

<div class='uk-alert uk-alert-warning'>Make sure to add `config-local.php` to the `.gitignore` file!</div>

## Connect to the server

### Create the SSH key

Create a project-specific SSH key pair called `id_github` that will be used for connecting to the server:

`label: LOCAL`
```bash
ssh-keygen -t ed25519 -f ~/.ssh/id_rockmigrations -C "RM Deployment Key"
```

You will see the key's random image if it was created successfully.

### Add the SSH key to the server

First, let's copy the public key to the clipboard:

`label: LOCAL`
```bash
cat ~/.ssh/id_rockmigrations.pub
```

Next, we copy the public key to the remote server:

`label: REMOTE`
```bash
nano ~/.ssh/authorized_keys
```

Paste the public key to the end of the file and save it:

`label: REMOTE`
```bash
# DEMOPROJECT deployment key
ssh-ed25519 XXXX RM Deployment Key
```

### Check connection

`label: LOCAL`
```bash
ssh -i ~/.ssh/id_rockmigrations DEMOUSER@DEMOSERVER
```

<div class='uk-alert uk-alert-warning'>Make sure this works before you continue!</div>

## Deploy Manually

### Setup Server (Vhost + DB)

Use your webhosting panel to setup a vhost and database for your project!

### Copy files (rsync)

`label: LOCAL`
```bash
# !!!!! MAKE SURE YOU ARE IN THE PROJECT ROOT !!!!!
cd /path/to/your/project

# you can add additional excludes as needed
rsync -avz -e "ssh -i ~/.ssh/id_rockmigrations" \
  --exclude='.ddev' \
  --exclude='.git' \
  --exclude='.vscode' \
  ./ DEMOUSER@DEMOSERVER:/path/to/your/documentroot/
```

You might need to fix file permissions on the server:

`label: REMOTE`
```bash
cd /path/to/your/documentroot/
find . -type d -exec chmod 755 {} \;
find . -type f -exec chmod 644 {} \;
```

### Check website

Now visit the website in your browser. Check the result:

- Internal Server Error (500) --> Check the error log
- Error: Exception: SQLSTATE[HY000] [2002] ... --> Great! ProcessWire complains about having no connection to the database, which means that ProcessWire works, but the database does not exist.

### Create & Import the Database

First, create the database via your webhosting panel.

Then, import the database via your webhosting panel or use the following command:

`label: REMOTE`
```bash
cd /path/to/your/documentroot/
mysql your_database_name < site/assets/backups/database/db.sql
```

### Update config.php

Create a new userAuthSalt (change the length at the end to your liking):

`label: REMOTE`
```bash
openssl rand -base64 40
```

Then copy it to the `config-local.php` file and also set DB credentials there:

`label: REMOTE`
```php
$config->dbHost = 'localhost';
$config->dbName = 'your_db_name';
$config->dbUser = 'your_db_user';
$config->dbPass = 'c8kCbEBYM3t1VQ==';

$config->userAuthSalt = 'IHeIVPuu9LARrXG4L/6nfslYzCRoFIbFdkiwy5JWbTGVqkTV8ClBmw==';
```

### Check website

<div class='uk-alert uk-alert-warning'>Make sure your website works before you continue!</div>

### Reset User + Password

We changed the userAuthSalt, so no user can log in anymore. Using RockShell you can reset the namem and password of any user easily:

`label: REMOTE`
```bash
php RockShell/rock user:reset
```

Now you should be able to login to your website on the remote server.

## Deploy via Github Actions

### Prepare the server

Transform folder structure using RockShell:

`label: REMOTE`
```bash
./RockShell rm:transform
```

### Update your vhost / document root

If you now open your website you will get a 404 error! Update the document root in your webhosting panel from this:

- from `/path/to/your/documentroot/`
- to `/path/to/your/documentroot/current/`

Your site should now work again!

### Add Github Actions Workflow

Add this to your local project, but DO NOT PUSH IT YET!

I like to name that file `main.yaml` as it is the workflow for the `main` branch, but you can name it anything you want.

`label: LOCAL .github/workflows/main.yaml`
```yaml
name: Deploy

on:
  push:
    branches:
      - main

jobs:
  deploy:
    # @main will use the latest version (main branch)
    # @v6.5.0 will use version 6.5.0
    uses: baumrock/RockMigrations/.github/workflows/deploy.yaml@main
    with:
      # document root (without /current and no trailing slash)
      PATH: ${{ vars.DEPLOY_PATH }}
      SSH_HOST: ${{ vars.SSH_HOST }}
      SSH_USER: ${{ vars.SSH_USER }}
      # SUBMODULES: true
      # PHP_COMMAND: "php81"
    secrets:
      SSH_KEY: ${{ secrets.SSH_KEY }}
      CI_TOKEN: ${{ secrets.CI_TOKEN }}
      KNOWN_HOSTS: ${{ secrets.KNOWN_HOSTS }}
```

### Update Repository

To make the workflow work, we need to add the following secrets and variables to your Github repository:

Secrets:

- SSH_KEY
- CI_TOKEN
- KNOWN_HOSTS

Variables:

- SSH_HOST
- SSH_USER
- DEPLOY_PATH

**SSH_KEY**

Copy the private key to Github:

`label: LOCAL`
```bash
cat ~/.ssh/id_rockmigrations
```

**CI_TOKEN**

Copy your PAT token to Github so that the runner can access your private repositories.

**KNOWN_HOSTS**

Copy the server fingerprint to Github:

`label: LOCAL`
```bash
ssh-keyscan YOURSERVER
```

**SSH_HOST**

Copy the server hostname to Github, for example `example.baumrock.com`.

**SSH_USER**

Copy the username to Github, for example `youruser`.

**DEPLOY_PATH**

Copy the production path to Github, for example:

`/path/to/your/documentroot`

NOTE: Do NOT add the /current folder to the path! It will be added automatically. Also do not add a trailing slash.

### Check deployment

Push changes and monitor Github Actions workflow for any errors.

### Update DEV config

`label: LOCAL /site/config-local.php`
```php
# enable filesOnDemand feature
$config->filesOnDemand = 'https://your-live.site/';

# add rockshell config (to use db:pull)
$config->rockshell = [
  // 'remotePHP' => 'php81',
  'remotes' => [
    'production' => [
      'ssh' => 'DEMOUSER@DEMOSERVER',
      'dir' => '/path/to/your/documentroot/current',
    ],
  ],
];
```

> Congrats! You are now ready to rock your deployments like never before!
