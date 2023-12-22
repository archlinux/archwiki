# Release History

## wikimedia-langconv 0.4.2 (2021-08-07)
* Update dependencies.

## wikimedia-langconv 0.4.1 (2020-07-13)
* Don't distribute the .att files in the composer library (they were already
  omitted from the npm library)

## wikimedia-langconv 0.4.0 (2020-05-05)
* Refactor ReplacementMachine in PHP port; add new classes
  FstReplacementMachine and NullReplacementMachine.
  This is a breaking change; replace references to ReplacementMachine
  in your code with FstReplacementMachine.

## wikimedia-langconv 0.3.5 (2020-03-06)
* Loosen dependency on wikimedia/assert to facilitate upgrades

## wikimedia-langconv 0.3.4 (2020-03-06)
* Version bumps to dependencies.
* R&D code to support more efficient FST conversion of large string
  replacement tables; not yet used in production.

## wikimedia-langconv 0.3.3 (2020-01-28)
* Performance improvements to PHP port.

## wikimedia-langconv 0.3.2 (2019-12-12)
* Fix crasher in PHP port.

## wikimedia-langconv 0.3.1 (2019-12-04)
* Suppress warning in PHP port; debugging improvements.

## wikimedia-langconv 0.3.0 (2019-11-25)
* Significant performance improvements to PHP port.

## wikimedia-langconv 0.2.0 (2019-11-20)
* Updated wikimedia/assert and mediawiki/mediawiki-codesniffer versions.

## wikimedia-langconv 0.1.0 (2019-10-16)
* Initial release, in both JavaScript (npm) and PHP (composer)
