[![Latest Stable Version]](https://packagist.org/packages/wikimedia/css-sanitizer) [![License]](https://packagist.org/packages/wikimedia/css-sanitizer)

Wikimedia CSS Parser & Sanitizer
================================

This library implements a CSS tokenizer, parser and grammar matcher in PHP that
mostly follows the [CSS Syntax Module Level 3 candidate recommendation dated 20
February 2014][CSSSYN], the [CSS Values and Units Module Level 3][CSSVAL], and the
[CSS Selectors Level 3][CSSSEL] grammar. It also provides a sanitizer that
recognizes various [CSS3 modules][CSSWORK].

Usage
-----

```php
use Wikimedia\CSS\Parser\Parser;
use Wikimedia\CSS\Sanitizer\StylesheetSanitizer;

/** Parse a stylesheet from a string **/

$parser = Parser::newFromString( $cssText );
$stylesheet = $parser->parseStylesheet();

/** Report any parser errors **/

foreach ( $parser->getParseErrors() as list( $code, $line, $pos ) ) {
	// $code is a string that should be suitable as a key for an i18n library.
	// See errors.md for details.
	$error = lookupI18nMessage( "css-parse-error-$code" );
	echo "Parse error: $error at line $line character $pos\n";
}

/** Apply sanitization to the stylesheet **/

// If you need to customize the defaults, copy the code of this method and
// modify it.
$sanitizer = StylesheetSanitizer::newDefault();
$newStylesheet = $sanitizer->sanitize( $stylesheet );

/** Report any sanitizer errors **/

foreach ( $sanitizer->getSanitizationErrors() as list( $code, $line, $pos ) ) {
	// $code is a string that should be suitable as a key for an i18n library.
	// See errors.md for details.
	$error = lookupI18nMessage( "css-sanitization-error-$code" );
	echo "Sanitization error: $error at line $line character $pos\n";
}

/** Convert the sanitized stylesheet back to text **/

$newText = (string)$newStylesheet;

// Or if you'd rather have it minified too
$minifiedText = Wikimedia\CSS\Util::stringify( $newStylesheet, [ 'minify' => true ] );
```

Running tests
-------------

    composer install --prefer-dist
    composer test

History
-------

We required a CSS sanitizer with several properties:

* Strict parsing according to modern standards.
* Includes line and character position for all errors.
* Configurable to limit unsafe constructs such as external URL references.
* Errors are easily localizable.

We could not find a library that fit these requirements, so we created one.


---
[Latest Stable Version]: https://poser.pugx.org/wikimedia/css-sanitizer/v/stable.svg
[License]: https://poser.pugx.org/wikimedia/css-sanitizer/license.svg
[CSSSYN]: https://www.w3.org/TR/2014/CR-css-syntax-3-20140220/
[CSSVAL]: https://www.w3.org/TR/2016/CR-css-values-3-20160929/
[CSSSEL]: https://www.w3.org/TR/2011/REC-css3-selectors-20110929/
[CSSWORK]: https://www.w3.org/Style/CSS/current-work
