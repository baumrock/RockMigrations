# Deployments Part 2

In part 1 we explained how to deploy a ProcessWire site manually to a remote server over SSH. In part 2 we will create a Github Actions Workflow that will deploy the site automatically.

## How it works

We will use the Deployment Workflow that is part of the RockMigrations module: [See workflow here](https://github.com/baumrock/RockMigrations/blob/main/.github/workflows/deploy.yaml)

The basic principle is that we split up our site's root folder into the following structure:

- **current**: A symlink to the current release
- **release-XXX**: The current release
- **shared**: Data that is persistent across releases (like files and config)

Then, instead of serving the root folder directly, we serve the `current` folder that points to the latest release.

When deploying a new release, we first copy all files to a new `release-YYY` folder. When everything is done, we simply change the symlink `current` to point to the new release.

If anything goes wrong, we can simply rollback to the previous release by changing the symlink `current` back to the previous release.

By default we keep three releases, but you can customize this to your needs.

Let's make this work!

## Create a new Github Repository

First, we need to turn our local ProcessWire site into a Github repository. For that we create an empty repository on Github and initialize a git repository in our local site:

<img src=https://i.imgur.com/RqfFF6B.png class=blur alt="Git Init">

As you can see on the left side, we have 4478 files that would be added to the repo. In reality you'd create a .gitignore file now to exclude all unneeded files. But we'll go ahead and add everything to the repo.

<img src=https://i.imgur.com/mNhcaqz.png class=blur alt="Initial Commit">

I cannot recommend the `Git Graph` extension for VSCode enough! It's a game changer for understanding git repositories and it's a must-have for anyone working with git. Unfortunately it has not seen any updates for some years, but Microsoft seems to be working on an official version! For this tutorial we'll stick with what we have.

### First Push

Now that we have our first commit we can push it to the remote:

<img src=https://i.imgur.com/heIt0Iq.png class=blur alt="First Push">

Reloading the Github browser tab shows that the push was successful:

<img src=https://i.imgur.com/EoyuzaU.png class=blur alt="Push Successful">

## Create a Github Actions Workflow

Now that we have our first commit in the repo, we can create a Github Actions Workflow that will deploy our site automatically.

For that, create a new file in the `.github/workflows` directory called `deploy.yaml` and copy the following content into it:

```yaml
name: Deploy

on:
  push:
    branches:
      - main

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - run: echo "TBD"
```

We commit this to our repo and push it to Github and voila - you have created your first Github automation!

<img src=https://i.imgur.com/9seABVp.png class=blur alt="Deploy Workflow">

Please also click on the workflow log and inspect the output. It will show you the commands that are executed:

<img src=https://i.imgur.com/Rjg2184.png class=blur alt="Deploy Workflow Log">

Now let's do something useful. Change the workflow file to use RockMigrations' deployment workflow:

```yaml
name: Deploy

on:
  push:
    branches:
      - main

jobs:
  deploy:
    uses: baumrock/RockMigrations/.github/workflows/deploy.yaml@main
    with:
      PATH: ${{ vars.DEPLOY_PATH }}
      SSH_HOST: ${{ vars.SSH_HOST }}
      SSH_USER: ${{ vars.SSH_USER }}
    secrets:
      CI_TOKEN: ${{ secrets.CI_TOKEN }}
      SSH_KEY: ${{ secrets.SSH_KEY }}
      KNOWN_HOSTS: ${{ secrets.KNOWN_HOSTS }}
```

Then add the variables to your repo settings:

<img src=https://i.imgur.com/njKY9Y2.png class=blur alt="Repo Settings">

Now let's re-run the job and see what happens:

<img src=https://i.imgur.com/2bRjzJo.png class=blur alt="Re-run job">

The job fails again, but we are a step closer!

<img src=https://i.imgur.com/h3W9jA5.png class=blur alt="One step closer">

The variables are picked up correctly, but the deployment still fails with the error `Host key verification failed.` in the step `Deploy via RSYNC and Set Permissions`.

This is because Github is not yet able to connect to our server `deploy.baumrock.com`. To allow Github to push files to our server, we need do the same as we did in part 1, but this time we do not create a keypair for ourselves, but for Github!

### Create the Github SSH Key

In this step we will create a keypair called `id_github` and again, we will copy the public key to our server and then move the private key to the Github repo settings.

```sh
ssh-keygen -t ed25519 -f ~/.ssh/id_github -C "you@example.com"
```

For an explanation of the command please refer to part 1.

Next, we copy the public key to our server (also see part 1).

```sh
cat ~/.ssh/id_github.pub
```

<img src=https://i.imgur.com/I9WzhH0.png class=blur alt="Copy Public Key">

Then we check if authentication with this SSH key works:

```sh
ssh -i ~/.ssh/id_github deploy@deploy.baumrock.com
```

<img src=https://i.imgur.com/XcWr2mH.png class=blur alt="SSH Key Authentication">

As you can see, the key authentication works and we can connect to the server. Now we can copy the PRIVATE key to the Github repo settings:

```sh
# copy the private key (without the .pub extension)
cat ~/.ssh/id_github
```

<img src=https://i.imgur.com/R8Bc0H4.jpeg class=blur alt="Copy Private Key">

### Get the Server's Fingerprint

Next we need to get the server's fingerprint so Github can verify it's connecting to the correct server. We do this by running ssh-keyscan which outputs the server's public key fingerprint that we'll add to Github's known_hosts.


```sh
ssh-keyscan deploy.baumrock.com
```

<img src=https://i.imgur.com/1lMhED6.jpeg class=blur alt="Known Hosts">

### Add the PAT

The last missing piece is to copy the PAT (Personal Access Token) to the Github repo settings. This token is used to authenticate the Github Actions job with Github.

Think of it like this: Github Actions boots up a virtual machine. This virtual machine then does what you tell it to do. We tell it to checkout our repo and copy it over to our server. For that we not only need the SSH key to connect to our server, but also a token to authenticate with Github, because our repo is private and not everyone can access it!

Generating a PAT is a one time thing. But be careful! Anybody having this token can access your private repositories! That's why we do not share a screenshot here. Please check the [Github Docs](https://docs.github.com/en/authentication/keeping-your-account-and-data-secure/managing-your-personal-access-tokens#creating-a-fine-grained-personal-access-token) for this step.

Now we should have all secrets in place:

<img src=https://i.imgur.com/kepOi2y.png class=blur alt="Secrets">

### Transform the Folder Structure

<div class='uk-alert uk-alert-warning'>Before we can proceed, we need to transform the folder structure of our site. The quickest way to do this is via the RockShell `rm:transform` command.</div>

The `rm:transform` is part of the RockMigrations module. So far we have not installed it, so it does not exist on the remote server. Let's fix that by adding it to the development site and then rsyncing it over to the remote server.

```sh
# add the RockMigrations module to /site/modules/RockMigrations
# download from https://github.com/baumrock/rockmigrations
cd /path/to/your/project/site/modules

# rsync the site over to the remote server
rsync -avz -e "ssh -i ~/.ssh/id_bernhard" ./RockMigrations deploy@deploy.baumrock.com:/home/users/deploy/www/example.deploy.baumrock.com/site/modules/
```

<img src=https://i.imgur.com/JnDVeu3.jpeg class=blur alt="Rsync RockMigrations">

1. RockMigrations is in place on the local development site.
2. rsync RockMigrations to the remote server.
3. RockMigrations is now in place on the remote server.
4. The `rm:transform` command is now available on the remote server.

Now let's run the `rm:transform` command on the remote server:

<img src=https://i.imgur.com/Xc4r47C.png class=blur alt="Run rm:transform">

As you can see on the left side, this command splits up our root folder into the following structure:

- current: A symlink to the current release
- release-1: The current release
- shared: Data that is persistent across releases (like files and config)

Now let's try to view our site in the browser:

<img src=https://i.imgur.com/ez0FN54.png class=blur alt="View site">

As you can see, we get an error. This is because the document root still points to `example.deploy.baumrock.com`. We need to update our document root to point to `example.deploy.baumrock.com/current`.

<img src=https://i.imgur.com/Kjxzm6e.png class=blur alt="Update Document Root">

Great, now we have a working website with the new folder structure:

<img src=https://i.imgur.com/d45aDNA.png class=blur alt="Working Website">

Once the website is running, we can confirm to remove the backup (1).

<img src=https://i.imgur.com/wo9cS90.png class=blur alt="Create config-local.php">

As you can see in the screenshot above, there is no backup folder left (2). In (3) you can see that we got a warning when transforming the folder structure. The copy command for the file `config-local.php` failed!

This is because we have not yet split up our site's config into two files.

Why do we need two config files? Please read [this forum thread](https://processwire.com/talk/topic/18719-maintain-separate-configs-for-livedev-like-a-boss/). The idea is simple but powerful: We have one global config that is equal for all environments (local dev, staging, production) and one environment-specific config that is different for each environment. The global config is part of the git repo and does not contain any secrets. The environment-specific config is stored in `/site/config-local.php` and is NOT part of the git repo. We have to create this file manually for each environment.

To make sure we don't lose this config file on each deployment, we have to place it in the `shared` folder. This folder will not be touched by the deployment workflow, so it will always be there after a deployment.

On the local development machine we simply split `config.php` into `config.php` and `config-local.php`. Let's do this now and push the changes to Github.

In `config.php` we add the following line of code at the very end:

```php
require_once __DIR__ . '/config-local.php';
```

And then we move all secrets from `config.php` to `config-local.php`. In our example we end up with this `config.php`:

```php
<?php

namespace ProcessWire;

if (!defined("PROCESSWIRE")) die();

/** @var Config $config */
$config->useFunctionsAPI = true;
$config->usePageClasses = true;
$config->useMarkupRegions = true;
$config->prependTemplateFile = '_init.php';
$config->appendTemplateFile = '_main.php';
$config->templateCompile = false;

$config->dbHost = 'db';
$config->dbName = 'db';
$config->dbUser = 'db';
$config->dbPass = 'db';
$config->dbPort = '3306';
$config->dbCharset = 'utf8mb4';
$config->dbEngine = 'InnoDB';

$config->tableSalt = '5a39bf0ddb4ad8c4158def6584e0fce64b22ff94';
$config->chmodDir = '0755'; // permission for directories created by ProcessWire
$config->chmodFile = '0644'; // permission for files created by ProcessWire
$config->timezone = 'Europe/Vienna';
$config->defaultAdminTheme = 'AdminThemeUikit';
$config->installed = 1734276718;
$config->httpHosts = array('deploy.ddev.site');

$config->debug = false;

// rockshell config
$config->rockshell = [
  'remotes' => [
    'production' => [
      'ssh' => 'deploy@deploy.baumrock.com',
      'dir' => '/home/users/deploy/www/example.deploy.baumrock.com',
    ],
  ],
];

require_once __DIR__ . '/config-local.php';
```

And this `config-local.php`:

```php
<?php

namespace ProcessWire;

$config->debug = true;
$config->userAuthSalt = 'bdf92c990851bcb30cdf0140e9589be8177294dc';
```

NOTE: As you can see we keep most of the settings in the global config file. Everything that is in this file will be shared across all environments and all developers. The config for rockshell, for example, is the same for all developers, so we can keep it in the global config file.

`$config->debug` on the other hand should only be `true` in the local dev environment! That's why we set it to `false` in the global config file and only override it on the local dev machine. We could then also enable it on the staging system, but leave it turned off on the production system.

This is a simple yet powerful technique to keep your config clean and secure. In the `config-local.php` file you can also put settings for tracydebugger, for example, where you can set different paths for different developers - neat! Or, as for the next step, we can add custom database credentials there.

Now let's commit these changes and push them to production!

> First, make sure to add `config-local.php` to the `.gitignore` file!

Then commit the changes and again, the deployment will fail. This is the result:

<img src=https://i.imgur.com/MBrnqBM.jpeg class=blur alt="Deployment Failed">

The log in (1) shows that the database connection failed. This is because we still have the DDEV config in the `config.php` file but don't have any specific database credentials in the `config-local.php` file! Let's fix this:

```php
<?php

namespace ProcessWire;

$config->dbHost = 'localhost';
$config->dbUser = 'deploy_example';
$config->dbName = 'deploy_example';
$config->dbPass = '%94hbnQ9i_vq@dTc#?';

$config->userAuthSalt = 'e022f6e4ca3e2f7f3b6c797de9316f0500a4';
```

After refreshing the page, we still get an error:

<img src=https://i.imgur.com/tgyD7ij.jpeg class=blur alt='Follow Symlinks Problem'>

We see that the deployment was successful (1) and we also see the new release folder in the tree on the left side. Inspecting the error log (3) shows that this webserver has a problem with the `FollowSymlinks` directive in the `.htaccess` file. We already fixed that in part 1, but the deployment broke it again.

The solution is to fix it on the local development machine and then push the new .htaccess to the server.

Now this fix is the first time you should be able to enjoy a fully automated deployment workflow! A simple fix, a simple commit, a simple push, and you're done!

<img src=https://i.imgur.com/uTZNL1Q.jpeg class=blur alt="Fix FollowSymlinks issue">

We see that we changed the .htaccess file (1), then we pushed that change to Github (2), there we see the new and successful action (3), after a reload we see our website (4) and we also see the new release folder in the file tree (5).

> CONGRATULATIONS! You have just deployed your first ProcessWire feature/fix in a fully automated way!

## Example Use Case

Not all features are code-only. As soon as changes also affect the database, we either need to create a migration or - if you prefer cowboy coding - we create the new field on the production system first, then pull the database to our local development machine and then create the code for it and push it back to production.

### Add Image Field

Let's say we want to add an image field to the homepage.

First, we create the field on the production system and add it to the `home` template:

<img src=https://i.imgur.com/Feyd174.png class=blur alt='Wrong Password'>

Wait! What's that? We can't log in any more?

Yes! That's because we added a new `userAuthSalt` to our production system that differs from the one on our local development machine. You don't have to do this, but I prefer to do it as it adds an additional layer of security. The reason might be totally opinionated, but it has been workign great for me for a long time.

I'm always using "ddevadmin" both for the user and password on my local projects. On any project. That way I don't have to remember login credentials for any of my projects. I just go to the backend and log in.

This leads to the problem that if I uploaded that database to a production system and forgot to change the username and/or password, I would potentially be in trouble as anybody could just log in with "ddevadmin" and "ddevadmin".

Having a different userAuthSalt on the production system takes care of that risk, because using the same password with a different salt will not work.

But what credentials can we use to log in now? RockShell to the rescue!

<img src=https://i.imgur.com/MuSW8EZ.png class=blur alt='RockShell user:reset'>

We use the `user:reset` command (1) to reset the password for the former "admin" user (2). Then we set a new name "bernhard" (3) and confirm the randomly created password (4). Then we enter those credentials in the login form (5+6) and voila! We're logged in!

Next, we add an image field to the `home` template and upload an image:

<img src=https://i.imgur.com/usuk2EI.png class=blur alt="Upload Image">

### Pull Changes

Then we run `rockshell db:pull` on the dev machine and when we edit the homepage, we see this:

<img src=https://i.imgur.com/eMa2rnz.png class=blur alt='Wrong Path'>

This is because we forgot to update the path of our RockShell config to match our new deployment folder structure!

<img src=https://i.imgur.com/haQQ1OH.jpeg class=blur alt="Update RockShell Path">

We see a lot on this single screen! First, we open the `config.php` file (1) and update the path to the RockShell config (2). Then we run the `db:pull` command again and see that it is now successful (3). This results in the same database on our dev machine, so we see the remote image (4) is now also on the local file system (5).

Or to be more precise: The reference to that image is not in the local database, but - as we can see in the error message (6) - the file is not!

This is a common problem with this workflow. You often not only need to pull the database, but also the files. So we need to do some RSYNC magic again, right?

No! RockMigrations has you covered!

### Files On Demand

We can tell RockMigrations to pull all files that it tries to load from a remote server ON DEMAND. All you have to do is to provide the remote URL to pull the files from. Guess where to put this? Yes, in the `config-local.php` file, as it is totally specific to our local development machine and you might even turn this feature off most of the time.

```php
$config->filesOnDemand = 'https://example.deploy.baumrock.com/';
```

Having that line in place we can reload our page:

<img src=https://i.imgur.com/Ws0aLQx.png class=blur alt='Files On Demand'>

Boom! The image is now also in the local file system! Without even having to run a single command or manually downloading a single file. This is an extremely powerful concept, because you might have gigabytes of files on your production system and you don't want to download them all to your local machine. Files on demand will download them as you browse your local copy of the site. 🚀

> CONGRATULATIONS! You have just mastered the art of fully automated deployments! 🎉 In the next part we will cover some advanced workflows.
