# Deployments

Imagine this: You've just made some critical updates to your ProcessWire site. Maybe it's an urgent bug fix or an exciting new feature your client has been waiting for. But now comes the stressful part - deploying those changes to production.

We've all been there:
- Manually uploading files and hoping we didn't miss anything
- Wrestling with file permissions after each upload
- Trying to keep track of which files need to be excluded
- Wondering if we remembered to update the database
- And that moment of fear when we finally hit refresh on the production site

What if I told you there's a better way? A way to deploy your ProcessWire sites with just a single git push, knowing that everything - files, database, permissions - will be handled automatically and correctly every single time?

In this guide, I'll show you how to set up professional deployments using RockMigrations and GitHub Actions. By the end, you'll have a robust deployment pipeline that:
- Makes deployments as simple as pushing to Git
- Automatically handles file permissions
- Manages your database migrations
- Works perfectly for both solo developers and teams
- And most importantly - gives you peace of mind with every single deployment

Let's transform your deployment process from a source of stress into a competitive advantage!

## Outline

Setting up Deployment with RockMigrations is a 3 step process:
1. Setting up passwordless authentication using SSH
2. Deploy Manually (ensures everything works)
3. Deploy via Github Actions (to save you time and hassle)

## Prerequisites

When following this guide you will need:

- A ProcessWire project running on your local development machine
  - I recommend starting with the blank profile
  - RockMigrations installed
  - RockShell installed
- A server that allows
  - SSH connections
  - Symlinks
- A Github account

## Passwordless Authentication (SSH)

We need two different SSH keys for our setup:

1. A personal key for development
   - Used by you for all your projects
   - Stays on your computer
   - Never shared with others
   - Example: `~/.ssh/id_bernhard`

2. A project key for deployments
   - Specific to this project
   - Stored in the Github repository
   - Used by Github Actions
   - Shared with team members
   - Example: `~/.ssh/id_github`

> Pro-Tip: I'm using a generic name `id_github` for the project-key on every project. I simply overwrite it for each project, because then I do not bloat the `.ssh` folder with too many keys that are never used (because they are moved to the Github repo and only used by the Github runner).

### Create Personal SSH Key

First, create your personal key if you don't have one yet:

`label: LOCAL`
```bash
ssh-keygen -t ed25519 -f ~/.ssh/id_ed25519 -C "your@email.com"
```

### Create Project SSH Key

Then create a project-specific key for automated deployments:

`label: LOCAL`
```bash
ssh-keygen -t ed25519 -f ~/.ssh/id_rockmigrations -C "RM Deployment Key"
```

### Add Keys to Server

Add both public keys to your server's authorized_keys file. To get the public key, use the following commands on your local machine:

`label: LOCAL`
```bash
cat ~/.ssh/id_ed25519.pub
cat ~/.ssh/id_rockmigrations.pub
```

Then add the keys to your server's `~/.ssh/authorized_keys` file, for example using nano:

`label: REMOTE`
```bash
nano ~/.ssh/authorized_keys
```

Then add this to the end of the file:

```bash
# Bernhard's Personal Key
ssh-ed25519 XXXX your@email.com
# Github Deployment Key
ssh-ed25519 YYYY RM Deployment Key
```

### Test Connection

Test both connections to make sure they work:

`label: LOCAL`
```bash
# Test personal key
ssh -i ~/.ssh/id_ed25519 user@server

# Test project key
ssh -i ~/.ssh/id_rockmigrations user@server
```

<div class='uk-alert uk-alert-warning'>Make sure both connections work before you continue, otherwise you will be in trouble later!</div>

## Deploy Manually

### Split config.php

Our remote instance of ProcessWire needs different database credentials than our local development instance. Some are using `if ... else ...` in `config.php` relying on the hostname or such, but this is prone to errors! Don't do that!

You don't believe me? Well, think about me when you first try to use ProcessWire bootstrapped from the command line. You don't have a hostname there, so your config will not work! I told you!

So let's avoid this by using different config files for different environments. It's easy! Just split the config file into two files:

- `config.php` will hold all settings that are the same for all environments
- `config-local.php` will hold environment specific settings

And then add this line at the bottom of `config.php`:

```php
// Split Config Pattern
// See https://processwire.com/talk/topic/18719--
require __DIR__ . "/config-local.php";
```

<div class='uk-alert uk-alert-warning'>Make sure to add `config-local.php` to the `.gitignore` file!</div>

### Dump Database

To make it easy to restore the database later on the remote server we create a dump now:

`label: LOCAL`
```bash
rockshell db:dump
```

This will store the dump in `/site/assets/backups/database/db.sql`.

### Setup Server (Vhost + DB)

Use your webhosting panel to setup a vhost and database for your project! Once that is done we can start the deployment process.

### Copy files (rsync)

Let's first copy files from our local machine to the remote server:

