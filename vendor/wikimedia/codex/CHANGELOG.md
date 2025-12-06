# Codex PHP release notes

## Version 0.7.1
* tests: Make PHPUnit data provider static
* build: Updating mediawiki/mediawiki-codesniffer to 47.0.0
* Remove unused $files from TemplateParser
* Localization: Make MW i18n actually work
* build: Updating mediawiki/mediawiki-phan-config to 0.16.0

## Version 0.7.0
* table.mustache: Remove <form> wrapper from template
* .gitattributes: Update and simplify

## Version 0.6.0

* Do not sanitize label text in renderer files

## Version 0.5.0

* Table: Allow raw HTML content in header
* Label: Do not sanitize label text and add taint annotations
* build: Updating mediawiki/mediawiki-codesniffer to 46.0.0

## Version 0.4.0

* README.md: Fix missing semicolon in code example
* InfoChip: Add icon handling support and status-based examples
* sandbox/package.json: Update Codex dependencies to use latest version
* Table: Allow raw HTML content in columns
* i18n: Import latest Codex message updates from MediaWiki
* Renderer: Fix usage of visually hidden labels in renderers
* InfoChipBuilder: Add status validation for InfoChip statuses
* README.md: Fix badge URLs to use HTTPS instead of HTTP
* build: Updating mediawiki/mediawiki-phan-config to 0.15.1

## Version 0.3.0

* .gitattributes: Update and expand
* Add .gitmessage template for standardized commit messages
* Add taint-check annotations to HtmlSnippet methods
* Adopt wikimedia/services for Codex service wiring
* Builder: Correct constructor class references for clarity
* HtmlSnippetBuilder: Remove sanitizer dependency
* ParamValidator: Simplify and improve parameter handling
* Remove HTMLPurifier usage and references
* Renderer: Call non static functions non statically
* SelectRenderer: Add new property to select data
* ServiceContainer: Remove call_user_func() for array-based resolvers
* ServiceContainer: Use explicit nullable type syntax for PHP 8.4
* ServiceWiring: Replace array_map with foreach in param trimming
* Table: Add support for empty state
* Table: Remove custom CSS-only icons
* TableRenderer: Add new properties to table data
* TemplateParser: Use LightnCandy for template rendering
* Tests: Add @coversDefaultClass annotations to test files
* ThumbnailRenderer: Fix null handling for backgroundImage and placeholder
* Utility/Codex: Fix builder state leakage by instantiating new instances
* build: Updating mediawiki/mediawiki-codesniffer to 45.0.0
* build: Updating mediawiki/mediawiki-phan-config to 0.15.0
* build: Updating phpunit/phpunit to 9.6.16
* build: Updating phpunit/phpunit to 9.6.21
* composer.json: Add autoloader configuration options
* sandbox/package.json: Update Codex dependencies to version 1.18.0
* tests: Use class-level @covers annotations instead @coversDefaultClass

## Version 0.2.0

* Pager: Remove unused Intuition references
* ServiceWiring: Move Intuition domain registration to TemplateRenderer
* ServiceWiring: Remove redundant mustache initialization logic
* TextInput, TextArea: Change boolean hasError to validation status
* ToggleSwitch: Fix typo in template
* doxyfile, composer.json, README.md: Update Codex PHP description
* i18n: Import latest Codex message updates from MediaWiki
* localization: Add flexible localization for varied environments
* sandbox: Update Codex dependencies to version 1.15.0
* sandbox: Update examples

## Version 0.1.0

Initial release
