[![Latest Stable Version]](https://packagist.org/packages/wikimedia/css-sanitizer) [![License]](https://packagist.org/packages/wikimedia/css-sanitizer)

Wikimedia CSS Parser & Sanitizer
================================

This library implements a CSS tokenizer, parser and grammar matcher in PHP.

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

Conformance
-----------

The library follows the following grammar specifications:

* [CSS Syntax Level 3, 2019-07-16][CSSSYN]
* [CSS Values and Units Module Level 3, 2019-06-06][CSSVAL]
* [CSS Selectors Level 3, 2018-11-06][CSSSEL]

The sanitizer recognizes the following CSS modules:

* [Align Level 3, 2018-12-06](https://www.w3.org/TR/2018/WD-css-align-3-20181206/)
* [Animations Level 1, 2018-10-11](https://www.w3.org/TR/2018/WD-css-animations-1-20181011/)
* [Backgrounds Level 3, 2017-10-17](https://www.w3.org/TR/2017/CR-css-backgrounds-3-20171017/)
* [Break Level 3, 2018-12-04](https://www.w3.org/TR/2018/CR-css-break-3-20181204/)
* [Cascade Level 4, 2018-08-28](https://www.w3.org/TR/2018/CR-css-cascade-4-20180828)
* [Color Level 3, 2018-06-19](https://www.w3.org/TR/2018/REC-css-color-3-20180619)
* [Compositing Level 1, 2015-01-13](https://www.w3.org/TR/2015/CR-compositing-1-20150113/)
* [CSS Level 2, 2011-06-07](https://www.w3.org/TR/2011/REC-CSS2-20110607/)
* [Display Level 3, 2019-07-11](https://www.w3.org/TR/2019/CR-css-display-3-20190711)
* [Filter Effects Level 1, 2018-12-18](https://www.w3.org/TR/2018/WD-filter-effects-1-20181218)
* [Flexbox Level 1, 2018-11-19](https://www.w3.org/TR/2018/CR-css-flexbox-1-20181119)
* [Fonts Level 3, 2018-09-20](https://www.w3.org/TR/2018/REC-css-fonts-3-20180920)
* [Grid Level 1, 2017-12-14](https://www.w3.org/TR/2017/CR-css-grid-1-20171214/)
* [Images Level 3, 2019-10-10](https://www.w3.org/TR/2019/CR-css-images-3-20191010)
* [Masking Level 1, 2014-08-26](https://www.w3.org/TR/2014/CR-css-masking-1-20140826/)
* [Multicol Level 1, 2019-10-15](https://www.w3.org/TR/2019/WD-css-multicol-1-20191015)
* [Overflow Level 3, 2018-07-31](https://www.w3.org/TR/2018/WD-css-overflow-3-20180731)
* [Page Level 3, 2018-10-18](https://www.w3.org/TR/2018/WD-css-page-3-20181018)
* [Position Level 3, 2016-05-17](https://www.w3.org/TR/2016/WD-css-position-3-20160517/)
* [Shapes Level 1, 2014-03-20](https://www.w3.org/TR/2014/CR-css-shapes-1-20140320/)
* [Sizing Level 3, 2019-05-22](https://www.w3.org/TR/2019/WD-css-sizing-3-20190522)
* [Text Level 3, 2019-11-13](https://www.w3.org/TR/2019/WD-css-text-3-20191113)
* [Text Decorations Level 3, 2019-08-13](https://www.w3.org/TR/2019/CR-css-text-decor-3-20190813)
* [Easing Level 1, 2019-04-30](https://www.w3.org/TR/2019/CR-css-easing-1-20190430/)
* [Transforms Level 1, 2019-02-14](https://www.w3.org/TR/2019/CR-css-transforms-1-20190214)
* [Transitions Level 1, 2018-10-11](https://www.w3.org/TR/2018/WD-css-transitions-1-20181011)
* [UI 3 Level 3, 2018-06-21](https://www.w3.org/TR/2018/REC-css-ui-3-20180621)
* [UI 4 Level 4, 2020-01-02](https://www.w3.org/TR/2020/WD-css-ui-4-20200102)
* [Writing Modes Level 4, 2019-07-30](https://www.w3.org/TR/2019/CR-css-writing-modes-4-20190730)
* [Selectors Level 4, 2019-02-25](https://www.w3.org/TR/2019/WD-css-pseudo-4-20190225/)
* [Logical Properties and Values Level 1, 2018-08-27](https://www.w3.org/TR/2018/WD-css-logical-1-20180827/)

And also,
* The `touch-action` property from
[Pointer Events Level 2, 2019-04-04](https://www.w3.org/TR/2019/REC-pointerevents2-20190404/)
* `:dir()` pseudo-class from [Selectors Level 4, 2022-11-11](https://www.w3.org/TR/2022/WD-selectors-4-20221111/#the-dir-pseudo)
* Accessibility related media features from [Media Queries Level 5](https://drafts.csswg.org/mediaqueries-5/#mf-user-preferences) including prefers-reduced-motion, prefers-reduced-transparency, prefers-contrast and forced-colors.
* `light-dark()` color function from [Color Module Level 5, 2024-02-29](https://www.w3.org/TR/2024/WD-css-color-5-20240229/#funcdef-light-dark)

Running tests
-------------

    composer install --prefer-dist
    composer test

Releasing a new version
-----------------------

This package uses `wikimedia/update-history` and its conventions.

See https://www.mediawiki.org/wiki/UpdateHistory for details.

History
-------

We required a CSS sanitizer with several properties:

* Strict parsing according to modern standards.
* Includes line and character position for all errors.
* Configurable to limit unsafe constructs such as external URL references.
* Errors are easily localizable.

We could not find a library that fit these requirements, so we created one.

Additional release history is in [`HISTORY.md`](./HISTORY.md).

---
[Latest Stable Version]: https://poser.pugx.org/wikimedia/css-sanitizer/v/stable.svg
[License]: https://poser.pugx.org/wikimedia/css-sanitizer/license.svg
[CSSSYN]: https://www.w3.org/TR/2019/CR-css-syntax-3-20190716/
[CSSVAL]: https://www.w3.org/TR/2019/CR-css-values-3-20190606/
[CSSSEL]: https://www.w3.org/TR/2018/REC-selectors-3-20181106/
[CSSWORK]: https://www.w3.org/Style/CSS/current-work
