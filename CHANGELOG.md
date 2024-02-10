## [4.0.0](https://github.com/baumrock/RockMigrations/compare/v3.36.1...v4.0.0) (2024-02-10)


### ⚠ BREAKING CHANGES

* remove ImageMurl tweak in favour of Latte Filter

### Bug Fixes

* remove ImageMurl tweak in favour of Latte Filter ([c706ece](https://github.com/baumrock/RockMigrations/commit/c706ecebb4966c19e0fd5d7daafba42525de366e))

## [3.36.1](https://github.com/baumrock/RockMigrations/compare/v3.36.0...v3.36.1) (2024-02-05)


### Bug Fixes

* missing check for deleteOnSave in cache() ([a764c5a](https://github.com/baumrock/RockMigrations/commit/a764c5af1fc7a74a6b0196db0f34fb0f3c8adcdc))

## [3.36.0](https://github.com/baumrock/RockMigrations/compare/v3.35.5...v3.36.0) (2024-02-04)


### Features

* add $pagefile->isImage property hook ([43a06de](https://github.com/baumrock/RockMigrations/commit/43a06de5863cb49a6ed4429dd174fa1d6ae16792))
* add $pagefile->resizedUrl(width, height) ([50f9d0f](https://github.com/baumrock/RockMigrations/commit/50f9d0fc6111d419098cb42a3fadc2d611d6c89e))
* add cache that resets on page save ([9423821](https://github.com/baumrock/RockMigrations/commit/94238210ba6f81395e03e4d31e15baa699009a71))
* add logs for fileOnDemand ([71fb270](https://github.com/baumrock/RockMigrations/commit/71fb2701010eb62b23d189a6020ed13e1849ca0c))
* add murl pagefile property tweak ([6cf026b](https://github.com/baumrock/RockMigrations/commit/6cf026bde528b0566862fe12a587bc31f4dce5a8))
* add new autoloader for traits/pageClasses/repeaterPageClasses ([7a8d550](https://github.com/baumrock/RockMigrations/commit/7a8d550a711c1f563555817c6c5b12cc529ba991))
* add preventPublish() method ([de7a39b](https://github.com/baumrock/RockMigrations/commit/de7a39b7ad0e58ed7f174db8954672f3dcbf103e))
* add site module macro ([992862a](https://github.com/baumrock/RockMigrations/commit/992862ae5f3918ad1b2c4706fdfbef554ef6ebee))
* add snippet ([7c529cf](https://github.com/baumrock/RockMigrations/commit/7c529cf5eb5034f8852a270d5728006172f19420))
* add snippet for module version from package.json ([9f00b1b](https://github.com/baumrock/RockMigrations/commit/9f00b1ba4726b3997e052300582b9049c76952a2))
* add variable typehint snippet ([ed3a43c](https://github.com/baumrock/RockMigrations/commit/ed3a43c2e885da720e6392063557faf9f9d798ce))
* docs about minify() ([20f1e19](https://github.com/baumrock/RockMigrations/commit/20f1e19761bb8b45462ee1a632ba217a87d5eeaf))
* don't use cache on cli usage at all to avoid dependency problems ([82b0e9c](https://github.com/baumrock/RockMigrations/commit/82b0e9c1fb50edbdaf680325e8eba37de943655a))
* improve settings page ([e232a33](https://github.com/baumrock/RockMigrations/commit/e232a33c2abbfef5f2e34f3dc6b1a37f7d261b6c))
* update Site.module.php stub ([89ee09b](https://github.com/baumrock/RockMigrations/commit/89ee09bfa1f1b021663ace950badb66ae1221dc6))
* use DDEV_APPROOT for localRootPath ([e77a3b1](https://github.com/baumrock/RockMigrations/commit/e77a3b1ab4b416443cf7911a64ac88b89b64d57c))


### Bug Fixes

* catch error if file is null ([880ff03](https://github.com/baumrock/RockMigrations/commit/880ff035ee982cddac9d1a6313c0fedaa36cbcec))
* dollar signs in snippets missing ([d8d636d](https://github.com/baumrock/RockMigrations/commit/d8d636de632bf3f972500a6bb6f6dea7152152c2))
* don't use filesondemand on cli ([67f3388](https://github.com/baumrock/RockMigrations/commit/67f3388f4d37144dc0e6cd932e8c5d0cd3c6f41f))
* prevent process module auto-loading pageclasses ([aa78554](https://github.com/baumrock/RockMigrations/commit/aa7855435bddd1aab648858c255642ebc15101da))

## [3.35.5](https://github.com/baumrock/RockMigrations/compare/v3.35.4...v3.35.5) (2024-01-17)


### Bug Fixes

* remove absolete title-field check ([5f2a944](https://github.com/baumrock/RockMigrations/commit/5f2a9445643b09bd4a03da3c76269fb43f050365))

## [3.35.4](https://github.com/baumrock/RockMigrations/compare/v3.35.3...v3.35.4) (2024-01-17)


### Bug Fixes

* fix issue with removing global fields ([b7e4182](https://github.com/baumrock/RockMigrations/commit/b7e418249137a7b7d588ceabf426bb59a0a10332))

