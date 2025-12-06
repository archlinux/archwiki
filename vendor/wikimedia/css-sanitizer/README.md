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
* [CSS Values and Units Module Level 4, 2024-03-12][CSSVAL]
* [CSS Selectors Level 3, 2018-11-06][CSSSEL]

The sanitizer recognizes the following CSS modules:

* [Align Level 3, 2025-03-11](https://www.w3.org/TR/2025/WD-css-align-3-20250311/)
* [Animations Level 1, 2023-03-02](https://www.w3.org/TR/2023/WD-css-animations-1-20230302/)
* [Backgrounds Level 3, 2024-03-11](https://www.w3.org/TR/2024/CRD-css-backgrounds-3-20240311/)
* [Break Level 3, 2018-12-04](https://www.w3.org/TR/2018/CR-css-break-3-20181204/)
* [Cascade Level 4, 2022-01-13](https://www.w3.org/TR/2022/CR-css-cascade-4-20220113/)
* [Color Level 4, 2025-04-24](https://www.w3.org/TR/2025/CRD-css-color-4-20250424)
* [Compositing Level 1, 2024-03-21](https://www.w3.org/TR/2024/CRD-compositing-1-20240321/)
* [Counter Styles Level 3, 2021-07-27](https://www.w3.org/TR/2021/CR-css-counter-styles-3-20210727/)
* [CSS Level 2, 2011-06-07](https://www.w3.org/TR/2011/REC-CSS2-20110607/)
* [Display Level 3, 2023-03-30](https://www.w3.org/TR/2023/CR-css-display-3-20230330/)
* [Easing Level 1, 2023-02-13](https://www.w3.org/TR/2023/CRD-css-easing-1-20230213/)
* [Filter Effects Level 1, 2018-12-18](https://www.w3.org/TR/2018/WD-filter-effects-1-20181218)
* [Flexbox Level 1, 2018-11-19](https://www.w3.org/TR/2018/CR-css-flexbox-1-20181119)
* [Fonts Level 3, 2018-09-20](https://www.w3.org/TR/2018/REC-css-fonts-3-20180920)
* [Grid Level 1, 2025-03-26](https://www.w3.org/TR/2025/CRD-css-grid-1-20250326/)
* [Images Level 3, 2023-12-18](https://www.w3.org/TR/2023/CRD-css-images-3-20231218/)
* [Lists and Counters Level 3, 2020-11-17](https://www.w3.org/TR/2020/WD-css-lists-3-20201117/)
* [Logical Properties and Values Level 1, 2018-08-27](https://www.w3.org/TR/2018/WD-css-logical-1-20180827/)
* [Masking Level 1, 2021-08-05](https://www.w3.org/TR/2021/CRD-css-masking-1-20210805/)
* [Multicol Level 1, 2019-10-15](https://www.w3.org/TR/2024/CR-css-multicol-1-20240516/)
* [Overflow Level 3, 2023-03-29](https://www.w3.org/TR/2023/WD-css-overflow-3-20230329/)
* [Overflow Level 4, 2023-03-21](https://www.w3.org/TR/2023/WD-css-overflow-4-20230321/)
* [Page Level 3, 2023-09-14](https://www.w3.org/TR/2023/WD-css-page-3-20230914/)
* [Position Level 3, 2025-03-11](https://www.w3.org/TR/2025/WD-css-position-3-20250311/)
* [Pseudo-Elements Level 4, 2022-12-30](https://www.w3.org/TR/2022/WD-css-pseudo-4-20221230/)
* [Ruby Level 1, 2022-12-31](https://www.w3.org/TR/2022/WD-css-ruby-1-20221231/)
* [Scroll Snap Module Level 1](https://www.w3.org/TR/2021/CR-css-scroll-snap-1-20210311/)
* [Shapes Level 1, 2022-11-15](https://www.w3.org/TR/2022/CRD-css-shapes-1-20221115/)
* [Sizing Level 3, 2021-12-17](https://www.w3.org/TR/2021/WD-css-sizing-3-20211217/)
* [Sizing Level 4, 2025-02-24](https://drafts.csswg.org/css-sizing-4/)
* [Text Level 3, 2024-09-30](https://www.w3.org/TR/2024/CRD-css-text-3-20240930/)
* [Text Decorations Level 3, 2022-05-05](https://www.w3.org/TR/2022/CRD-css-text-decor-3-20220505/)
* [Transforms Level 1, 2019-02-14](https://www.w3.org/TR/2019/CR-css-transforms-1-20190214)
* [Transforms Level 2, 2021-11-09](https://www.w3.org/TR/2021/WD-css-transforms-2-20211109/)
* [Transitions Level 1, 2018-10-11](https://www.w3.org/TR/2018/WD-css-transitions-1-20181011)
* [UI 3 Level 3, 2018-06-21](https://www.w3.org/TR/2018/REC-css-ui-3-20180621)
* [UI 4 Level 4, 2021-03-16](https://www.w3.org/TR/2021/WD-css-ui-4-20210316/)
* [Writing Modes Level 4, 2019-07-30](https://www.w3.org/TR/2019/CR-css-writing-modes-4-20190730)

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

Adding properties
-----------------

CSS specifications typically contain a summary of value grammars in the property
index section. These value grammars map directly to PHP code.

[Component value types](https://www.w3.org/TR/css-values-4/#component-types)

| Syntax         | css-sanitizer code                       |
|----------------|------------------------------------------|
| `foo`          | `new KeywordMatcher( 'foo' )`            |
| `foo \| bar`   | `new KeywordMatcher( [ 'foo', 'bar' ] )` |
| `<string>`     | `$matcherFactory->string()`              |
| `<url>`        | `$matcherFactory->url()`                 |
| `<integer>`    | `$matcherFactory->integer()`             |
| `<number>`     | `$matcherFactory->number()`              |
| `<ratio>`      | `$matcherFactory->ratio()`               |
| `<percentage>` | `$matcherFactory->percentage()`          |
| `<length>`     | `$matcherFactory->length()`              |
| `<frequency>`  | `$matcherFactory->frequency()`           |
| `<angle>`      | `$matcherFactory->angle()`               |
| `<time>`       | `$matcherFactory->time()`                |
| `<resolution>` | `$matcherFactory->resolution()`          |

[Component value combinators](https://www.w3.org/TR/css-values-4/#component-combinators)

| Syntax      | css-sanitizer code                   |
|-------------|--------------------------------------|
| `a b`       | `new Juxtaposition( [ a, b ] )`      |
| `a && b`    | `UnorderedGroup::allOf( [ a, b ] )`  |
| `a  \|\| b` | `UnorderedGroup::someOf( [ a, b ] )` |
| `a \| b`    | `new Alternative( [ a, b ] )`        |

[Component value multipliers](https://www.w3.org/TR/css-values-4/#component-multipliers)

| Syntax   | css-sanitizer code             |
|----------|--------------------------------|
| `a*`     | `Quantifier::star( a )`        |
| `a+`     | `Quantifier::plus( a )`        |
| `a?`     | `Quantifier::optional( a )`    |
| `a{3,4}` | `Quantifier::count( a, 3, 4 )` |
| `a#`     | `Quantifier::hash( a )`        |
| `a!`     | `new NonEmpty( a )`            |


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
[CSSSYN]: https://www.w3.org/TR/2021/CRD-css-syntax-3-20211224/
[CSSVAL]: https://www.w3.org/TR/2024/WD-css-values-4-20240312/
[CSSSEL]: https://www.w3.org/TR/2018/REC-selectors-3-20181106/
[CSSWORK]: https://www.w3.org/Style/CSS/current-work
