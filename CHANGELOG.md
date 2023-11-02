# [3.32.0](https://github.com/baumrock/RockMigrations/compare/v3.31.1...v3.32.0) (2023-11-02)


### Bug Fixes

* empty previewPassword causes no redirect ([c74596f](https://github.com/baumrock/RockMigrations/commit/c74596ff03c96c6b19fe4664ff828bf3c242e34b))
* installModule() does not actually install the module when run the 1st time, but only downloads it [#29](https://github.com/baumrock/RockMigrations/issues/29) ([ce59d2d](https://github.com/baumrock/RockMigrations/commit/ce59d2d6414f3c410bf2275b2641caaf28bf8e1b))
* move livereload settings back to RockFrontend ([37eb50c](https://github.com/baumrock/RockMigrations/commit/37eb50cc9125a0568d74f0d9323b45ac163482b4))
* rewrite setPageNameFromField method ([dbd900e](https://github.com/baumrock/RockMigrations/commit/dbd900ec28b7feb6080e8f63711503ed0db0b58f))
* settings from config file not properly applied ([2aa8ea5](https://github.com/baumrock/RockMigrations/commit/2aa8ea508449efeb14f55c1fa146ed44653935b8))
* tweak info icon falsely on new line ([d738b6b](https://github.com/baumrock/RockMigrations/commit/d738b6b34fe87a6f228f7b017f3b8a871fb1a426))


### Features

* add CopyFieldNames tweak ([822b36b](https://github.com/baumrock/RockMigrations/commit/822b36bbd2bf7eca83a4293d4fcebf4f3e29a510))
* add hideFromGuests() + preview password feature ([518788f](https://github.com/baumrock/RockMigrations/commit/518788f1bc33cf1ca2a661f52bcac4eac5300ec3))
* improve PR from ivan to use internal refresh method ([0362f33](https://github.com/baumrock/RockMigrations/commit/0362f33fcf9bf72d19b9ef792c6a324119f2cdd4))
* show current fileconfig on settings page ([4fbb444](https://github.com/baumrock/RockMigrations/commit/4fbb4448157f5cf4a77036f0de3174d2db4d8719))



## [3.31.1](https://github.com/baumrock/RockMigrations/compare/v3.31.0...v3.31.1) (2023-10-09)


### Bug Fixes

* PHP Warning: Invalid argument supplied for foreach() in RockMigrations.module.php:3510 ([bb24d33](https://github.com/baumrock/RockMigrations/commit/bb24d330e77cb2baceec9479f029580fce8ce5a9))



# [3.31.0](https://github.com/baumrock/RockMigrations/compare/v3.30.0...v3.31.0) (2023-10-05)


### Bug Fixes

* docblock ([af3572e](https://github.com/baumrock/RockMigrations/commit/af3572ef772076b82c5c6a79103e1d1792190f42))
* remove project-specific $rm->echo() ([b242f56](https://github.com/baumrock/RockMigrations/commit/b242f563f6830e55ba295d56b68d94b8a723bb58))
* remove unused line ([070c28c](https://github.com/baumrock/RockMigrations/commit/070c28cdf288d6fb025c0f73532e696cdaefa428))


### Features

* add tweak to set all languages of new pages active by default ([98f9855](https://github.com/baumrock/RockMigrations/commit/98f98552d62922a54f9d7180d61575cf8c147d4d))



# [3.30.0](https://github.com/baumrock/RockMigrations/compare/v3.29.0...v3.30.0) (2023-09-11)


### Features

* add minify param in saveCSS() ([60f9167](https://github.com/baumrock/RockMigrations/commit/60f9167537b77612d367d9486228ff1df992c095))
* cleanup caches table on deploy ([85c2458](https://github.com/baumrock/RockMigrations/commit/85c24588a9d0a0552485a008fe624429427db988))



# [3.29.0](https://github.com/baumrock/RockMigrations/compare/v3.27.0...v3.29.0) (2023-08-11)


### Bug Fixes

* catch 404 page not found error on deployment ([e67bebd](https://github.com/baumrock/RockMigrations/commit/e67bebd7382ae5b7a491b6f8c783c1d157524ff4))
* jquery deprecation warning for window.load() ([c626d5b](https://github.com/baumrock/RockMigrations/commit/c626d5bfbc2a8b731524921479d08e653f550d7e))
* pageListBadge showing when empty ([3644e6a](https://github.com/baumrock/RockMigrations/commit/3644e6a85263ba879c10fd2a208d47c589a9946d))
* remove language tab fix ([4a1061b](https://github.com/baumrock/RockMigrations/commit/4a1061bcf1ba5ab64140e96ec204ceccacbe22b7))
* renderTable not showing data and wrong asset path on windows ([24a9d9d](https://github.com/baumrock/RockMigrations/commit/24a9d9d5ab4f2e306f7e0b948692cf59da05e282))
* renderTable not showing some values ([bce2df8](https://github.com/baumrock/RockMigrations/commit/bce2df88da20d894f9e4c719ff8e4355769128a0))


### Features

* add checkbox to force livereload on module config pages ([a52a550](https://github.com/baumrock/RockMigrations/commit/a52a5504d48474502927e162d7412a4c9ff54eec))
* add getFile() method ([ea94430](https://github.com/baumrock/RockMigrations/commit/ea94430431f141556dd2191bce4c56eaaa76ced8))
* add homeTemplate() method ([cbd0c07](https://github.com/baumrock/RockMigrations/commit/cbd0c07e04adb20ee262ab6fef2f315723eb10d1))
* add new pageClassLoader :) ([480b06c](https://github.com/baumrock/RockMigrations/commit/480b06cf52241f8ab3eaaa360a18f520d2080189))
* add path helper method ([29e8740](https://github.com/baumrock/RockMigrations/commit/29e8740d8d013e2721555a9f3c3cd51997230ce2))
* add redirect logger Tweak ([6003cf5](https://github.com/baumrock/RockMigrations/commit/6003cf593b878b80937fa9708f0172a8f9fcfed9))
* add renderTable helper ([eb3ee9c](https://github.com/baumrock/RockMigrations/commit/eb3ee9cdc7f535dcf040f1ca8352eb91daf6ef46))
* add support for template_ids ([14489ad](https://github.com/baumrock/RockMigrations/commit/14489add2c971c9e6b75bf23986d5338ab7e3343))
* improve new pageClassLoader ([e99b80c](https://github.com/baumrock/RockMigrations/commit/e99b80c494e25331705767637d2ceaab152bab7e))



