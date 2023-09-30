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



# [3.27.0](https://github.com/baumrock/RockMigrations/compare/v3.26.1...v3.27.0) (2023-07-07)


### Bug Fixes

* createUser returning false PR[#24](https://github.com/baumrock/RockMigrations/issues/24) ([fb18156](https://github.com/baumrock/RockMigrations/commit/fb18156f929d549c3c160595b75bcd4b00f72162))


### Features

* add deployment hooks ([fcefd1f](https://github.com/baumrock/RockMigrations/commit/fcefd1ffdca1b6b3d0e01cace195961d5f96450d))
* add PageListAutoExpand to default profile ([68af9f9](https://github.com/baumrock/RockMigrations/commit/68af9f98e2fdfd0cdf4ad4baf585ede91b6c72b4))
* add rockhsell demo command ([cce0d06](https://github.com/baumrock/RockMigrations/commit/cce0d062d422a7e2b58815c61c5433eee0f65c71))
* add support for getTplName() syntax ([c01630f](https://github.com/baumrock/RockMigrations/commit/c01630f0f71627d72c3fa1fbc1bfafa213902134))
* add time constants ([17ab452](https://github.com/baumrock/RockMigrations/commit/17ab452a9d39cb0883b2ac447e77bd5aea11b3e2))
* make github action fail on error in migration ([7499223](https://github.com/baumrock/RockMigrations/commit/7499223c9491c4155b3287efd69aff5554aec909))



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



