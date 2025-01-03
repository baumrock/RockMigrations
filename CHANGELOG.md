## [6.5.0](https://github.com/baumrock/RockMigrations/compare/v6.4.0...v6.5.0) (2025-01-03)


### Features

* list allowed ips (hide from guests) on the module settings screen ([11a93eb](https://github.com/baumrock/RockMigrations/commit/11a93eb7f0f4800aa846378d763502c10a1f0ddd))


### Bug Fixes

* remove inline mode from tinymce settings file ([d67d1e4](https://github.com/baumrock/RockMigrations/commit/d67d1e43ed754901c8856761d0bd5c7c3a85ba97))
* remove log (causing warning) ([127610b](https://github.com/baumrock/RockMigrations/commit/127610b5dff9717d2239053ba1d7304e4a4f8e4d))

## [6.4.0](https://github.com/baumrock/RockMigrations/compare/v6.3.0...v6.4.0) (2024-12-15)


### Features

* add support for constants helper in site-config-migrations ([b18bce8](https://github.com/baumrock/RockMigrations/commit/b18bce8b0ec4efce4dc7589c952920825f942f30))

## [6.3.0](https://github.com/baumrock/RockMigrations/compare/v6.2.0...v6.3.0) (2024-12-14)


### Features

* add default deploy.php ([3d8942c](https://github.com/baumrock/RockMigrations/commit/3d8942c6ef2261d2697c4281495dbb4a92f01ce3))
* pass deployment through deploy.php in RM folder ([96aa3cd](https://github.com/baumrock/RockMigrations/commit/96aa3cd7edbab98e438e3c9af6f0fe8ab49fb35e))

## [6.2.0](https://github.com/baumrock/RockMigrations/compare/v6.1.0...v6.2.0) (2024-12-09)


### Features

* add missing method setFieldLanguageValue() ([e5ad13a](https://github.com/baumrock/RockMigrations/commit/e5ad13a95d6cd2e71d34d44bb2d27f015e8d9d2f))
* improve PR from ivan to use internal refresh method ([da0b08b](https://github.com/baumrock/RockMigrations/commit/da0b08b9cc65ee5573a5041971fe6ec575130b98))


### Bug Fixes

* installModule() does not actually install the module when run the 1st time, but only downloads it [#29](https://github.com/baumrock/RockMigrations/issues/29) ([78cf409](https://github.com/baumrock/RockMigrations/commit/78cf4098f1d1a5542261c4280e4e7408288f03b4))
* make setFieldLanguageValue() get the field from the page object and set language value correctly ([86eb045](https://github.com/baumrock/RockMigrations/commit/86eb045c72bec49a795b764b5a9d64d9821d3250))
* rename deploy command ([5a41e0a](https://github.com/baumrock/RockMigrations/commit/5a41e0a81fd59dee33adceaf87da352dbe02bd60))

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

