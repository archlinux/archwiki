# Release History

## Timestamp 5.1.0 (2025-11-26)
* Add TimestampFormat enumeration. Users are encouraged to migrate
  away from the global TS_* constants. (C. Scott Ananian)
* Allow DateTimeInterface as constructor argument (C. Scott Ananian)

## Timestamp 5.0.0 (2025-09-20)
* [BREAKING CHANGE] Drop support for PHP < 8.1 (James D. Forrester)
* [BREAKING CHANGE] Drop ConvertibleTimestamp::microtime() (James D. Forrester)
* Add adapter for PSR-20 ClockInterface (Gergő Tisza)
* Change getTimestamp() to throw InvalidArgumentException on bad format (Daimona Eaytoy)
* Fix PHPDoc return type for ConvertibleTimestamp::diff() (Gergő Tisza)
* Make setTimestamp() faster by moving TS_UNIX up in the list of regexes (Thiemo Kreuz)

## Timestamp 4.2.0 (2024-09-25)
* Add `ConvertibleTimestamp::hrtime()`, as mockable version of hrtime() built-in.
* Deprecate ConvertibleTimestamp::microtime() in favor of hrtime(). [T245464](https://phabricator.wikimedia.org/T245464)

## Timestamp 4.1.1 (2023-09-29)
* Fix setTimestamp() to catch ValueError from DateTime::createFromFormat.

## Timestamp 4.1.0 (2023-02-14)
* Add `add()` and `sub()` methods, for date interval arithmetic.
* Add optional `$step` parameter to `setFakeTime()`.
* Add microtime() function

## Timestamp 4.0.0 (2022-03-15)
* Remove support for HHVM, PHP 7.0, and PHP 7.1.
* Add support for 2-digit years, per RFC 2626.

## Timestamp 3.0.0 (2019-06-08)
* [BREAKING CHANGE] The library is now stricter about rejecting some invalid
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

## Timestamp 2.2.0 (2018-09-23)
* Add `ConvertibleTimestamp::time()`, which works like the time() built-in but
  can be mocked in tests.

## Timestamp 2.1.1 (2018-09-10)
* Fix timezone handling in `TS_POSTGRES`. Before, it generated a format that
  was accepted by Postgres but differed from what Postgres itself generates.

## Timestamp 2.1.0 (2018-09-04)
* Introduce a mock clock for unit testing.

## Timestamp 2.0.0 (2018-08-11)
* [BREAKING CHANGE] Drop PHP 5 support (HHVM in PHP 5 mode is still supported).
* Support microtime for Unix and Oracle formats.

## Timestamp 1.0.0 (2016-10-01)
* Initial commit
