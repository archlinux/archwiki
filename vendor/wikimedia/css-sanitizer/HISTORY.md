# Release History
## css-sanizer 5.5.0 (2024-01-27)
* Ensure <-token and identifiers are always separated as a security
  paranoia measure
* Add support for prefers-reduced-motion, prefers-reduced-transparency,
  prefers-contrast and forced-colors media queries
* Add support for light-dark() color function
* [build] Bump dependencies

## css-sanitizer 5.4.0 (2024-10-30)
* Add support for `:dir()` pseudo-class
* Add support for CSS Logical 1 properties and values

* code: Use explicit nullable type on parameter arguments
* build: Phan must ignore EmptyIterator type in UnorderedGroup
* build: Updating composer dependencies
* build: Updating mediawiki/mediawiki-codesniffer to 44.0.0
* build: Allow wikimedia/scopedcallback 5.0.0
* build: Allow wikimedia/testing-access-wrapper 3.0.0
* build: Remove use of `$PHPUNIT_ARGS`

## css-sanitizer 5.3.0 (2024-04-18)
* Harden security by disallowing CSS custom properties for `border-color`
* Add support for fallback values for CSS custom properties in color attributes.

## css-sanitizer 5.2.0 (2024-04-03)
* Add support for CSS custom properties in color attributes
* [build] Bump dependencies

## css-sanitizer 5.1.0 (2024-02-21)
* Drop support for wikimedia/utfnormal 2.x
* Change use of AtEase to at operator
* Support @media (prefers-color-scheme:...) rule
* [build] Bump dependencies

## css-sanitizer 5.0.0 (2023-08-29)
* Drop support for PHP < 7.4
* Support wikimedia/utfnormal ^4.0.0
* [build] Bump dependencies

## css-sanitizer 4.0.1 (2022-07-09)
* Allow wikimedia/scoped-callback 4.0.0

## css-sanitizer 4.0.0 (2022-07-03)
* Update for new versions of various standards
* Add return types to inbuilt interfaces
* [build] Bump dependencies

## css-sanitizer 3.0.2 (2021-03-21)
* Relax wikimedia/at-ease to ^2.0.0
* [build] Bump dependencies

## css-sanitizer 3.0.1 (2021-01-30)
* Support wikimedia/utfnormal ^3.0.1

## css-sanitizer 3.0.0 (2021-01-04)
* Drop support for PHP < 7.2 and HHVM
* Switch to PHPUnit 8; drop support for PHPUnit 4
* Remove support for wikimedia/utfnormal 1.1.0
* Make Parser::CV_DEPTH_LIMIT private
* [BREAKING CHANGE] Rename match() and Match class for PHP 8.0
* [build] Bump dependencies

## css-sanitizer 2.0.1 (2019-02-11)
* [build] Bump dependencies

## css-sanitizer 2.0.0 (2018-08-24)
* Update for new versions of various standards
* Add CSS Intrinsic and Extrinsic Sizing Level 3
* Add hoisting option to StyleRuleSanitizer selector handling

## css-sanitizer 1.0.6 (2018-04-18)
* [build] Bump dependencies

## css-sanitizer 1.0.5 (2018-04-02)
* Workaround IE parsing bug
* [build] Bump dependencies

## css-sanitizer 1.0.4 (2018-02-27)
* Optionally depend on wikimedia/utfnormal 2.0.0
* [build] Bump dependencies

## css-sanitizer 1.0.3 (2018-01-17)
* [build] Bump dependencies

## css-sanitizer 1.0.2 (2017-06-13)
* [SECURITY] Escape angle brackets numerically in strings and identifiers

## css-sanitizer 1.0.1 (2017-06-06)
* Fix escaping of various characters

## css-sanitizer 1.0.0 (2017-04-06)
* Initial release.
