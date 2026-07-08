# Changelog

## v2.2.0

Added:

* Add native type declarations and enable strict_types (Umherirrender)
* Add missing documentation to class properties (Umherirrender)

Changed:

* Raise required PHP to 8.1+ (Reedy, Umherirrender)

## v2.1.0

Added:

* PSquare: Implement `__serialize()` and `__unserialize()` (Timo Tijhof)

## v2.0.0

Removed:

* Remove `RunningStat` namespace alias (Reedy)

Fixed:

* RunningStat: Remove workaround for resolved PHP 5.6 bug (Ammarpad)

Changed:

* Switch autoloader from classmap to PSR-4 (Kunal Mehta)
* Raise required PHP to 7.0+ (Reedy)

## v1.2.1

Fixed:

* Fix `RelPath\RelPath` to extend `Wikimedia\RelPath` not `PSquare` (Reedy)

## v1.2.0

Added:

* Rename namespace from `RunningStat` to `Wikimedia` with compat alias (Reedy)

## v1.1.0

Added:

* Add `PSquare` class for online percentile estimation (Ori Livneh)

Changed:

* Rename `RunningStat::push()` to `RunningStat::addObservation()` (Ori Livneh)

## v1.0.0

Initial release.