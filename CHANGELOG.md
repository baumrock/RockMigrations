## [3.35.3](https://github.com/baumrock/RockMigrations/compare/v3.35.2...v3.35.3) (2024-01-17)


### Bug Fixes

* add check for title field flag after each run() ([2930675](https://github.com/baumrock/RockMigrations/commit/293067549744e6229ab9ad5e838690a90957e30d))

## [3.35.2](https://github.com/baumrock/RockMigrations/compare/v3.35.1...v3.35.2) (2024-01-17)


### Bug Fixes

* migration hints messing up the fields selector field ([a1d16c2](https://github.com/baumrock/RockMigrations/commit/a1d16c285a25ab4c10f60774a26a02d367aef02f))

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

