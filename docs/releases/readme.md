# Auto-Release

RockMigrations comes with a GitHub Workflow file that you can use to automatically create releases of your modules or projects.

All you have to do is to add this file to your `.github/workflows` directory:

```yaml
name: Auto-Release

# create a release when a commit is pushed to the main branch
on:
  push:
    branches:
      - main

# run the auto-release workflow from rockmigrations
jobs:
  auto-release:
    uses: baumrock/RockMigrations/.github/workflows/auto-release.yml@main
    # optionally set the email of the committer
    # with:
    #   email: "foo@example.com"
    secrets:
      token: ${{ secrets.CI_TOKEN }}
```

This will use the https://www.conventionalcommits.org/en/v1.0.0/ specification for your commits.

Basically you just have to prefix your commit messages with one of the following keywords:

- `feat: ...` for features
- `fix: ...` for bug fixes
- `chore: ...` for other changes

All `fix` commits will bump the version number from `x.y.z` to `x.y.z+1`. All `feat` commits will bump the version from `x.y.z` to `x.y+1.0`.

Major version bumps are created when a breaking change was introduced, which you can specify by adding a `!` to the end of the commit key: `feat!: whatever`.
