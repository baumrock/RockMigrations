# Deployments Part 3

So far we have learned how to deploy a ProcessWire project from your local machine to a remote server with a passwordless SSH connection. We then added Github Actions to the mix to automate the deployment process.

In this part I will show you how you can use your new automation superpowers to deploy different branches to different environments and how to get fully automated releases with tags following semantic versioning.

Let's get started!

## Agenda

- Deploy different branches to different environments
- Create automated releases with semantic versioning
- My personal Git workflow

## Different environments

Before we start, let's quickly recap where we are at right now:

<img src=https://i.imgur.com/QDWhaFW.png alt="Current state">

As you can see, we are currently on the `main` branch on our local development machine (1). The last commit was to fix the rockshell path.

What you can also see in (2+3) is that we have not yet pushed the last commit to the remote server. The remote server is still at the previous commit (2). "origin" is the remote server in our case.

Let's push that to the remote to be in sync before we start. Note that this push will trigger another deployment in Github Actions. This is actually nothing that changes anything on the remote server, so we could argue that the configuration might better be placed in `config-local.php`. But having it in the global `config.php` makes sure that every developer has the same configuration on their local machine, so we leave it there for this tutorial.

After the push we see that "main" and "origin" are at the same commit (1) and we see the deployment going on (2).

<img src=https://i.imgur.com/fkdsA04.png class=blur alt="Push to remote">

### Creata a new branch

Now let's create a new `dev` branch that we will later deploy to a staging environment.

Creating the new branch is easy:

```sh
git checkout -b dev
```

<img src=https://i.imgur.com/hG9ypuE.png alt="Create new branch">

The command line tells us that we switched to the new branch (1) and we see in (2) that this branch is not published yet. In (3) the dot indicates that we are on the `dev` branch and since there is no `dev/origin` yet, we see that it is not published either.

Let's publish that branch! Just hit `Publish Branch` in VSCode.

<img src=https://i.imgur.com/OeQQrNz.png class=blur alt="Publish branch">

We now see that the local `dev` branch is on par with the `origin/dev` branch (1). VSCode is ready to accept new commits (2) and on Github we also see the new branch in the dropdown (3).

Now that we have the new dev branch, we can setup an automated deployment to a staging environment. For that we need several things:

- The file system
- The database
- The Github Actions workflow

### Prepare staging files

This part is quite easy, because we can simply clone the production folder and point a new vhost to that new folder. So we log into our remote server:

<img src=https://i.imgur.com/l9AMeWf.png alt="Login to remote server">

At the moment we have only the `example.deploy.baumrock.com` folder. So let's copy that to `staging.deploy.baumrock.com` using the following command:

```sh
cp -r example.deploy.baumrock.com staging.deploy.baumrock.com
```

<img src=https://i.imgur.com/gnFoR3Q.png alt="Copy production folder">

As you can see here, after executing the command (1) we have the new folder in the tree (2) with the exact same files and folders as the production folder. That also means that the `config-local.php` is the same, as you can see in (3+4).

Let's create a new database for our staging environment!

### Create the staging database

<img src=https://i.imgur.com/POaka6n.png alt="Create staging database">

Creating the database is easy in my panel. We can use the GUI. But as you can see our new database is empty. We need to export the production database and import it into the new database. The quickest way to do this is via the command line, but you can also use the GUI if you prefer.

```sh
mysqldump deploy_example > /tmp/dump.sql
mysql deploy_staging < /tmp/dump.sql
```

<img src=https://i.imgur.com/reKoJGv.png alt="Clone Database">

After executing these commands (1) we also update the `config-local.php` to point to the new database (2) and to be extra safe we also set a new `userAuthSalt` (3) so that production users can not login to the staging environment.

### Setup the staging vhost

Files are in place, the database is in place - so let's create the vhost and see if it works!

<img src=https://i.imgur.com/0oDB6bx.png class=blur alt="Setup vhost">

WOHOO! We have setup the new domain (1), we have an SSL certificate (2) and we can access the site (3) and see the exact same content as on the production site.

### Show image on staging site

Now let's add the image that we uploaded before to the frontend of our website. Before we push that change to production, we want to test it on the staging site and once we confirmed that it not only works on the dev environment but also on the staging environment, we can push it to production.

So we get back to our local VSCode and add the image to the frontend:

<img src=https://i.imgur.com/oDyAOga.jpeg class=blur alt="Add image to frontend">

We open `home.php` (1), add the quick&dirty image code (2) and reload the frontend to see the image (3).

Let's inspect the Github Tab:

<img src=https://i.imgur.com/2B4d2U3.png alt="Github Tab">

