# Deployments Part 1

You can use RockMigrations to create fully automated CI/CD pipelines for Github/Gitlab.

- In **part 1** we will explain how to deploy a ProcessWire site manually to a remote server via rsync / SSH and pull content from the server via RockShell.
- In **part 2** we will create a Github Actions Workflow that will deploy the site automatically.
- In **part 3** we will show a multi-developer setup where we deploy different branches to different environments including fully automated releases.

<div class="uk-alert">Please note that this tutorial is for Github Actions and all commands shown are for MacOS. If you are using another Git provider or another operating system you can use the same process, but the commands will be slightly different.</div>

<div class="uk-alert">Please also note that we will use RockShell along the way. You can follow the Tutorial without installing/using RockShell, but then you need to execute some actions manually, which is not recommended.</div>

Let's get started!

## What we will do

- Setup passwordless authentication (SSH)
- Take the new website online (rsync)
- Add a new feature (RockShell)
- Add a new image field and pull changes manually

## Setup passwordless authentication (SSH)

To be able to deploy our site via rsync we need to setup passwordless authentication via SSH. If you already know how to use SSH and you have already created an SSH key for your computer you can skip this step.

If not, here's what you have to do:

### Create the SSH keypair

In this step we will create a personal SSH keypair that we will use for passwordless authentication. Please note that this key is specific to our user, but will be used for all projects of this user.

To create the SSH keypair we use the following command:

```sh
ssh-keygen -t ed25519 -f ~/.ssh/id_bernhard -C "you@example.com"
```

Let's break down this command:

- `ssh-keygen`: The command to generate SSH key pairs
- `ed25519`: Specifies we want to use Ed25519 encryption
- `-f ~/.ssh/id_bernhard`: Sets the filename and location for the key files
  - This will create two files:
  - `~/.ssh/id_bernhard` (private key)
  - `~/.ssh/id_bernhard.pub` (public key)
- `-C "you@example.com"`: Adds a comment to identify the key (usually your email)

This command will prompt you to enter a passphrase. You can press enter twice to skip setting a passphrase. Using one adds an extra layer of security but is not possible for us as we want to use the key for passwordless authentication.

### Add key to the remote server

In this example we will use VSCode Remote - SSH to connect to our server as root. Using this technique we can browse the remote server as if it was a local VSCode project. Not all providers support this, though. You might have a web based interface or you might have to work from the command line.

The remote server is a VPS and we will setup the following:

- `deploy.baumrock.com`: The server's hostname
- `deploy`: The unix user that serves the website
- `/home/users/deploy/www/deploy.baumrock.com`: The document root of the website

We want to copy files via rsync to the document root. For this we want to connect to the server as the `deploy` user. To make that work all we have to do is to copy the public key `id_bernhard.pub` to the `~/.ssh/authorized_keys` file of the `deploy` user:

```sh
cat ~/.ssh/id_bernhard.pub
```

<img src=https://i.imgur.com/mK8L30Q.png class=blur alt="Copy key">

1. On the remote server, create the file `~/.ssh/authorized_keys`.
2. Copy the content of the public key `id_bernhard.pub` from your local machine.
3. Paste it into the `authorized_keys` file on the remote server and save it.

### Try to connect

Now that the key is in place we can try to connect to the server as the `deploy` user:

```sh
ssh -i ~/.ssh/id_bernhard deploy@deploy.baumrock.com
```

Let's break down this command:

- `ssh`: The command to establish an SSH connection
- `-i ~/.ssh/id_bernhard`: Specifies which private key file to use for authentication
- `deploy@deploy.baumrock.com`: The connection string in format `username@hostname`
  - `deploy`: The username we want to connect as
  - `deploy.baumrock.com`: The hostname of the remote server

If everything worked you should see a message like this:

<img src=https://i.imgur.com/SBNydVW.png class=blur alt="Welcome Message">

Great! We can now connect to the server, so let's deploy our site!

## Take the new website online (rsync)

At the moment we have this setup:

- Upper left: The local website, opened in VSCode
- Upper right: The local website, opened in Chrome
- Lower left: The remote server, opened in VSCode
- Lower right: The remote website, opened in Chrome

<img src=https://i.imgur.com/uJ8LtX4.jpeg class=blur alt="Take the new website online">

### Copy the files

Now let's copy our local files to the remote server. VSCode would even support to copy files via drag and drop, but we will use the command line for this. By using rsync we can later upload only the changed files to the server efficiently.

First, remove the `index.html` file from the remote server:

```sh
ssh deploy@deploy.baumrock.com
rm /home/users/deploy/www/deploy.baumrock.com/index.html
```

Now let's copy the files to the remote server:

```sh
# make sure you are in the pw root path!
cd /path/to/your/local/site

# copy the files to the remote server
rsync -avz -e "ssh -i ~/.ssh/id_bernhard" --exclude='.ddev' --exclude='.git' ./ deploy@deploy.baumrock.com:/home/users/deploy/www/example.deploy.baumrock.com/
```

