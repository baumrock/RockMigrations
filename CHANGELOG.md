# [3.33.0](https://github.com/baumrock/RockMigrations/compare/v3.32.0...v3.33.0) (2023-12-03)


### Bug Fixes

* createPage() triggering page save all the time ([456c0b1](https://github.com/baumrock/RockMigrations/commit/456c0b1449a8b15d3a4b715bbfe39a4bc734f1e0))
* dont add page id + template in dropdown menu ([0730ee3](https://github.com/baumrock/RockMigrations/commit/0730ee300bcb87d383bdfae647442834def63c47))
* is_file() causing error when $path is null ([7e64380](https://github.com/baumrock/RockMigrations/commit/7e643806c3bf8feb5b04b766c20e962521c4f74b))
* ready() not triggered in magicpages ([f4f09bc](https://github.com/baumrock/RockMigrations/commit/f4f09bcba34f4cfa7078bc94cd5a99589f923f49))
* title not updating when using createPage() ([147230c](https://github.com/baumrock/RockMigrations/commit/147230c614ddf1ac97c503e90338f7d652fdccee))
* vscode links not working with latest vscode ([ec10745](https://github.com/baumrock/RockMigrations/commit/ec1074516641af2dd1f410be8c843d716ace1cf0))


### Features

* add DelayedImageVariations to default profile ([a063964](https://github.com/baumrock/RockMigrations/commit/a063964635d19c64a69318470b25b75fde50c51d))
* add keepCSS param in saveCSS method ([be46e16](https://github.com/baumrock/RockMigrations/commit/be46e1665e1930733cc192e833f895b2a42b2241))
* add sql query snippet ([547f0ef](https://github.com/baumrock/RockMigrations/commit/547f0ef894c10938c076e3973279428093fbb057))



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



