## [6.8.0](https://github.com/baumrock/RockMigrations/compare/v6.7.1...v6.8.0) (2025-03-12)


### Features

* add _END constant for fieldsetopen automatically ([2c51632](https://github.com/baumrock/RockMigrations/commit/2c51632f12adb7620af485e94daea6692cbcac85))


### Bug Fixes

* lastrunlogfile is null (issue Error when upgrading from v6.0.1 to 6.1.0 or higher [#75](https://github.com/baumrock/RockMigrations/issues/75)) ([a6fe22d](https://github.com/baumrock/RockMigrations/commit/a6fe22d45c33fbb908050c0b1bbedd536c9065c4))

## [6.7.1](https://github.com/baumrock/RockMigrations/compare/v6.7.0...v6.7.1) (2025-02-02)


### Bug Fixes

* prevent template_id=0 in field code of repeater items ([8f9bd47](https://github.com/baumrock/RockMigrations/commit/8f9bd471dcc4e9f47d0949af3be3cc4586567abd))

## [6.7.0](https://github.com/baumrock/RockMigrations/compare/v6.6.0...v6.7.0) (2025-01-13)


### Features

* add configMigrations() method to temporarily enable/disable config migrations ([10702f3](https://github.com/baumrock/RockMigrations/commit/10702f3f05919424c0544782f7f4d2f8d36a7454))
* add inputfield options to page field snippet ([617d271](https://github.com/baumrock/RockMigrations/commit/617d27167ee55b406500f7aeca33a6a7862c4824))
* update all snippets to use config migrations syntax ([46f2fce](https://github.com/baumrock/RockMigrations/commit/46f2fcec7c79e4898fb93c25c26ed57c29b1283e))


### Bug Fixes

* remove unnecessary fieldset-close snippet ([8acf49e](https://github.com/baumrock/RockMigrations/commit/8acf49e852862ecde4c4dd59deeb948d47636f90))

## [6.6.0](https://github.com/baumrock/RockMigrations/compare/v6.5.0...v6.6.0) (2025-01-11)


### Features

* add config migration hooks ❤️ ([d70b271](https://github.com/baumrock/RockMigrations/commit/d70b271b1dc52d8fb03b3ba76b07838bceaadae9))
* add support to directly minify a less file with $rm->minify(...) ([2257788](https://github.com/baumrock/RockMigrations/commit/225778806898669b6b7c7bb839311ed0ba9b306d))
* auto run config migrations on module install ([a13a8fc](https://github.com/baumrock/RockMigrations/commit/a13a8fc4759085d2724705d40798e5e36a055707))


### Bug Fixes

* access rootPath() not root ([7c84b6f](https://github.com/baumrock/RockMigrations/commit/7c84b6f5bad82e64988551a77503b490a2099022))
* make sure hookfiles get not written into constants files ([5741d98](https://github.com/baumrock/RockMigrations/commit/5741d98cedfab80233024a3f568f34daf8460312))

## [6.5.0](https://github.com/baumrock/RockMigrations/compare/v6.4.0...v6.5.0) (2025-01-03)


### Features

* list allowed ips (hide from guests) on the module settings screen ([11a93eb](https://github.com/baumrock/RockMigrations/commit/11a93eb7f0f4800aa846378d763502c10a1f0ddd))


### Bug Fixes

* remove inline mode from tinymce settings file ([d67d1e4](https://github.com/baumrock/RockMigrations/commit/d67d1e43ed754901c8856761d0bd5c7c3a85ba97))
* remove log (causing warning) ([127610b](https://github.com/baumrock/RockMigrations/commit/127610b5dff9717d2239053ba1d7304e4a4f8e4d))

