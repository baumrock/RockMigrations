## [3.35.1](https://github.com/baumrock/RockMigrations/compare/v3.35.0...v3.35.1) (2024-01-07)


### Bug Fixes

* too much refactoring :D ([e816cea](https://github.com/baumrock/RockMigrations/commit/e816cea53a02eb7f14e321a8dd2ba6219c421261))

## [3.35.0](https://github.com/baumrock/RockMigrations/compare/v3.34.0...v3.35.0) (2024-01-07)


### Features

* add auto-load feature for custom page classes in modules ([accd253](https://github.com/baumrock/RockMigrations/commit/accd253bff1320299b498cb4b6b86bbb57c1b63a))
* add cache() method ([56d5789](https://github.com/baumrock/RockMigrations/commit/56d57891a104ba37b97759e23aefb9efa4b512d7))
* add copy to clipboard for pagelist id+tpl ([2e4a077](https://github.com/baumrock/RockMigrations/commit/2e4a077aa0c3bc41b4e26837793d6aed8515f125))
* add snippets ([2ec759e](https://github.com/baumrock/RockMigrations/commit/2ec759e270fc760763364d49a5c20a14af20ab56))
* create VSCode snippets from single files ([7dd15de](https://github.com/baumrock/RockMigrations/commit/7dd15ded83be0dee90c9c3ce0f6082db88ea5c48))
* move settings macro to dedicated RockSettings module ([6aa8e80](https://github.com/baumrock/RockMigrations/commit/6aa8e8064fcf1be014dee8d75c9280c1c5103763))

## [3.34.0](https://github.com/baumrock/RockMigrations/compare/v3.33.0...v3.34.0) (2024-01-03)


### Features

* add 2s timeout for filesondemand ([6f3d17c](https://github.com/baumrock/RockMigrations/commit/6f3d17cd8f71d157e3c978c4d2567d226c64274c))
* add custom pageListLabel() for MagicPages ([be35030](https://github.com/baumrock/RockMigrations/commit/be3503074879bfee8c0915edf308adce8de0dc40))
* add deployment rockshell command ([3946444](https://github.com/baumrock/RockMigrations/commit/3946444f7b60ed3e3232c147303e37c9edf5284b))
* add field declaration snippet ([dd6b68d](https://github.com/baumrock/RockMigrations/commit/dd6b68d1110dc0dfa546ae0d29b50c6e5dfe7df5))
* add keyhelp php version to snippet ([99f9eb4](https://github.com/baumrock/RockMigrations/commit/99f9eb4bdab5a5bc1cccad7fb983746046590f8b))
* add macros feature ([09e1ca1](https://github.com/baumrock/RockMigrations/commit/09e1ca1bc2d4ea6c4df47b534b72799f0c2b6b2c))
* add rm-badge class to pagelist badge ([cb1f948](https://github.com/baumrock/RockMigrations/commit/cb1f948c9b04cef58680049dfd1e0bb30bb94a67))
* add rockmigrations() functions API [#41](https://github.com/baumrock/RockMigrations/issues/41) ([b10c404](https://github.com/baumrock/RockMigrations/commit/b10c404a5b5f95df082861551738218155098b3b))
* add settings redirect repeater ([bb0ccd8](https://github.com/baumrock/RockMigrations/commit/bb0ccd8375edd4495d6201da84eeb3de4d7c10e9))
* add tpl + prefix constant snippet ([0c420d2](https://github.com/baumrock/RockMigrations/commit/0c420d2d15d1219b1ab7caea722244bb99998f03))
* add trigger option for watch() method ([7743985](https://github.com/baumrock/RockMigrations/commit/7743985c6e460f54f77eddd3b85b9f24d3ffeaba))
* expose site module as site() function ([7547721](https://github.com/baumrock/RockMigrations/commit/75477219df7ca9e79d4a8fa0b87e096089e95855))
* improve loading of magic page templates ([f3b1a5f](https://github.com/baumrock/RockMigrations/commit/f3b1a5f80255378d2d3abb4520b17842c37d2969))
* improve settings page macro ([5b4a474](https://github.com/baumrock/RockMigrations/commit/5b4a4740fdd2b3c805eea9de5875787591480835))
* update settings stub ([b3d7399](https://github.com/baumrock/RockMigrations/commit/b3d739958f256bc5e312ed3780d41a84a9732b5d))
* use functions api for rm snippet ([a9ae6b0](https://github.com/baumrock/RockMigrations/commit/a9ae6b088ab3affb07f65b7b02e33b76ea723e1a))
* use minify only for superusers and on debug ([1214154](https://github.com/baumrock/RockMigrations/commit/1214154449b6bd777537cad6252db25ad8603d8a))


### Bug Fixes

* early exit if redirects are not populated ([65c4f52](https://github.com/baumrock/RockMigrations/commit/65c4f52b11eb724ef880fec865a6044beaf7da95))
* empty fields array flipping field order ([56e8f99](https://github.com/baumrock/RockMigrations/commit/56e8f99f0e8f92235c704ae6112a4b877388edfb))
* fix createPage resetting title ([07b0655](https://github.com/baumrock/RockMigrations/commit/07b06557d1af5e1f4c91e60e0fd48ca921ac80a6))
* fix pageListLabel removing template icon from pagelist ([c0431c7](https://github.com/baumrock/RockMigrations/commit/c0431c7d1ead71508b80b4f1c8770b0bab5670e0))
* fix redirect rules ([2225c3d](https://github.com/baumrock/RockMigrations/commit/2225c3d7b680bbd1b34d34bcb3fb6cedad0af18c))
* fix wrong comment in snippet ([85c28fb](https://github.com/baumrock/RockMigrations/commit/85c28fb97411f2af1ab3c77d3911b777d3f77ccf))
* prevent localName does not exist error ([48d28d6](https://github.com/baumrock/RockMigrations/commit/48d28d60df606434e0841cb5dab9d85b12c20781))
* remove console.log() ([62cd687](https://github.com/baumrock/RockMigrations/commit/62cd687cd952c41503e808c1a7bf6cb7dd93df1d))
* **RepeaterMatrix:** retain original field order ([17bc932](https://github.com/baumrock/RockMigrations/commit/17bc932fb28ed5cd4ed8385bf1e53b4953b495c2))
* **RepeaterMatrix:** standardize fields array ([375700c](https://github.com/baumrock/RockMigrations/commit/375700c0ba5f2881bec463d6e0f88f77cf2f6250))
* revert redirects from repeater to textarea ([0216cb3](https://github.com/baumrock/RockMigrations/commit/0216cb38d95ecf196ad24faebc1ce17d2dbc419c))
* saveCSS triggering Livereload if $keepCSS = false ([d11675d](https://github.com/baumrock/RockMigrations/commit/d11675d31359862b275907dffd2f72f07c9fbd90))

## [3.33.0](https://github.com/baumrock/RockMigrations/compare/v3.32.0...v3.33.0) (2023-12-03)


### Features

* add DelayedImageVariations to default profile ([a063964](https://github.com/baumrock/RockMigrations/commit/a063964635d19c64a69318470b25b75fde50c51d))
* add keepCSS param in saveCSS method ([be46e16](https://github.com/baumrock/RockMigrations/commit/be46e1665e1930733cc192e833f895b2a42b2241))
* add sql query snippet ([547f0ef](https://github.com/baumrock/RockMigrations/commit/547f0ef894c10938c076e3973279428093fbb057))


### Bug Fixes

* createPage() triggering page save all the time ([456c0b1](https://github.com/baumrock/RockMigrations/commit/456c0b1449a8b15d3a4b715bbfe39a4bc734f1e0))
* dont add page id + template in dropdown menu ([0730ee3](https://github.com/baumrock/RockMigrations/commit/0730ee300bcb87d383bdfae647442834def63c47))
* is_file() causing error when $path is null ([7e64380](https://github.com/baumrock/RockMigrations/commit/7e643806c3bf8feb5b04b766c20e962521c4f74b))
* ready() not triggered in magicpages ([f4f09bc](https://github.com/baumrock/RockMigrations/commit/f4f09bcba34f4cfa7078bc94cd5a99589f923f49))
* title not updating when using createPage() ([147230c](https://github.com/baumrock/RockMigrations/commit/147230c614ddf1ac97c503e90338f7d652fdccee))
* vscode links not working with latest vscode ([ec10745](https://github.com/baumrock/RockMigrations/commit/ec1074516641af2dd1f410be8c843d716ace1cf0))

## [3.32.0](https://github.com/baumrock/RockMigrations/compare/v3.31.1...v3.32.0) (2023-11-02)


### Features

* add CopyFieldNames tweak ([822b36b](https://github.com/baumrock/RockMigrations/commit/822b36bbd2bf7eca83a4293d4fcebf4f3e29a510))
* add hideFromGuests() + preview password feature ([518788f](https://github.com/baumrock/RockMigrations/commit/518788f1bc33cf1ca2a661f52bcac4eac5300ec3))
* improve PR from ivan to use internal refresh method ([0362f33](https://github.com/baumrock/RockMigrations/commit/0362f33fcf9bf72d19b9ef792c6a324119f2cdd4))
* show current fileconfig on settings page ([4fbb444](https://github.com/baumrock/RockMigrations/commit/4fbb4448157f5cf4a77036f0de3174d2db4d8719))


### Bug Fixes

* empty previewPassword causes no redirect ([c74596f](https://github.com/baumrock/RockMigrations/commit/c74596ff03c96c6b19fe4664ff828bf3c242e34b))
* installModule() does not actually install the module when run the 1st time, but only downloads it [#29](https://github.com/baumrock/RockMigrations/issues/29) ([ce59d2d](https://github.com/baumrock/RockMigrations/commit/ce59d2d6414f3c410bf2275b2641caaf28bf8e1b))
* move livereload settings back to RockFrontend ([37eb50c](https://github.com/baumrock/RockMigrations/commit/37eb50cc9125a0568d74f0d9323b45ac163482b4))
* rewrite setPageNameFromField method ([dbd900e](https://github.com/baumrock/RockMigrations/commit/dbd900ec28b7feb6080e8f63711503ed0db0b58f))
* settings from config file not properly applied ([2aa8ea5](https://github.com/baumrock/RockMigrations/commit/2aa8ea508449efeb14f55c1fa146ed44653935b8))
* tweak info icon falsely on new line ([d738b6b](https://github.com/baumrock/RockMigrations/commit/d738b6b34fe87a6f228f7b017f3b8a871fb1a426))

