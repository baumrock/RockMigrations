## [5.3.0](https://github.com/baumrock/RockMigrations/compare/v5.2.0...v5.3.0) (2024-10-01)


### Features

* add a condition for setPageNameFromField ([fc582b2](https://github.com/baumrock/RockMigrations/commit/fc582b2e23d97c6505ead3ff0dd70dc4befdcdaa))
* add GET_PHP_COMMAND variable for deployments ([3bba845](https://github.com/baumrock/RockMigrations/commit/3bba8452ceefe42d66c0c72e7eee8741e9a80a79))
* add indent() for better logs ([f611675](https://github.com/baumrock/RockMigrations/commit/f61167515dc391e92ebb74795bb1900bd75eefec))
* add refresh() before installing new module ([476963f](https://github.com/baumrock/RockMigrations/commit/476963f2885c512a76ed7ada78ea7d44c297edd9))
* trigger migrateModule() after module install ([4c09a51](https://github.com/baumrock/RockMigrations/commit/4c09a51540177175dfafad27f27def45c4d8a14d))

## [5.2.0](https://github.com/baumrock/RockMigrations/compare/v5.1.0...v5.2.0) (2024-09-02)


### Features

* add snippet for rockicons field ([019053e](https://github.com/baumrock/RockMigrations/commit/019053eea8f99f9ff6febeaa69408e843760942c))
* send preview password to JS for multisite ([a84b47a](https://github.com/baumrock/RockMigrations/commit/a84b47aba495d285379e1327f94b3e3e2c227224))

## [5.1.0](https://github.com/baumrock/RockMigrations/compare/v5.0.1...v5.1.0) (2024-08-01)


### Features

* add auto-refresh on module install ([e0474fa](https://github.com/baumrock/RockMigrations/commit/e0474fa8a018272c5dfc7accf7b563d7cbed1b3b))
* add getTemplateIds() method ([847f3f8](https://github.com/baumrock/RockMigrations/commit/847f3f86b66c027b0067ec564670a807ebb0cf8b))
* add onlyDebug flag to saveCSS() ([012d3d2](https://github.com/baumrock/RockMigrations/commit/012d3d2adc46f73c9595dd2fe36e92c0c6aa09b3))
* add option to manually set the php command in yaml ([5b6e9c0](https://github.com/baumrock/RockMigrations/commit/5b6e9c02aff6e62f26887b9dcda0a062fee4aafc))
* allow guest views from IP additional to session ([0d0e5cd](https://github.com/baumrock/RockMigrations/commit/0d0e5cd31ec629243c2bdee2f6c0206fb626f318))


### Bug Fixes

* add early exit if user is not defined ([1ed8261](https://github.com/baumrock/RockMigrations/commit/1ed8261652e4553f1168033b33145db385aed3b8))
* remove default value for php command ([36795a4](https://github.com/baumrock/RockMigrations/commit/36795a49be2b40b1b4d552abd404123453cb6912))
* setAndSave must be after page has been saved once ([b16a937](https://github.com/baumrock/RockMigrations/commit/b16a93729544625771394b2d469cc646b4dee843))
* update ip cache on every request ([e9db692](https://github.com/baumrock/RockMigrations/commit/e9db6927615fa9ccdce638aab6c3f172eb95322a))
* wrong variable name ([3464198](https://github.com/baumrock/RockMigrations/commit/3464198c25c428a4eacb324b7bfe6199674eda30))

## [5.0.1](https://github.com/baumrock/RockMigrations/compare/v5.0.0...v5.0.1) (2024-07-22)


### Bug Fixes

* use timestamp instead of deploy id ([c1f0119](https://github.com/baumrock/RockMigrations/commit/c1f011977d31c22112fd0020088070fb04b83cc8))

## [5.0.0](https://github.com/baumrock/RockMigrations/compare/v4.5.1...v5.0.0) (2024-07-12)


### ⚠ BREAKING CHANGES

* share sessions folder by default on deploy

### Miscellaneous Chores

* share sessions folder by default on deploy ([3b349e8](https://github.com/baumrock/RockMigrations/commit/3b349e8a68cf3e687cb0ccda7662752435f2941f))

