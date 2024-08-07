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

## [4.5.1](https://github.com/baumrock/RockMigrations/compare/v4.5.0...v4.5.1) (2024-07-03)


### Bug Fixes

* get-php not working without config ([e468ecc](https://github.com/baumrock/RockMigrations/commit/e468ecccc11021fef3bd52e55dc29e0a53d06d6f))

## [4.5.0](https://github.com/baumrock/RockMigrations/compare/v4.4.0...v4.5.0) (2024-07-01)


### Features

* add disable procache via config setting ([1454de1](https://github.com/baumrock/RockMigrations/commit/1454de1ac29451fd5a7566ec479e70c50b4dcccc))
* read config from php file ([00d6d8d](https://github.com/baumrock/RockMigrations/commit/00d6d8ddef55b67a889dc45f770ae40161c8a75a))
* show php version in log ([8871426](https://github.com/baumrock/RockMigrations/commit/8871426426d30791e6d5eac16981432eb1ebc78a))
* update deployment to get php version dynamically from the remote server :) ([d17f07f](https://github.com/baumrock/RockMigrations/commit/d17f07fcd151c64cc4b50fe0bedbebfce05f607d))


### Bug Fixes

* make sure config returns an array ([e37ea97](https://github.com/baumrock/RockMigrations/commit/e37ea97e3d31be05be73a19fe68784cd186e6946))

