# Request timeout library changelog

## v3.0.0

* [BREAKING CHANGE] Drop PHP 7.4 and PHP 8.0 support. (James D. Forrester)
* Update for Excimer 1.2.4+. Keep a pool of timers rather than creating a new
  timer for each critical section. (Tim Starling)

## v2.0.2

### Bug fixes

* Fix TimeoutException constructor after NormalizedException change (Gergő Tisza)

### Internal changes

* composer.json: Run phpunit as part of 'composer test' (Reedy)


## v2.0.1

### Internal changes

* composer: Update wikimedia/normalized-exception version constraint (Gergő Tisza)
* build: Updating mediawiki/mediawiki-phan-config to 0.15.1 (libraryupgrader)


## v2.0.0

### New features

* [BREAKING CHANGE] Drop PHP 7.2 and PHP 7.3 support (James D. Forrester)

### Internal changes

* build: Minor cleanup of `.phan/config.php` (Reedy)
* build: Switch phan to special library mode (James D. Forrester)
* build: Updating mediawiki/mediawiki-codesniffer to 45.0.0 (libraryupgrader)
* build: Updating mediawiki/mediawiki-phan-config to 0.14.0 (libraryupgrader)
* build: Upgrade PHPUnit from ^8.5 to 9.6.21 (James D. Forrester, Reedy)
* build: Use phan `exclude_file_regex` from standard (Umherirrender)
* build: Allow wikimedia/normalized-exception 2.0.0 (Reedy)
* docs: Fix broken homepage in Doxygen output (Timo Tijhof)


## v1.2.0

### New features

* Use NormalizedException (Taavi Väänänen)

### Internal changes

* build: Updating mediawiki/mediawiki-codesniffer to 38.0.0 (libraryupgrader)
* build: Updating mediawiki/mediawiki-phan-config to 0.11.1 (libraryupgrader)
* build: Cleanup and improve `.phpcs.xml` (Umherirrender)


## v1.1.0

### New features

* Allow emergency timeouts to be overridden when creating the critical section (Tim Starling)

### Internal changes

* build: Updating composer dependencies (libraryupgrader)


## v1.0.0

### New features

* RequestTimeout library initial commit (Tim Starling)

### Bug fixes

* Fix CSP stack bug and some other cleanups (Tim Starling)

### Internal changes

* build: Sync with library bootstrap, add Doxygen config and more (Kunal Mehta)
* build: Add a gitattributes file to control export (Umherirrender)
* build: Updating mediawiki/mediawiki-phan-config to 0.10.6 (libraryupgrader)
* Initial empty repository (Tim Starling)
