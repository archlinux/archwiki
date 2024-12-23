# Changelog

## v5.1.2

Fixed:
* Less_Functions: Fix "Implicitly nullable parameter" PHP 8.4 warning (Reedy) [T376276](https://phabricator.wikimedia.org/T376276)

## v5.1.1

Fixed:
* Fix compiling of PHP-injected variables with false, null or empty string (Hannah Okwelum)

## v5.1.0

Added:
* Add support for property acessors (Piotr Miazga) [T368408](https://phabricator.wikimedia.org/T368408)
* Increase parsing flexibility around at-rule blocks and custom properties (Piotr Miazga) [T368408](https://phabricator.wikimedia.org/T368408)
* Add support for Namespaces and Accessors (Piotr Miazga) [T368409](https://phabricator.wikimedia.org/T368409)

Fixed:
* Fix parse error when opacity is set to zero in `alpha()` function (Hannah Okwelum) [T371606](https://phabricator.wikimedia.org/T371606)

## v5.0.0

Added:
* Add support for Lessjs 3.5.0 `calc()` exception (Piotr Miazga) [T367186](https://phabricator.wikimedia.org/T367186)
* Add support for CSS Grid syntax (Dringsim) [T288498](https://phabricator.wikimedia.org/T288498)
* Add support for `\9` escapes in CSS keyword (Dringsim) [T288498](https://phabricator.wikimedia.org/T288498)
* Add Less_Parser "math" option, renamed from strictMath (Hannah Okwelum) [T366445](https://phabricator.wikimedia.org/T366445)

Changed:
* Change Less_Parser "math" default from "always" to "parens-division" (Hannah Okwelum) [T366445](https://phabricator.wikimedia.org/T366445)
* Change `Less_Version::less_version` to "3.13.3". This end compatibility support of Less.js 2.5.3.
  Less.php 5.0 and later will target Less.js 3.13.1 behaviour instead. (Piotr Miazga)

Removed:
* Remove `import_callback` Less_Parser option (Hannah Okwelum)
* Remove backtick evaluation inside quoted strings (Bartosz Dziewoński)
* Remove `Less_Parser::AllParsedFiles()` (Hannah Okwelum)
* Remove Less_Parser->SetInput() public method, now private (Hannah Okwelum)
* Remove Less_Parser->CacheFile() public method, now private (Hannah Okwelum)
* Remove Less_Parser->UnsetInput() public method, now private (Hannah Okwelum)
* Remove Less_Parser->save() public method, now private (Hannah Okwelum)

## v4.4.1

Fixed:
* Update `Less_Version::version` and bump `Less_Version::cache_version` (Timo Tijhof)

## v4.4.0

Added:
* Add `image-size()` function, disable base64 for SVG `data-uri()` (Hannah Okwelum) [T353147](https://phabricator.wikimedia.org/T353147)
* Improve support for preserving `!important` via variables (Piotr Miazga) [T362341](https://phabricator.wikimedia.org/T362341)
* Add support for include path inside `data-uri()` (Hannah Okwelum) [T364871](https://phabricator.wikimedia.org/T364871)

Changed, to match Less.js 2.5.3:
* Fix multiplication of mixed units to preserve the first unit (Piotr Miazga) [T362341](https://phabricator.wikimedia.org/T362341)

Fixed:
* Fix checking of guard conditions in nested mixins (Hannah Okwelum) [T352867](https://phabricator.wikimedia.org/T352867)
* Less_Functions: Avoid clobbering `clamp()` with internal helper (Timo Tijhof) [T363728](https://phabricator.wikimedia.org/T363728)

## v4.3.0

Added:
* Support interpolated variable imports, via ImportVisitor (Hannah Okwelum) [T353133](https://phabricator.wikimedia.org/T353133)
* Support rulesets as default values of a mixin parameter (Hannah Okwelum) [T353143](https://phabricator.wikimedia.org/T353143)
* Support `...` expand operator in mixin calls (Piotr Miazga) [T352897](https://phabricator.wikimedia.org/T352897)
* Improve support for `@import (reference)` matching Less.js 2.x (Hannah Okwelum) [T362647](https://phabricator.wikimedia.org/T362647)

Changed:
* Improve `mix()` argument exception message to mention given arg type (Timo Tijhof)
* The `Less_Tree_Import->getPath()` method now reflects the path as written in the source code,
  without auto-appended `.less` suffix, matching upstream Less.js 2.5.3 behaviour.
  This internal detail is exposed via the deprecated `import_callback` parser option.
  It is recommended to migrate to `Less_Parser->SetImportDirs`, which doesn't expose internals,
  and is unaffected by this change.

Deprecated:
* Deprecate `import_callback` Less_Parser option. Use `Less_Parser->SetImportDirs` with callback instead.
* Deprecate `Less_Parser->SetInput()` as public method. Use `Less_Parser->parseFile()` instead.
* Deprecate `Less_Parser->CacheFile()` as public method. Use `Less_Cache` API instead.
* Deprecate `Less_Parser::AllParsedFiles()` as static method. Use `Less_Parser->getParsedFiles()` instead.
* Deprecate `Less_Parser->UnsetInput()` as public method, considered internal.
* Deprecate `Less_Parser->save()` as public method, considered internal.

Fixed:
* Fix `replace()` when passed multiple replacements (Roan Kattouw) [T358631](https://phabricator.wikimedia.org/T358631)
* Fix unexpected duplicating of uncalled mixin rules (Hannah Okwelum) [T363076](https://phabricator.wikimedia.org/T363076)
* Fix ParseError for comments after rule name or in `@keyframes` (Piotr Miazga) [T353131](https://phabricator.wikimedia.org/T353131)
* Fix ParseError for comments in more places and preserve them (Piotr Miazga) [T353132](https://phabricator.wikimedia.org/T353132)
* Fix ParseError effecting pseudo classes with `when` guards (Piotr Miazga) [T353144](https://phabricator.wikimedia.org/T353144)
* Fix preservation of units in some cases (Timo Tijhof) [T360065](https://phabricator.wikimedia.org/T360065)
* Less_Parser: Faster matching by inlining `matcher()` chains (Timo Tijhof)
* Less_Parser: Faster matching with `matchStr()` method (Timo Tijhof)

## v4.2.1

Added:
* Add support for `/deep/` selectors (Hannah Okwelum) [T352862](https://phabricator.wikimedia.org/T352862)

Fixed:
* Fix ParseError in some division expressions (Hannah Okwelum) [T358256](https://phabricator.wikimedia.org/T358256)
* Fix `when()` matching between string and non-string (Timo Tijhof) [T358159](https://phabricator.wikimedia.org/T358159)
* Preserve whitespace before `;` or `!` in simple rules (Hannah Okwelum) [T352911](https://phabricator.wikimedia.org/T352911)

## v4.2.0

Added:
* Add `isruleset()` function (Hannah Okwelum) [T354895](https://phabricator.wikimedia.org/T354895)
* Add source details to "Operation on an invalid type" error (Hannah Okwelum) [T344197](https://phabricator.wikimedia.org/T344197)
* Add support for `method=relative` parameter in color functions (Hannah Okwelum) [T354895](https://phabricator.wikimedia.org/T354895)
* Add support for comments in variables and function parameters (Hannah Okwelum) [T354895](https://phabricator.wikimedia.org/T354895)
* Less_Parser: Add `functions` parser option API (Hannah Okwelum)

Changed, to match Less.js 2.5.3:
* Preserve original color keywords and shorthand hex (Hannah Okwelum) [T352866](https://phabricator.wikimedia.org/T352866)

Fixed:
* Fix PHP Warning when using a dynamic variable name like `@@name` (Hannah Okwelum) [T352830](https://phabricator.wikimedia.org/T352830)
* Fix PHP Warning when `@extend` path contains non-quoted attribute (Gr8b) [T349433](https://phabricator.wikimedia.org/T349433)
* Less_Parser: Faster `skipWhitespace` by using native `strspn` (Umherirrender)
* Less_Parser: Fix Less_Tree_JavaScript references to consistently be in camel-case (Stefan Fröhlich)
* Fix `!important` in nested mixins (Hannah Okwelum) [T353141](https://phabricator.wikimedia.org/T353141)
* Fix crash when using recursive mixins (Timo Tijhof) [T352829](https://phabricator.wikimedia.org/T352829)
* Fix disappearing selectors in certain nested blocks (Hannah Okwelum) [T352859](https://phabricator.wikimedia.org/T352859)
* Fix Less_Exception_Compiler when passing unquoted value to `color()` (Hannah Okwelum) [T353289](https://phabricator.wikimedia.org/T353289)
* Fix order of comments in `@font-face` blocks (Timo Tijhof) [T356706](https://phabricator.wikimedia.org/T356706)
* Fix string comparison to ignore quote type (Timo Tijhof) [T357160](https://phabricator.wikimedia.org/T357160)
* Fix string interpolation in selectors (Hannah Okwelum) [T353142](https://phabricator.wikimedia.org/T353142)

## v4.1.1

* Less_Parser: Faster `MatchQuoted` by using native `strcspn`. (Thiemo Kreuz)
* Less_Parser: Faster `parseEntitiesQuoted` by inlining `MatchQuoted`. (Thiemo Kreuz)
* Less_Parser: Faster `parseUnicodeDescriptor` and `parseEntitiesJavascript` by first-char checks. (Thiemo Kreuz)
* Less_Tree_Mixin_Call: Include mixin name in error message (Jeremy P)
* Fix mismatched casing in class names to fix autoloading on case-sensitive filesystems (Jeremy P)

## v4.1.0

* Add support for `@supports` blocks. (Anne Tomasevich) [T332923](http://phabricator.wikimedia.org/T332923)
* Less_Parser: Returning a URI from `SetImportDirs()` callbacks is now optional. (Timo Tijhof)

## v4.0.0

* Remove support for PHP 7.2 and 7.3. Raise requirement to PHP 7.4+.
* Remove support for `cache_method=php` and `cache_method=var_export`, only the faster and more secure `cache_method=serialize` is now available. The built-in cache remains disabled by default.
* Fix `url(#myid)` to be treated as absolute URL. [T331649](https://phabricator.wikimedia.org/T331688)
* Fix "Undefined property" PHP 8.1 warning when `calc()` is used with CSS `var()`. [T331688](https://phabricator.wikimedia.org/T331688)
* Less_Parser: Improve performance by removing MatchFuncs and NewObj overhead. (Timo Tijhof)

## v3.2.1

* Tree_Ruleset: Fix support for nested parent selectors (Timo Tijhof) [T204816](https://phabricator.wikimedia.org/T204816)
* Fix ParseError when interpolating variable after colon in selector (Timo Tijhof) [T327163](https://phabricator.wikimedia.org/T327163)
* Functions: Fix "Undefined property" warning on bad minmax arg
* Tree_Call: Include previous exception when catching functions (Robert Frunzke)

## v3.2.0

* Fix "Implicit conversion" PHP 8.1 warnings (Ayokunle Odusan)
* Fix "Creation of dynamic property" PHP 8.2 warnings (Bas Couwenberg)
* Fix "Creation of dynamic property" PHP 8.2 warnings (Rajesh Kumar)
* Tree_Url: Add support for "Url" type to `Parser::getVariables()` (ciroarcadio) [#51](https://github.com/wikimedia/less.php/pull/51)
* Tree_Import: Add support for importing URLs without file extension (Timo Tijhof) [#27](https://github.com/wikimedia/less.php/issues/27)

## v3.1.0

* Add PHP 8.0 support: Drop use of curly braces for sub-string eval (James D. Forrester)
* Make `Directive::__construct` $rules arg optional (fix PHP 7.4 warning) (Sam Reed)
* ProcessExtends: Improve performance by using a map for selectors and parents (Andrey Legayev)

## v3.0.0

* Raise PHP requirement from 7.1 to 7.2.9 (James Forrester)

## v2.0.0

* Relax PHP requirement down to 7.1, from 7.2.9 (Franz Liedke)
* Reflect recent breaking changes properly with the semantic versioning (James Forrester)

## v1.8.2

* Require PHP 7.2.9+, up from 5.3+ (James Forrester)
* release: Update Version.php with the current release ID (COBadger)
* Fix access array offset on value of type null (Michele Locati)
* Fix test suite on PHP 7.4 (Sergei Morozov)

## v1.8.1

* Another PHP 7.3 compatibility tweak

## v1.8.0

Library forked by Wikimedia, from [oyejorge/less.php](https://github.com/oyejorge/less.php).

* Supports up to PHP 7.3
* No longer tested against PHP 5, though it's still remains allowed in `composer.json` for HHVM compatibility
* Switched to [semantic versioning](https://semver.org/), hence version numbers now use 3 digits

## v1.7.0.13

* Fix composer.json (PSR-4 was invalid)

## v1.7.0.12

* set bin/lessc bit executable
* Add `gettingVariables` method to `Less_Parser`

## v1.7.0.11

* Fix realpath issue (windows)
* Set Less_Tree_Call property back to public ( Fix 258 266 267 issues from oyejorge/less.php)

## v1.7.0.10

* Add indentation option
* Add `optional` modifier for `@import`
* Fix $color in Exception messages
* take relative-url into account when building the cache filename
* urlArgs should be string no array()
* fix missing on NameValue type [#269](https://github.com/oyejorge/less.php/issues/269)

## v1.7.0.9

* Remove space at beginning of Version.php
* Revert require() paths in test interface