`label: LOCAL`
```bash
# !!!!! MAKE SURE YOU ARE IN THE PROJECT ROOT !!!!!
cd /path/to/your/project

# copy content of current folder to remote server
# you can add additional excludes as needed
rsync -avz -e "ssh -i ~/.ssh/id_rockmigrations" \
  --exclude='.ddev' \
  --exclude='.git' \
  --exclude='.vscode' \
  --exclude='.github' \
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

Now that we have copied all files to the server we need to set up the database. Most hosting providers offer a GUI for that. If you want to import the dump from the before uploaded files you can use the following command:

`label: REMOTE`
```bash
mysql your_db_name < /path/to/your/dump.sql
```

### Update config.php

Before adding the database credentials, let's talk about security. When developing locally, it's common to use simple credentials like `ddevadmin/ddevadmin`. If you just restore your local database to the production server, these credentials would work there too - which is a serious security risk!

To prevent this, we'll change the `userAuthSalt` on the production server. This salt is used to hash passwords, so changing it will invalidate all existing password hashes. This means:
1. Nobody can use your local development credentials on the production site
2. You'll need to reset passwords for all users on the production site
3. Your local development site remains unchanged

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

You should now be able to open your website in your browser, but you should not be able to log in!

<div class='uk-alert uk-alert-warning'>Make sure your website works before you continue!</div>

### Reset User + Password

Using RockShell it is super fast and easy to reset a user's password:

`label: REMOTE`
```bash
php RockShell/rock user:reset
```

After that you should be able to login to your website on the remote server.

## Deploy via Github Actions

Github Actions is a service that allows you to automate your development workflow. In this case we want to automate the deployment of our website to the remote server. We will use Github Actions to copy the files from the Github repository to the remote server and then trigger the RockMigrations deployment and migration scripts.

### Prepare the server

For automated deployments, we need a more sophisticated folder structure that allows us to:
- Keep multiple versions of our site
- Roll back to previous versions if needed
- Share files between versions (like uploads)
- Switch between versions without downtime

We'll create this structure:
```
/path/to/your/site/
  ├── current -> release-1      # Symlink to current release
  ├── release-2-                # Previous release
  ├── release-1                 # Current release
  └── shared/                   # Shared files (uploads, logs, etc)
      ├── site/assets/files/
      └── site/config-local.php
```

RockShell will help us set this up with a single command:

`label: REMOTE`
```bash
php RockShell/rock rm:transform
```

### Update your vhost / document root

After running this command your website will show a 404 error. This is expected! We need to update the document root in your webhosting panel to point to the `current` folder:

```
OLD: /path/to/your/documentroot
NEW: /path/to/your/documentroot/current
```

This change ensures that your web server always serves the currently active release. After updating the document root your site should work again.

### Configure GitHub Repository

For security reasons, sensitive information like SSH keys and server details are stored as GitHub Secrets and Variables. Here's what you need to set up:

#### Required Secrets

These values are encrypted and only visible to GitHub Actions:

**1. SSH_KEY** - The private key for server access

`label: LOCAL`
```bash
# Copy the entire private key, including BEGIN and END lines
cat ~/.ssh/id_rockmigrations
```

**2. CI_TOKEN** - Personal Access Token (PAT)

- Create a new token at GitHub.com → Settings ...
- Required for accessing private repositories during deployment
- Minimum required permissions: `repo` and `workflow`

**3. KNOWN_HOSTS** - Server SSH fingerprint

```bash
# Get your server's SSH fingerprint
ssh-keyscan your-server.com
```

#### Required Variables

These values are visible in logs:

**1. SSH_HOST** - Your server's hostname

- Example: `example.baumrock.com`
- Use the domain name or IP address

**2. SSH_USER** - Server username

- Example: `deploy`
- The user that owns your website files

**3. DEPLOY_PATH** - Path to your website

- Example: `/var/www/example.com`
- Do NOT include `/current` or trailing slash

### Add Github Actions Workflow

Next, we'll create the workflow file that tells GitHub how to deploy our site. Create this file in your local project:

`.github/workflows/main.yaml`

I recommend using `main.yaml` as the filename since it will handle deployments for the main branch, making it easy to add more workflows later (like `staging.yaml` for your staging environment).

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

### Test the Deployment

Now you can safely push your changes:
1. Commit the workflow file
2. Push to GitHub
3. Go to Actions tab in your repository
4. Monitor the deployment progress

If you see any errors, check the workflow logs for details about what went wrong.

### RockShell + FilesOnDemand

Let's set up your local development environment to work with production data:

1. **Database Synchronization**: First, we'll configure RockShell so you can pull the production database to your local machine with a simple `db:pull` command. This gives you real content to work with during development.

2. **Missing Files**: After pulling the database, you'll notice that while the database contains references to uploaded files, these files don't exist on your local machine yet. Downloading all files would be slow and waste disk space.

3. **FilesOnDemand**: This is where FilesOnDemand comes in - it automatically downloads files from your production site only when they're actually needed. Your local environment stays lean, but you still see all the images and files.

Add this to your local `site/config-local.php`:

```php
# add rockshell config (to use db:pull)
$config->rockshell = [
  // 'remotePHP' => 'php81',  # uncomment if your server needs a specific PHP version
  'remotes' => [
    'production' => [
      'ssh' => 'DEMOUSER@DEMOSERVER',
      'dir' => '/path/to/your/documentroot/current',
    ],
  ],
];

# enable filesOnDemand feature
$config->filesOnDemand = 'https://your-live.site/';
```

After this setup you can:
- Pull the production database with `rockshell db:pull production`
- See all images and files from production immediately
- Files are downloaded only when you actually view them

> Congrats! You are now ready to rock your deployments like never before!
