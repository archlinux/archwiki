# Release History

## 0.10.3
* Check string type before running `preg_match()` (Umherirrender)
* Raise wikimedia/timestamp to 5.1, use `TS::…` instead of global constants (James D. Forrester)

* build: Allow psr/log ^3.0.0 (Reedy)
* build: Updating composer dependencies (libraryupgrader)
* build: Updating mediawiki/mediawiki-phan-config to 0.20.0 (libraryupgrader)
* build: Upgrade colinodell/psr-testlogger from 1.3.0 to 1.3.1 (James D. Forrester)
* build: Upgrade mediawiki-phan-config for PHP 8.5 support (James D. Forrester)
* build: Upgrade mediawiki/mediawiki-phan-config from 0.17.0 to 0.18.0 (James D. Forrester)
* build: Upgrade PHPUnit from 10.5.58 to 10.5.63 to unblock CI (James D. Forrester)
* code: Update copy of GPL license and licensing headers in files (James D. Forrester)
* code: Declare strict types on all PHP files (Umherirrender)

## 0.10.2
* build: Updating ockcyp/covers-validator to 1.7.0 (libraryupgrader)
* build: Update phpunit/phpunit to 10.5.58 (Umherirrender)
* tests: Improve `ReaderTest::provideXMPParse()` (Umherirrender)
* Remove no-op `xml_parser_free()` calls (Sam Reed)
* Reader: Update `destroyXMLParser()` comment (Sam Reed)
* Reader: Replace `switch()` for `match()` (Sam Reed)

## 0.10.1
* Add DigitalSourceType field to the extraction list (Brian Wolff)
* Ensure that top level rdf:type is properly ignored (Brian Wolff)
* Fix handling of Qualified statements (Brian Wolff)
* Simplify validate callback, require it to be valid (Brian Wolff)

## 0.10.0
* [BREAKING CHANGE] Remove support for PHP < 8.1 (James D. Forrester)
* Fix parsing of Artist field with type string (Thiemo Kreuz) [T399148](https://phabricator.wikimedia.org/T399148)
* Change documented parameter type from resource to \XMLParser (Umherirrender)
* composer: Add support for wikimedia/timestamp ^5.0.0 (Bartosz Dziewoński)

Changelog was not maintained prior to version 0.10.0.
