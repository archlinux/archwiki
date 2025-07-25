# Release History

## v4.2.0
* Add `ConvertibleTimestamp::hrtime()`, as mockable version of hrtime() built-in.
* Deprecate ConvertibleTimestamp::microtime() in favor of hrtime(). [T245464](https://phabricator.wikimedia.org/T245464)

## v4.1.1
* Fix setTimestamp() to catch ValueError from DateTime::createFromFormat.

## v4.1.0
* Add `add()` and `sub()` methods, for date interval arithmetic.
* Add optional `$step` parameter to `setFakeTime()`.
* Add microtime() function

## v4.0.0
* Remove support for HHVM, PHP 7.0, and PHP 7.1.
* Add support for 2-digit years, per RFC 2626.

## v3.0.0
* BREAKING CHANGE: the library is now stricter about rejecting some invalid
  formats such as "Wed, 22 May 2019 12:00:00 +1 day" (which is a valid date
  spec in some tools but not in ConvertibleTimestamp which does not accept
  relative date modifiers) or "Wed, 22 May 2019 12:00:00 A potato" (where
  the trailing nonsense got silently ignored before this change).
* Change time zone handling to be more consistent and correct.
* Fix some bugs certain formats had with pre-Unix-epoch dates.
* Add support for more ISO 8601 inputs:
  - allow space instead of "T",
  - also accept comma as decimal separator,
  - also accept non-Z timezones.
* Add support for DateTime in `ConvertibleTimestamp::convert()`.

## v2.2.0
* Add `ConvertibleTimestamp::time()`, which works like the time() built-in but
  can be mocked in tests.

## v2.1.1
* Fix timezone handling in `TS_POSTGRES`. Before, it generated a format that
  was accepted by Postgres but differed from what Postgres itself generates.

## v2.1.0
* Introduce a mock clock for unit testing.

## v2.0.0
* BREAKING CHANGE: drop PHP 5 support (HHVM in PHP 5 mode is still supported).
* Support microtime for Unix and Oracle formats.

## v1.0.0
* Initial commit
