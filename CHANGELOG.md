## [6.1.0](https://github.com/baumrock/RockMigrations/compare/v6.0.1...v6.1.0) (2024-12-01)


### Features

* add copy/moveRepeaterItems() ([771fd47](https://github.com/baumrock/RockMigrations/commit/771fd471782fb94a2217c3100e540044a7842ea9))
* add createTrait option in runConfigMigrations ([a34bb2a](https://github.com/baumrock/RockMigrations/commit/a34bb2abb424a5a198e41178c37bccd4112a9349))
* add dedicated method runConfigMigrations() ([b0cbd8c](https://github.com/baumrock/RockMigrations/commit/b0cbd8cdf9514abc951f2c93e1d4fb9bd5d55ad6))
* add last run logfile ([50869c9](https://github.com/baumrock/RockMigrations/commit/50869c9b32540c635fe5c533418476f6011fb9eb))
* add option to prevent migrate from running ([2f3ccaa](https://github.com/baumrock/RockMigrations/commit/2f3ccaa98f69e50b9da4aeacfad95e0216a51586))
* add setPageName() method ([e2c53b4](https://github.com/baumrock/RockMigrations/commit/e2c53b4853566bd96ef52a14ba853a6f12014b2a))


### Bug Fixes

* add early exit in createTemplateFromClassfile if tpl constant is not set ([fc8be28](https://github.com/baumrock/RockMigrations/commit/fc8be28b407decc0a0072fa1b9d0b2885d03bbd4))
* allow priorities > 1000 ([a89fa4d](https://github.com/baumrock/RockMigrations/commit/a89fa4d633316598e9e26bd722fee6f4ceb1ae17))
* custom fieldtype returns wrong class after creation ([3993f2f](https://github.com/baumrock/RockMigrations/commit/3993f2f31daa8d4d43e1df22f47751a138e3e58b))
* do not migrate dotfiles ([7195a0f](https://github.com/baumrock/RockMigrations/commit/7195a0fa106ccbc0590c420cee2e5208db33fa30))
* error when no matrixItems passed in $options ([fa76493](https://github.com/baumrock/RockMigrations/commit/fa76493353dc7a9c15fabc48f3f43ab72eed2312))
* issue with iterating fields of repeater ([ad59425](https://github.com/baumrock/RockMigrations/commit/ad59425831c74e32034035afa9d2276f398cc2be))
* prevent errors when page is nullpage in deletepage ([ccce68a](https://github.com/baumrock/RockMigrations/commit/ccce68a2d5f87ae7ad37007fa003c0bdaceba2f6))
* run watchlist migrations in correct order ([47301fa](https://github.com/baumrock/RockMigrations/commit/47301fa724fde85aae04513d24fc9da28e223271))
* support hyphens in template names ([9f07b3f](https://github.com/baumrock/RockMigrations/commit/9f07b3f114cb3970efe333e18395d507d6c82189))
* wrong copy paste ([2633704](https://github.com/baumrock/RockMigrations/commit/26337043e785d5a6a2a5f0be236eeee85b3aeaa9))

## [6.0.1](https://github.com/baumrock/RockMigrations/compare/v6.0.0...v6.0.1) (2024-11-15)


### Bug Fixes

* don't use text as default fieldtype ([46e74d7](https://github.com/baumrock/RockMigrations/commit/46e74d74150a25d567bf5aa09c5df8a4aabe5284))

## [6.0.0](https://github.com/baumrock/RockMigrations/compare/v5.5.0...v6.0.0) (2024-11-13)


### ⚠ BREAKING CHANGES

* create constant trait instead of helper classes

### Features

* add renameField() method ([0b24426](https://github.com/baumrock/RockMigrations/commit/0b24426cf6170658449e40e80041b5c35cbb7b28))
* create constant trait instead of helper classes ([e4ccc74](https://github.com/baumrock/RockMigrations/commit/e4ccc74e1041c70bdfeb935b36da3b36bed61b67))


### Bug Fixes

* don't show once in config table ([37baaaf](https://github.com/baumrock/RockMigrations/commit/37baaaf53b5ba4827480ecb78cba2be2f8368947))

## [5.5.0](https://github.com/baumrock/RockMigrations/compare/v5.4.1...v5.5.0) (2024-11-03)


### Features

* add config migrations ([5ebea63](https://github.com/baumrock/RockMigrations/commit/5ebea6395a16643315011bedce80651786aa4b0b))
* add dump() method ([4eb2e4e](https://github.com/baumrock/RockMigrations/commit/4eb2e4e49859512dc36953164ed5839a279789ce))


### Bug Fixes

* outdated syntax in fieldsetpage stub ([cae4802](https://github.com/baumrock/RockMigrations/commit/cae4802e8903ae1f49d4b6d1df333a6aac11c8d6))
* save once() data in module config not in cache ([e24288e](https://github.com/baumrock/RockMigrations/commit/e24288e9ed251751848e7850add4815de32d54b4))

## [5.4.1](https://github.com/baumrock/RockMigrations/compare/v5.4.0...v5.4.1) (2024-10-16)


### Bug Fixes

* update deploy workflow to checkout@v4 ([5ff3cc2](https://github.com/baumrock/RockMigrations/commit/5ff3cc2890f46301742a5f3271476d97ae534c80))

