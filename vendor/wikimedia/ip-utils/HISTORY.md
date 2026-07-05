# Release History

## 6.0.1

* Optimize `IPSet::addCidr` by reducing allocations and `ord()` calls (Ori Livneh)
* IPSet: Add some type hints (Sam Reed)

## 6.0.0

* Require PHP 8.1 or later (James D. Forrester)
* Require `ext-json` in composer.json (Reedy)
* Optimize `IPUtils::isInRanges` by removing redundant calls for a 3X speed-up (Ori Livneh)
* Optimize `IPUtils::isInRanges` by removing redundant false check in `parseCIDR6` (Sam Reed)
* Optimize `IPUtils::isInRanges` by removing extra case for CIDR /0 in `parseCIDR` (Umherirrender)
* Optimize `IPUtils::isInRange` by removing redundant sanitize call (Ori Livneh)
* Use PHP 8 functions str_contains and str_starts_with (Umherirrender)
* Optimize `::getIPsInRange` by using str_split in hexToOctet/hexToQuad (Umherirrender)

## 5.0.0
* Require PHP 7.4 or later (Timo Tijhof)
* The IPSet class is now part of the IPUtils library (Timo Tijhof)

## 4.0.1
* Allow wikimedia/ip-set ^4.0.0 (Timo Tijhof)

## 4.0.0
* Several IPUtils constants are now explicitly private (Zabe)

## 3.0.2
* Optimize `IPUtils::canonicalize` by removing redundant strpos/substr call (Reedy)
* Allow wikimedia/ip-set ^3.0.0 (Reedy)

## 3.0.1
* Revert "Stop allowing invalid /0 subnet" (Reedy) [T267997](https://phabricator.wikimedia.org/T267997)
* Improve return type for IPUtils::parseCIDR6 (Reedy)
* Add support for spaces around hyphenated IP ranges (Reedy)

## 3.0.0
* Add `IPUtils::getIPsInRange` to retrieve all IPs in a range (Ammar Abdulhamid)
* Fix `isValidIPv4` and `isValidIPv6` to cast return value to bool (Reedy)

## 2.0.0
* Remove PHP 7.0/7.1 and HHVM support (James D. Forrester)
* Add isValidIPv4 and isValidIPv6 (Reedy)
* Add support for explicit ranges in IPUtils::isValidRange() (Reedy)
* Optimize `IPUtils::isValidRange` by checking IPv4 before IPv6 (Reedy)
* Fix return values where function doesn't always return `[string, string]` (Reedy)
* Fix getSubnet to not accept invalid IPv4 addresses (Reedy)
* Stop allowing invalid /0 subnet (Reedy)

## 1.0.0
* Initial import from MediaWiki core (Kunal Mehta)
