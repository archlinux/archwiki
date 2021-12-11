# wikimedia/minify

Minify is a PHP library for minification of JavaScript code and CSS stylesheets.

### Quick start

Install using [Composer](https://getcomposer.org/), from [Packagist.org](https://packagist.org/packages/wikimedia/minify):

```
composer require wikimedia/minify
```

### Usage

```php
use Wikimedia\Minify\JavaScriptMinifier;

$input = '
/**
 * @param a
 * @param b
 */
function sum(a, b) {
	// Add it up!
	return a + b;
}
';

$output = JavaScriptMinifier::minify( $input );
// Result:
// function sum(a,b){return a+b;}
```

```php
use Wikimedia\Minify\CSSMin;

$input = '
.foo,
.bar {
	/* comment */
	prop: value;
}
';

$output = CSSMin::minify( $input );
// Result:
// .foo,.bar{prop:value}
```

### Contribute

* Issue tracker: <https://phabricator.wikimedia.org/tag/wikimedia-minify/>
* Source code: <https://gerrit.wikimedia.org/g/mediawiki/libs/Minify>
* Submit patches via Gerrit: <https://www.mediawiki.org/wiki/Developer_account>

### See also

* High-level documentation: <https://www.mediawiki.org/wiki/ResourceLoader/Architecture>