We see that we have changed one file (1) and we can inspect the changes in a diff view (2).

Now let's commit that change, BUT DON'T PUSH IT YET!

<img src=https://i.imgur.com/ugoXNq3.png alt="Commit changes">

Our local dev branch is now one commit ahead of both remote branches. But if we pushed that commit to the remote, it would do nothing, because we only have a github workflow for the `main` branch so far!

### Add staging workflow

Let's make Github deploy every push to the `dev` branch to our staging environment. For that we copy the `deploy.yaml` file from the `.github/workflows` folder and create the new file `.github/workflows/dev.yaml`.

You can name these files anything you want, but for this example we'll name our files like the branch they are linked to. So we rename `deploy.yaml` to `main.yaml` and create a new file called `dev.yaml`.

<img src=https://i.imgur.com/4W8r8Ig.jpeg class=blur alt="New workflow files">

We created/renamed the new files (1), then we set a meaningful name ofr the workflow (2), set the new workflow to run on push to the `dev` branch (3) and we set a new variable for the `PATH` (4) which we also add to our Github Repo Variables (5).

This should be all we need, as all other variables and secrets can be reused from the `main` workflow.

### Push to staging

<img src=https://i.imgur.com/joKLkgn.jpeg class=blur alt="Push to staging">

YEHAA! We pushed our changes to the `dev` branch (1) and Github Actions ran the new workflow (2). On the right side of the bubble we see that this time it shows `dev` instead of `main` like all the time before. In (3) we see that we have a new release folder in the staging folder and after a reload of `staging.deploy.baumrock.com` we see our image (4).

### Push to production

Now that we confirmed that everything works we can push the new version to production.

Using the git graph extension in VSCode this is easy:

<img src=https://i.imgur.com/82kSKwy.png alt="Push to production">

Double click on the `main` branch to check out that branch (1). Then right click on the `dev` branch (2) and select `Merge into current branch` (3).

Next, untick the checkbox "Create a new commit ..." and select "Yes, merge".

<img src=https://i.imgur.com/jRMRVMJ.png alt="Merge dev into main">

Our local repo is now on the `main` branch, which is 2 commits ahead of the remote `main` branch (3). We can push the updates by clicking "Sync Changes" (2).

<img src=https://i.imgur.com/TDZkHWY.jpeg class=blur alt="Push to production">

Voilà! We have pushed our changes to the `main` branch and Github Actions has deployed the new version to the production environment.

> Congratulations! You have now learned how to deploy different branches to different environments, which is an extremely powerful concept.

## Automated releases

Next, we want to expand our Github Actions workflow to also create automated releases whenever we push to the `main` branch.

### Conventional commits

We will use the `conventional commits` specification to create the release tags. All we have to do to bump the version number is to stick to specific commit message patterns:

- `fix: ...` will create a new patch release, eg 1.0.0 -> 1.0.1
- `feat: ...` will create a new minor release, eg 1.0.0 -> 1.1.0
- Any breaking change (eg `feat!: ...` or `fix!: ...`) will create a new major release, eg 1.0.0 -> 2.0.0

### Add the workflow

To use this automation all we need to do is use the `auto-release` workflow that is shipped with the RockMigrations module. Create a new file `.github/workflows/release.yaml` and add the following content:

```yaml
name: Auto-Release

on:
  push:
    branches:
      - main

jobs:
  auto-release:
    uses: baumrock/RockMigrations/.github/workflows/auto-release.yml@main
    with:
      email: "your-optional-email@example.com"
    secrets:
      token: ${{ secrets.CI_TOKEN }}
```

Now we have two workflows triggered by the same action (push to the `main` branch). But we want to deploy to production only after a new release has been created.

To achieve that modify the `main.yaml` workflow like this:

```yaml
on:
  workflow_run:
    workflows: ["Auto-Release"]
    types:
      - completed
```

You can name your workflow anything you want, but it is important that both the name you set in `releases.yaml` and the name you set in `main.yaml` are the same!

### Test the release workflow

To test our new setup we will do the following:

- Switch to the `dev` branch
- Create three new features
- Test it on the staging environment
- Merge changes into the main branch and push to production

<img src=https://i.imgur.com/97O7bUW.png alt="Feature 1">

We change the file `home.php` (1), add a new feature (represented by a new `<div>` tag) (2) and commit the changes with a proper commit message (3).

We do the same two more times and we have this git graph:

<img src=https://i.imgur.com/BGHyoo1.jpeg class=blur alt="Git graph">

Here you can see that we are now 4 commits ahead of our remote `dev + main` branches (1) and we can push those changes to the remote (2). On our local website we already see the new features (3).

