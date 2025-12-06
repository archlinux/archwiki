# Release History

## css-sanitizer 6.1.0 (2025-10-29)
* Add support for CSS Scroll Snap Module Level 1
* Fix `ruby-align: space-around` support

## css-sanitizer 6.0.0 (2025-07-23)

New modules:
* Add support for CSS Box Sizing Level 4 (as seen in draft from 2025-02-24)
  - values: stretch, fit-content, and contain;
  - properties: aspect-ratio, contain-intrinsic-* (size, width, height,
    block-size, inline-size), min-intrinsic-size;
* Update Color to Level 4 (2025-04-24)
* Update Values and Units to Level 4 (WD 2024-03-12)
* Add support for Ruby Level 1 (WD 2022-12-31)
* Add support for Transforms Level 2 (WD 2021-11-09)
* Update Overflow Level 3 to WD 2023-03-29 and add support for Overflow Level 4
  (WD 2023-03-21)
* Add support for Lists and Counters Level 3 (WD 2020-11-17) and Counter Styles
  Level 3 (WD 2021-07-27)

Substantive updates:
* Update Align Level 3 to WD 2025-03-11
* Update Backgrounds Level 3 to CRD 2024-03-11
* Update Display Level 3 to CR 2023-03-30
* Update Compositing Level 1 to CRD 2024-03-21
* Update Images Level 3 to CRD 2023-12-18
* Update Masking Level 1 to CRD 2021-08-05
* Update Paged Media Level 3 to WD 2023-09-14
* Update Position Level 3 to WD 2025-03-11
* Update Pseudo-Elements Level 4 to WD 2022-12-30
* Update Shapes Level 1 to CRD 2022-11-15
* Update User Interface Level 4 to WD 2021-03-16

Documentation-only updates:
* Update Animations Level 1 to WD 2023-03-02
* Update Cascade Level 4 to CR 2022-01-13
* Update Easing Level 1 to CRD 2023-02-13
* Update Grid Level 1 to CRD 2025-03-26
* Update Multi-column Layout Level 1 to CR 2024-05-16
* Update Syntax Level 3 to CRD 2021-12-24
* Update Text Level 3 to CRD 2024-09-30
* Update Text Decoration Level 3 to CRD 2022-05-05

## css-sanitizer 5.5.0 (2025-01-27)
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
