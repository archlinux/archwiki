# Release History

## v3.0.0

### Changes for users

* [BREAKING CHANGE] Drop PHP 7.2 and PHP 7.3 support
* [BREAKING CHANGE] Drop functions not in class
* AtEase: Stop setting E_STRICT on PHP >= 8.4

### Build changes

* Fix coverage of AtEase::suppressWarnings()
* Simplify file structure
* AtEaseTest: Minor cleanup
* build: Add phan
* build: Upgrade mediawiki/mediawiki-codesniffer from 35.0.0 to 44.0.0
* build: Upgrade mediawiki/minus-x from 1.1.1 to 1.1.3
* build: Upgrade ockcyp/covers-validator from 1.3.3 to 1.6.0
* build: Upgrade php-parallel-lint/php-console-highlighter from 0.5.0 to 1.0.0
* build: Upgrade php-parallel-lint/php-parallel-lint from 1.2.0 to 1.4.0
* build: Upgrade phpunit/phpunit from ^8.5 to 9.6.16

## v2.1.0

### Changed

* Dropped support for PHP 7.0/7.1 and HHVM
* build: Updated several dev dependencies
* src: Started using real variadic arguments
* doc: Restore Doxygen welcome page
* build: Dropped Travis testing

### Fixed
* src: AtEase::quietCall should restore error level in exception case
* README: Fix markdown display of code

## v2.0.0

### Added

* src: Move the three main functions to a new class `Wikimedia\AtEase\AtEase`,
  as static methods. The old namespaced functions are kept for compatibility.

### Changed

* build: Add MinusX test.
* build: Exclude `/build` from composer package via `.gitattributes`.
* build: Exclude `phpcs.xml` from composer package.
* build: Update development dependencies.
* build: Use SPDX 3.0 license identifier.
* docs: Update Packagist badge in README for new package name.

### Removed

* src: Remove compatibility with the `MediaWiki\` namespace,
  use `Wikimedia\` instead.
* build: Raised PHP requirement to 7.0.0.

## v1.2.0

### Fixed

* build: Unbreak Travis CI build for PHP 5.3.3.
* tests: Remove broken @covers tags.
* tests: Add more test-cases for `MediaWiki\quietCall()`.

### Changed

* src: Rename namespace from `MediaWiki\` to `Wikimedia\`,
  the old namespace is kept as alias for backwards compatibility.
* src: Add `callable` type hint to `Wikimedia\quietCall()`.
* build: Rename package to `wikimedia/at-ease`.
* build: Updating development dependencies.
* docs: Use diffusion instead of Code Review for SVN linkage.
* license: Use the plaintext GPL.

## v1.1.0

### Added

* src: Add `MediaWiki\quietCall()` convenience method.

### Changed

* build: Update development dependencies.

### Fixed

* docs: Make README nicer for Doxygen processing.

## v1.0.0

Initial release.
