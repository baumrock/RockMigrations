## [3.26.1](https://github.com/baumrock/RockMigrations/compare/v3.26.0...v3.26.1) (2023-06-02)


### Bug Fixes

* prevent exception in setFieldData when value 0/empty ([9093b20](https://github.com/baumrock/RockMigrations/commit/9093b20c90dc12873f498ad086d6257e2d9e3358))



# [3.26.0](https://github.com/baumrock/RockMigrations/compare/v3.25.0...v3.26.0) (2023-06-01)


### Bug Fixes

* double linebreaks ([bf07c6b](https://github.com/baumrock/RockMigrations/commit/bf07c6bdff51e9767b1663f931a207407db97bf9))
* magicpages comparing classnames without namespaces ([28b5657](https://github.com/baumrock/RockMigrations/commit/28b5657b5a9dec7ef31c3397d11bd0a83619ec07))
* prevent migrating pageclasses twice ([b3c9add](https://github.com/baumrock/RockMigrations/commit/b3c9addd2372f2773dd9f454dc7cc8e7eb0342fc))
* remove double slash when using $this->path ([aa9502c](https://github.com/baumrock/RockMigrations/commit/aa9502c1b2473c89ebd0784aab6f9d66ba2424ec))
* wirerandom issue on deployment ([29bd145](https://github.com/baumrock/RockMigrations/commit/29bd145c4964d3f5802ee2e8254fd48e97dc8044))


### Features

* add placeBefore to wrapFields() ([906753c](https://github.com/baumrock/RockMigrations/commit/906753c1aa636735150485fadd080e6df6a3f720))
* add random salts to config-local suggestion ([1e7401c](https://github.com/baumrock/RockMigrations/commit/1e7401c1b483e4a1ff913ffcc7ceed90626d75b6))
* add rm-hints to field and template gui ([27b5b25](https://github.com/baumrock/RockMigrations/commit/27b5b25ca87479cd9256e9517b5bad68bacc6a6c))
* add support for parent_id as page path ([e45e430](https://github.com/baumrock/RockMigrations/commit/e45e4306f8a77a84c1c4723f48b63320535884a1))
* imporve createPage for array syntax ([74bd338](https://github.com/baumrock/RockMigrations/commit/74bd3387ad967d002f88321c144cdc6d71ec3a5e))
* improve getTemplate (use ::tpl fallback) ([b6f6d14](https://github.com/baumrock/RockMigrations/commit/b6f6d144ac8441460d7ca5e2dc9fe94d4ff0e662))
* improve isDDEV() ([e4df541](https://github.com/baumrock/RockMigrations/commit/e4df5416b7d316fe0f243bd65cffaa7d9ef7a29a))
* improve setFieldData() method ([2830eed](https://github.com/baumrock/RockMigrations/commit/2830eed9617775a0f7bc2fd4535b0fbd8a5a9cd9))
* use $this->path instead of __DIR__ [#22](https://github.com/baumrock/RockMigrations/issues/22) ([f134e1b](https://github.com/baumrock/RockMigrations/commit/f134e1b63599befb37e543c06c2c1d58f2f50c47))



# [3.25.0](https://github.com/baumrock/RockMigrations/compare/v3.23.1...v3.25.0) (2023-05-04)


### Bug Fixes

* add early exit if tpl is not set ([2d35674](https://github.com/baumrock/RockMigrations/commit/2d35674e03bf95ea187724a2a98397fcfedc969e))
* livereload preventing module installation ([db4d18f](https://github.com/baumrock/RockMigrations/commit/db4d18f4de3b4a0e21e382803e85ddcb705bfef8))
* remove transition that has no effect ([e6524ac](https://github.com/baumrock/RockMigrations/commit/e6524acc165e4e4556c9d44fdfb5544b66140eb3))


### Features

* add $rm->inputfield() helper method ([c548a78](https://github.com/baumrock/RockMigrations/commit/c548a784ef0caeda25dfdabc855bed0ba83be03a))
* add backend stylesheet ([b45bbf2](https://github.com/baumrock/RockMigrations/commit/b45bbf2990c9df0d23c860fc694fd40021e1d5c3))
* add echo() method for cli usage ([f09dd3c](https://github.com/baumrock/RockMigrations/commit/f09dd3c5a1e2f7eae0fe182187b38fed6c241b1d))
* add email field ([7418757](https://github.com/baumrock/RockMigrations/commit/7418757fd0f481e7672a971b243a4dfa04480069))
* add getTplName() method to magicpages ([bfe4fab](https://github.com/baumrock/RockMigrations/commit/bfe4fab5d57132b1ef60a67740eeaf0197e2608a))
* add onChanged() magic method ([04ffe16](https://github.com/baumrock/RockMigrations/commit/04ffe162f97d98ec149d94d0fa9a4306aca57167))
* add pageclass snippet ([f2be261](https://github.com/baumrock/RockMigrations/commit/f2be26102f4ba52200249ff682a236db2845de6b))
* add pageListBadge() helper ([8b202ca](https://github.com/baumrock/RockMigrations/commit/8b202ca7adc9dc2450a8933dd77d465bbfe6379a))
* add PageListQuickActions tweak ([fa8f857](https://github.com/baumrock/RockMigrations/commit/fa8f857cdf091bc571a9aa24cc3430cf0f82c673))
* add PageListShowTemplate tweak ([40c49e8](https://github.com/baumrock/RockMigrations/commit/40c49e8d40a4c2c568826c8b9f6e535438688061))
* add permissions- and access- keys for migrate() ([890c242](https://github.com/baumrock/RockMigrations/commit/890c24287b78faefe25d34951db1b4fd7281cbd1))
* add removeSaveButton() to magicpages ([fc1e56f](https://github.com/baumrock/RockMigrations/commit/fc1e56f17a9bd20977e0d26ddec8f33aa4686b02))
* add removeSubmitActions() ([25701f9](https://github.com/baumrock/RockMigrations/commit/25701f99fbab50c397b0d557b4c5bfaa18c3d893))
* add rm-colorbar class to colorbar ([5d56b2b](https://github.com/baumrock/RockMigrations/commit/5d56b2bdbb4a4d1e4c10d875493d211c12863330))
* add rootVersion() ([3ab7560](https://github.com/baumrock/RockMigrations/commit/3ab756003e697f5fb2af0e2357094613464bd901))
* add support to remove all submit actions ([5c39faa](https://github.com/baumrock/RockMigrations/commit/5c39faae12896d6d2d02580c0b910a0435e95f22))
* add title field to migrated pageclasses ([c5bd95a](https://github.com/baumrock/RockMigrations/commit/c5bd95af8c332d24826b2b47e7a6bda3c2840ce6))
* improve deployment and its logs ([7120e5c](https://github.com/baumrock/RockMigrations/commit/7120e5cba8f6c495bd70d279b9587470c38f55a4))
* improve labels ([64cf73e](https://github.com/baumrock/RockMigrations/commit/64cf73e58428b646be7cc27bf51eb85bdf01ae3f))
* show deployment variables in log ([41bc4e7](https://github.com/baumrock/RockMigrations/commit/41bc4e72d6030ce2607025bbbce537cf72cb73f2))
* update docs and rename deploy workflow ([5bd96f3](https://github.com/baumrock/RockMigrations/commit/5bd96f313d4bd530741bc6651a69330dc446e8c2))



## [3.23.1](https://github.com/baumrock/RockMigrations/compare/v3.23.0...v3.23.1) (2023-03-20)


### Bug Fixes

* revert main branch deployment to old syntax ([0e24db0](https://github.com/baumrock/RockMigrations/commit/0e24db08a55f54e24515688111c7641906bb6e95))



# [3.23.0](https://github.com/baumrock/RockMigrations/compare/v3.22.0...v3.23.0) (2023-03-19)


### Bug Fixes

* set-output warning ([1ae3a5d](https://github.com/baumrock/RockMigrations/commit/1ae3a5de4e7ef7ee557ce3b84af555b94fa0846e))


### Features

* add magic field methods ([60eb61e](https://github.com/baumrock/RockMigrations/commit/60eb61e69a7917a2653eab642c89fbf794cbbc42))
* add PageListShowIds tweak ([22e8d4f](https://github.com/baumrock/RockMigrations/commit/22e8d4fa608d892d7cb964989d724ab8a1a7510e))
* add support for defining a single path ([48febe6](https://github.com/baumrock/RockMigrations/commit/48febe663b80a715159717a5b815599ce54dd338))



