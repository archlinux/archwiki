# Release History

## v3.0.2
* Fix @covers (Reedy)
* Try and fix PSR-4 namespace loading issues (Reedy)

## v3.0.1
* Move under new Wikimedia\ObjectFactory namespace (DannyS712)
* Bump psr/container from 1.0.0 to 1.1.1 (Reedy)
* Fix @phan-param tags on ObjectFactory (DannyS712)
* build: Swap deprecated @codingStandardsIgnore to @phpcs:disable (Umherirrender)
* build: Updating composer dependencies (libraryupgrader)
* build: Updating mediawiki/mediawiki-codesniffer to 34.0.0 (libraryupgrader)
* build: Updating ockcyp/covers-validator to 1.3.1 (libraryupgrader)

## v3.0.0
* Allow null values for 'services' spec field (Gergő Tisza)
* BREAKING CHANGE: Drop PHP 7.0/7.1 and HHVM support (James D. Forrester)
* BREAKING CHANGE: Remove deprecated ObjectFactory::constructClassInstance method (DannyS712)
* Add support for optional services (DannyS712)
* Add phan annotations for better analysis (Daimona Eaytoy)

## v2.1.0
* Implement spec 'services' for DI (Brad Jorsch)

## v2.0.0
* BREAKING CHANGE: Bump required PHP version to 5.6.99 (PHP 7 or HHVM) (Reedy)
* Enhance to be able to replace most/all MediaWiki object instantiation (Brad Jorsch)
* Deprecate ObjectFactory::constructClassInstance method (Brad Jorsch)

## v1.0.0
* Initial commit (Bryan Davis, Kunal Mehta)
