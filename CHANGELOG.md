## [4.5.0](https://github.com/baumrock/RockMigrations/compare/v4.4.0...v4.5.0) (2024-07-01)


### Features

* add disable procache via config setting ([1454de1](https://github.com/baumrock/RockMigrations/commit/1454de1ac29451fd5a7566ec479e70c50b4dcccc))
* read config from php file ([00d6d8d](https://github.com/baumrock/RockMigrations/commit/00d6d8ddef55b67a889dc45f770ae40161c8a75a))
* show php version in log ([8871426](https://github.com/baumrock/RockMigrations/commit/8871426426d30791e6d5eac16981432eb1ebc78a))
* update deployment to get php version dynamically from the remote server :) ([d17f07f](https://github.com/baumrock/RockMigrations/commit/d17f07fcd151c64cc4b50fe0bedbebfce05f607d))


### Bug Fixes

* make sure config returns an array ([e37ea97](https://github.com/baumrock/RockMigrations/commit/e37ea97e3d31be05be73a19fe68784cd186e6946))

## [4.4.0](https://github.com/baumrock/RockMigrations/compare/v4.3.0...v4.4.0) (2024-06-03)


### Features

* add money snippet ([bdc0ea2](https://github.com/baumrock/RockMigrations/commit/bdc0ea28e69d2c1bff7c1d3ab67c8a2fd42b3cfa))
* add snippet for RockGrid fields ([ada1cf9](https://github.com/baumrock/RockMigrations/commit/ada1cf948b33e962dba7d70e78fbb88b28fe0dba))
* improve visibility of pagelist tweaks ([088a310](https://github.com/baumrock/RockMigrations/commit/088a310d83b8b43c5821f5a36983edb70ad9df15))


### Bug Fixes

* add expirenever for RM cache() ([871544b](https://github.com/baumrock/RockMigrations/commit/871544bd2a8391ad299bcf74ca9bd49318fc80de))
* label empty in renderTable ([c800494](https://github.com/baumrock/RockMigrations/commit/c80049497641d246c247aa2583910ec066ce3a74))

## [4.3.0](https://github.com/baumrock/RockMigrations/compare/v4.2.0...v4.3.0) (2024-05-06)


### Features

* add config setting to disable magic methods [#59](https://github.com/baumrock/RockMigrations/issues/59) ([23b1272](https://github.com/baumrock/RockMigrations/commit/23b127290bad52b16eb5f28f5e93ac324802d1e3))
* add hidePageFromTree method ([e8c6681](https://github.com/baumrock/RockMigrations/commit/e8c66812062260885e5462f8901d9ba70443f93a))
* improve copied code (add verbose comments) ([8c00534](https://github.com/baumrock/RockMigrations/commit/8c005340a123cb7bb68322f2456aabc2de9839e1))
* improve renderTable() method ([09a4941](https://github.com/baumrock/RockMigrations/commit/09a49416dc0d4f050f862cb807e7cb72871b1754))


### Bug Fixes

* empty config info after installation ([00369e0](https://github.com/baumrock/RockMigrations/commit/00369e0037e765264f40562c1c71145c63b733d6))
* wrong path for robots.txt stub ([ee3561e](https://github.com/baumrock/RockMigrations/commit/ee3561e3e4a730a4a0508d3f461bc01cf3d7671c))

## [4.2.0](https://github.com/baumrock/RockMigrations/compare/v4.0.0...v4.2.0) (2024-03-28)


### Features

* add new config info screen and improve merging file config into module config (also fixes syncSnippets) ([eba2dfe](https://github.com/baumrock/RockMigrations/commit/eba2dfee03cb872412b8c5c5b0e7d352dc1d385b))
* add nodelete() method for deployments ([ea74cc8](https://github.com/baumrock/RockMigrations/commit/ea74cc8e66eb88627253abf446f37ac3ef60beb8))
* add once() feature + docs ([d1573d6](https://github.com/baumrock/RockMigrations/commit/d1573d6d46fe3c865d1efc1d5defc4208346d752))
* add RPB snippets for settings ([6ed6d35](https://github.com/baumrock/RockMigrations/commit/6ed6d35613853c73a0c27fd11c9f2980b9a92082))
* add shift-click copy feature for settings ([e958d3d](https://github.com/baumrock/RockMigrations/commit/e958d3d5d232cac996efe4f9757476751223211f))
* add sql() method to wipe caches on deploy ([b1bd9a4](https://github.com/baumrock/RockMigrations/commit/b1bd9a4eaa11e56cf20131cd4c6ae079c32acfc2))
* add support to minify all files in directory ([53cff11](https://github.com/baumrock/RockMigrations/commit/53cff113c5cf06ef03d011a1988d5b7d0e6fb95e))
* add trace what prevents publish ([fa9d0ab](https://github.com/baumrock/RockMigrations/commit/fa9d0abadfe78ee87350e18b6252558d63c55d72))
* catch autoload errors ([8f022b5](https://github.com/baumrock/RockMigrations/commit/8f022b5f3d47fecfe0bc595c6c2f46ad1a8a206d))
* improve deployment ([90a993c](https://github.com/baumrock/RockMigrations/commit/90a993c597cf5155f5093d1c78d1279a4ce19203))
* improve rockshell api and add docs ([6194f19](https://github.com/baumrock/RockMigrations/commit/6194f19598c3b35e26931f61742c6670bf291af0))
* merge fieldset open+close snippet ([4f5f6a4](https://github.com/baumrock/RockMigrations/commit/4f5f6a4ae4f7e2d834e0e029b7f08f4f8d11dc33))
* set file/folder permissions via rsync ([8f4de29](https://github.com/baumrock/RockMigrations/commit/8f4de299a9572ed4a6e91317b6197ac0dbccee69))
* tinymce paste as plaintext by default ([5748e67](https://github.com/baumrock/RockMigrations/commit/5748e6787fd1922dee0c5d0777cc3cffa5ae42c4))
* update copy code on click ([ea9a6f8](https://github.com/baumrock/RockMigrations/commit/ea9a6f8b475f4a1935d0374f139c34185bfef735))


### Bug Fixes

* add entity encoder to migrations code info [#51](https://github.com/baumrock/RockMigrations/issues/51) ([4b0c296](https://github.com/baumrock/RockMigrations/commit/4b0c2963497d9fec013117159e2df466e8c083d4))
* button without type breaks enter-submit ([8104b2b](https://github.com/baumrock/RockMigrations/commit/8104b2b846aa2e92ebd6e1f2732d60d7ad5fea75))
* create snippets folder if it does not exist ([c54a38a](https://github.com/baumrock/RockMigrations/commit/c54a38a0d915479ebd8f863835cae4859ade8619))
* final config not showing FALSE values ([b20d88a](https://github.com/baumrock/RockMigrations/commit/b20d88a3e5ca5d8d87bae9ff193b69a5c36c1237))
* make sure magicpage cache is an array ([d6ca7e3](https://github.com/baumrock/RockMigrations/commit/d6ca7e31c2c9b16956926a41c113716960d92f1a))
* pagelist id + template showing up in asm select ([291677b](https://github.com/baumrock/RockMigrations/commit/291677b51d7c07b4fec52f352f2467899edea01e))
* prevent fatal error on deploy if pageclass removed ([162a0b7](https://github.com/baumrock/RockMigrations/commit/162a0b7762300ebe3f1e5648b207db18281485e0))
* remove unused line ([da721f2](https://github.com/baumrock/RockMigrations/commit/da721f2100061b40790a28cf2524a917255b587a))
* rockmigrations() causing error on deploy ([7f80154](https://github.com/baumrock/RockMigrations/commit/7f80154ea7ec72872cdf4eaa1696beeb0f1f2362))
* vscode snippets not created ([b5e2592](https://github.com/baumrock/RockMigrations/commit/b5e25928ea17e073a71325cee7b253654dbdd8bb))

## [4.0.0](https://github.com/baumrock/RockMigrations/compare/v3.36.1...v4.0.0) (2024-02-10)


### ⚠ BREAKING CHANGES

* remove ImageMurl tweak in favour of Latte Filter

### Bug Fixes

* remove ImageMurl tweak in favour of Latte Filter ([c706ece](https://github.com/baumrock/RockMigrations/commit/c706ecebb4966c19e0fd5d7daafba42525de366e))