Next, we push that to Github and Github will deploy everything to the staging environment.

<img src=https://i.imgur.com/2nRvojq.jpeg class=blur alt="Deploy to staging">

We see that our local dev branch is not in sync with the remote (1), the github workflow deployed to staging successfully (2), we see the new features on the staging site (3+4) and - of course - we also have the new release in our file system on the remote server (5).

### Create a new release

So far nothing new has happened, because the workflow was the same as before. But next we will merge those changes into the `main` branch and push to production.

This time we will merge and create a new commit. This is optional, but it has been a good practice for me over the last years to keep my projects organized.

The idea of this approach is that we can commit as often as we want to the dev branch (or any other feature branch), but we always only merge a fully functional state of the software into the `main` branch.

We do the same as before and merge `dev` into `main`, but this time we leave the checkbox "Create a new commit ..." ticked:

<img src=https://i.imgur.com/i40uw0s.png class=blur alt="Merge dev into main">

First, we checkout the `main` branch (1), then we right click on the `dev` branch (2) and select `Merge into current branch`. Then we make sure that `Create a new commit ...` is ticked (3) and finally we click `Yes, merge` (4).

This is how the result looks like:

<img src=https://i.imgur.com/ps44K5o.png class=blur alt="Git merge result">

After pushing that to Github we get two new workflow runs, one for the automatic release and one that deploys to production:

<img src=https://i.imgur.com/dkefy0c.png alt="Github Actions">

### Add version to PW footer

If you want you can now add the current version of your software to the footer of your ProcessWire site. RockMigrations has a setting for that:

<img src=https://i.imgur.com/w44nQNW.png alt='RockMigrations Setting'>

<img src=https://i.imgur.com/rAAqrz4.png alt='Version Footer'>

> Congratulations! You have now learned how to create automated releases with semantic versioning and fully automated CI/CD pipelines that deploy different branches to different environments!

## Git troubleshooting

So far the steps were quite straightforward, but I have to mention that using this workflow adds one caveat: As Github creates a new release for us it will also modify the state of the remote `main` branch. This means that we always have to pull changes from the remote into our local repo before we can continue working on the project locally.

But what if we forget that? I'll show you!

### Handling push conflicts

After a while the git graph extension will show the changes on the remote like this:

<img src=https://i.imgur.com/jzoRmnk.png alt="Remote Changes">

In (1) we see that the remote main branch is now ahead of our local one! The dot shows our currently active local branch (main), `origin/main` shows where the remote `main` branch is at. And `dev/origin` shows that both our local and remote `dev` branches are in sync, but two commits behind the remote `main`. We could now sync changes and this time, instead of pushing to remote, we'd pull the changes. But we don't do that, because I want to show you what happens if we forget to pull the changes from the remote!

To illustrate this, we'll checkout the `dev` branch and commit a new change, let's call it `Feature 4`:

<img src=https://i.imgur.com/WebBVcU.png class=blur alt="Feature 4">

What a mess! But don't panic, we can fix this!

All we need to do is to "rebase" the dev branch onto `v0.1.0`. For that, please right-click on the text right beside the `v0.1.0` tag and select `Rebase current branch on this Commit...`. It is important that you click on the commit message, not the tag itself!

<img src=https://i.imgur.com/KqCR61n.png class=blur alt='Rebase'>

<img src=https://i.imgur.com/XPYD1Dn.png class=blur alt="After Rebase">

Better! Now we can add some more features and proceed as before:

- Create some commits on the `dev` branch
- Checkout the `main` branch
- Merge `v0.1.0` into `main` without creating a new commit
- Merge `dev` into `main` and create a new commit
- Push to remote

This time we let the workflow run, wait for it to finish and then click on the cloud symbol of the git graph extension to sync the changes:

<img src=https://i.imgur.com/5agVr7U.png class=blur alt="Sync changes">

Then we can checkout the `main` branch and pull changes and after that also checkout the `dev` branch and pull changes. Now we are all in sync and we are on the `dev` branch, ready to work on the next feature!

<img src=https://i.imgur.com/wIxLp6W.png alt="All synced">

> Congratulations! You have now learned how to handle push conflicts and how to rebase your branches to keep your git graph clean!

While setting up automated deployments requires some initial effort, the long-term benefits are tremendous. You now have a robust CI/CD pipeline that lets you deploy changes with confidence. Your workflow is streamlined, automated, and secure. Best of all, delivering new features to your clients is as simple as pushing your code to Git. The days of manual deployments are behind you - welcome to modern ProcessWire development!
