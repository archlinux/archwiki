## v0.5.1

* build: Updating mediawiki/mediawiki-codesniffer to 46.0.0 (libraryupgrader)
* build: Updating mediawiki/mediawiki-phan-config to 0.15.1 (libraryupgrader)
* build: Updating composer dependencies (libraryupgrader)
* build: Upgrade phpunit to 9.6.21 (James D. Forrester)
* build: Switch phan to special library mode (James D. Forrester)

## v0.5.0

* build: Remove obsolete doxygen_escape.sh (Timo Tijhof)
* Use PHP key-value lookup instead of bloomfilter with JSON files (Timo Tijhof)
* build: Fix benchmark unit from seconds to milliseconds (Timo Tijhof)
* .gitattributes: Simplify (Reedy)
* Add basic benchmark (Reedy)
* tests: Swap to import (Reedy)
* Move src/CommonPassword/ files up one level (Reedy)
* composer.json: Explicitly require ext-json (Reedy)
* Bump required PHP version and PHPUnit (Reedy)
* README.md: Add trailing fullstop (Reedy)
* build: Updating composer dependencies (libraryupgrader)
* build: Updating mediawiki/mediawiki-phan-config to 0.12.0 (libraryupgrader)

## v0.4.0

* build: Updating composer dependencies (libraryupgrader)
* Bump pleonasm/bloom-filter to 1.0.3 (Reedy)
* Add phan (take 2) (Reedy)
* Add phan (Reedy)
* Remove .travis.yml (Reedy)
* build: Updating composer dependencies (libraryupgrader)
* CommonPasswordsTest: add missing scope declarations (DannyS712)
* CommonPasswordsTest: remove extra @param tag (DannyS712)
* build: Updating mediawiki/mediawiki-codesniffer to 37.0.0 (libraryupgrader)
* build: Cleanup and improve .phpcs.xml (Umherirrender)
* build: Updating composer dependencies (libraryupgrader)
* Update a few places to use new name "CommonPasswords" (DannyS712)

## v0.3.0

* build: Updating composer dependencies (libraryupgrader)
* build: Updating mediawiki/mediawiki-codesniffer to 34.0.0 (libraryupgrader)
* build: Updating ockcyp/covers-validator to 1.3.1 (libraryupgrader)
* build: Updating ockcyp/covers-validator to 1.3.0 (libraryupgrader)
* build: Updating mediawiki/mediawiki-codesniffer to 33.0.0 (libraryupgrader)
* build: Updating ockcyp/covers-validator to 1.2.0 (libraryupgrader)
* Remove PasswordBlacklist compat (Reedy)

## v0.2.0

* Rename library to CommonPasswords (Reedy)
* build: Updating mediawiki/minus-x to 1.1.0 (libraryupgrader)
* build: Updating composer dependencies (libraryupgrader)
* build: Updating mediawiki/mediawiki-codesniffer to 31.0.0 (libraryupgrader)
* build: Updating mediawiki/mediawiki-codesniffer to 30.0.0 (libraryupgrader)
* build: Updating composer dependencies (libraryupgrader)
* Require PHPUnit 8 like MW core (Daimona Eaytoy)
* Drop PHP 7.0/7.1 and HHVM support (James D. Forrester)
* build: Updating mediawiki/mediawiki-codesniffer to 29.0.0 (libraryupgrader)
* build: Updating mediawiki/minus-x to 0.3.2 (libraryupgrader)
* build: Updating mediawiki/mediawiki-codesniffer to 28.0.0 (libraryupgrader)
* build: Updating mediawiki/mediawiki-codesniffer to 26.0.0 (libraryupgrader)
* build: Updating mediawiki/mediawiki-codesniffer to 24.0.0 (libraryupgrader)
* Add a password that is a known false positive (Reedy)
* Don't export scripts/ (Kunal Mehta)
* Drop pre-PHP 7 support (Kunal Mehta)

## v0.1.4

* Decrease false positive possibility to 0.000001 (Reedy)

## v0.1.3

* build: Updating mediawiki/mediawiki-codesniffer to 23.0.0 (libraryupgrader)
* Prevent web access to CLI script (Reedy)
* Add `testwikijenkinspass` to passwords to test not blacklisted (Reedy)
* build: Updating mediawiki/mediawiki-codesniffer to 22.0.0 (libraryupgrader)
* build: Updating mediawiki/mediawiki-codesniffer to 21.0.0 (libraryupgrader)
* build: Updating mediawiki/mediawiki-codesniffer to 20.0.0 (libraryupgrader)

## v0.1.2

* Sync with library bootstrap (Kunal Mehta)
* Move 64bit file from blacklist.json to blacklist-x64.json (Reedy)
* 32bit support (Reedy)
* build: Updating mediawiki/mediawiki-codesniffer to 18.0.0 (libraryupgrader)
* build: Updating mediawiki/mediawiki-codesniffer to 17.0.0 (libraryupgrader)
* build: Updating jakub-onderka/php-parallel-lint to 1.0.0 (libraryupgrader)
* pleonasm/bloom-filter 1.0.2 (Reedy)
* build: Updating phpunit/phpunit to 4.8.36 || ^6.5 (libraryupgrader)

## v0.1.1

* Use class-level @covers tag (Kunal Mehta)
* build: Updating mediawiki/mediawiki-codesniffer to 16.0.1 (libraryupgrader)
* build: Add jakub-onderka/php-console-highlighter (Umherirrender)
* build: Fix .phpcs.xml in .gitattributes (Umherirrender)
* build: Adding MinusX (Kunal Mehta)
* Reduce number of generated test cases (Kunal Mehta)
* build: Updating mediawiki/mediawiki-codesniffer to 16.0.0 (libraryupgrader)

## v0.1.0

* Initial implementation of the PasswordBlacklist (Reedy)
* Remove bloom filter from composer.json (Reedy)
* Add LICENSE, README.md, .gitignore and basic composer.json (Reedy)
* Add .gitreview (Kunal Mehta)