Let's break down this command:
- `rsync`: The command to synchronize files between systems
- `-avz`: Command options
  - `a`: Archive mode (preserves permissions, timestamps, etc.)
  - `v`: Verbose output (shows what's being copied)
  - `z`: Compress data during transfer
- `-e "ssh -i ~/.ssh/id_bernhard"`: Specifies to use SSH with the given private key
- `--exclude='.ddev' --exclude='.git'`: Excludes these directories from being copied
- `./`: The source directory (current directory)
- `deploy@deploy.baumrock.com:/home/users/deploy/www/example.deploy.baumrock.com/`: The destination
  - `deploy`: Remote username
  - `deploy.baumrock.com`: Remote hostname
  - `/home/users/deploy/www/example.deploy.baumrock.com/`: Remote destination path

This command will efficiently copy all files from the current directory to the remote server, excluding the .ddev and .git directories, while preserving file attributes and using compression for faster transfer.

### Set file permissions

After uploading our files we need to set the correct file permissions. This is done by running the following commands on the remote server:

```sh
cd /home/users/deploy/www/example.deploy.baumrock.com
find . -type d -exec chmod 755 {} \;
find . -type f -exec chmod 644 {} \;
```

This will set the correct permissions for directories and files.

<div class='uk-alert uk-alert-warning'>Please read the ProcessWire documentation about file permissions for more information!</div>

Now that our files are in place we can reload the page:

<img src=https://i.imgur.com/KAhyWKI.png class=blur alt="File permissions">

This error is not helpful, so we enable debug mode in `/site/config.php` and reload the page again:

<img src=https://i.imgur.com/mfHu9zB.png class=blur alt="SQL Error">

That's expected, because we have not setup the database yet. Let's do that in the next step.

### Import the database

Please create and import the database as you like. We will not cover this in this tutorial, because every provider offers different ways to do this.

Now that the database is in place we need to set the correct credentials to be able to connect to it. For that we open the `/site/config.php` file and set the correct credentials:

```php
$config->dbUser = '...';
$config->dbPass = '...';
...
```

<img src=https://i.imgur.com/ZMkY0Ft.jpeg class=blur alt="DB Imported">

1. Export database on local machine (via DDEV)
2. Import database on the remote server (via Adminer)
3. Reload the browser and see the new website!

## Add a new feature (RockShell)

Now that the website is running let's assume that we want to add a new feature to the website. For this example we will add "RockShell" to our website, because we need it for the next step anyhow - so it's actually a real world example.

### Add RockShell locally

The first step is to add RockShell to our local installation. For this we download RockShell from Github and copy it to the `/RockShell` directory of our website:

<img src=https://i.imgur.com/BVl4U4l.png class=blur alt="Download RockShell">

<img src=https://i.imgur.com/ynDEw6M.png class=blur alt='Install RockShell'>

1. Copy files to `/RockShell`
2. Make sure RockShell runs properly on your local machine (see RockShell documentation)

### Deploy RockShell to the remote server

Now that we have RockShell running on our local machine we can deploy it to the remote server. Rsync will only copy the changed files, so we don't have to worry about the files that didn't change (in theory):

```sh
rsync -avz -e "ssh -i ~/.ssh/id_bernhard" --exclude='.ddev' --exclude='.git' ./ deploy@deploy.baumrock.com:/home/users/deploy/www/example.deploy.baumrock.com/
```

<img src=https://i.imgur.com/tZJQ4aB.jpeg class=blur alt="Deploy new feature">

WAIT! Did we just break our live site? Yes, we did! As you can see in (1) the rsync command not only copied our new files (RockShell), but also overrode some other files that we carefully crafted on the remote server in the previous steps - for example the `config.php` file. Also, we need to adjust file permissions again (2) and we have to enable debug mode (3) to see the error message (4).

The error message states that we can't connect to the database, which is obvious, as we overrode the `config.php` file, which contains the database credentials.

### Test RockShell on the remote server

Once the db credentials are fixed we can test RockShell on the remote server:

<img src=https://i.imgur.com/OopYRxg.png class=blur alt="Testing RockShell">

<div class='uk-alert'>NOTE: As you can see in the terminal, we are running RockShell as deploy user, not as root!</div>

## Add content and pull changes

The reason why we added RockShell is to make it easy to pull changes from the remote server to the local machine. For this we will use the `pull` command of RockShell, but first, let's add some content to the remote server!

### Add content

Please create a new page from the backend on your remote server (live site)!

### Pull changes

Now we can use RockShell to pull changes from the remote server to our local development environment:

<img src=https://i.imgur.com/FBvyUVo.jpeg class=blur alt="Pull changes">

1. We created a new page on the remote server
2. Confirms that the page is not there on local
3. Pull changes via RockShell

As you can see, the pull command fails. That's because RockShell does not yet know where to connect to!

### Setup RockShell Remotes

To fix this we add the following to our local `/site/config.php` file:

```php
// rockshell config
$config->rockshell = [
  'remotes' => [
    'production' => [
      'ssh' => 'deploy@deploy.baumrock.com',
      'dir' => '/home/users/deploy/www/example.deploy.baumrock.com',
    ],
  ],
];
```

Now the RockShell `db:pull` command should work:

<img src=https://i.imgur.com/jnVUG4C.jpeg class=blur alt="Successful pull">

As you can see we have the `NEW CONTENT` page on the remote and also on our local machine.

This might sound like a little thing, but it is an extremely powerful concept!

- One example use case could be that you use this technique to always create content or new features (fields, templates, etc.) on the remote server and then pull changes via `db:pull`. This ensures that you always have a single source of truth - which is your live system. Once you have your new fields, templates, etc. on your local machine you can add code to use them. And once you are done with coding you can push your code to the remote server and everything should work. This is a workaround for anybody not wanting to use migrations or if migrations would be more work than it's worth.
- Another use case could be to quickly try out new features or modules on your local machine and then, when you don't need them anymore, simply revert your files and do a `db:pull` to also reset your database to the state before you started testing.

So RockShell solves a lot of content-related problems for us, but deploying features is still a pain! It's a lot of manual work and it's prone to errors.

Let's improve this in the next part!
