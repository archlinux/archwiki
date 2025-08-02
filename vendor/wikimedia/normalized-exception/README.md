[![Latest Stable Version]](https://packagist.org/packages/wikimedia/normalized-exception) [![License]](https://packagist.org/packages/wikimedia/normalized-exception)

NormalizedException
=====================

A minimal library to facilitate [PSR-3][]-friendly exception handling.

Additional documentation about the library can be found on
[MediaWiki.org](https://www.mediawiki.org/wiki/NormalizedException).


Usage
-----

The NormalizedException library provides an INormalizedException interface
that exception classes can use to support a standardized way of logging
their error messages in a PSR-3 compatible manner.

If you don't care about the exception class, you can use the standard implementation:

```php
use Wikimedia\NormalizedException\NormalizedException;

throw new NormalizedException( 'Invalid value: {value}', [ 'value' => $value ] );
```

```php
use Wikimedia\NormalizedException\INormalizedException;

try {
	mightThrow();
} catch ( INormalizedException $e ) {
	$psr3Logger->error( $e->getNormalizedMessage(), $e->getMessageContext() );
	echo 'Error: ' . $e->getMessage();
}
```

To make a specific exception class normalized, if it's constructed from a message
and optionally an error code and a previous exception (like most PHP core exceptions),
you can use a trait and a default constructor provided by that trait:

```php
use Exception
use Wikimedia\NormalizedException\INormalizedException;
use Wikimedia\NormalizedException\NormalizedExceptionTrait;

class MyException extends Exception implements INormalizedException {
	use NormalizedExceptionTrait {
		NormalizedExceptionTrait::normalizedConstructor as __construct;
	}
}
```

```php
throw new MyException( 'Invalid value!' );
throw new MyException( 'Invalid value: {value}', [ 'value' => $value ] );
throw new MyException( 'Invalid value: {value}', [ 'value' => $value ], /* code */ -1, $previous );
```

Exceptions with different parameters need their own constructor:

```php
use Wikimedia\NormalizedException\INormalizedException;
use Wikimedia\NormalizedException\NormalizedExceptionTrait;

class MyException extends SomeException implements INormalizedException {
	use NormalizedExceptionTrait;

	public function __construct(
		string $normalizedMessage,
		array $messageContext = [],
		$otherParam1,
		$otherParam2
	) {
		// these properties are defined by the trait and must be set
		$this->normalizedMessage = $normalizedMessage;
		$this->messageContext = $messageContext;

		// replaces the PSR-3 tokens
		$message = self::getMessageFromNormalizedMessage( $normalizedMessage, $messageContext );

		parent::__construct( $message, $otherParam1, $otherParam2 );
	}
}
```

Running tests
-------------

```
composer install --dev
composer test
```

History
-------

This library was split out of [MediaWiki][] changeset [670465][] during the
MediaWiki 1.37 development cycle.


---
[PSR-3]: https://www.php-fig.org/psr/psr-3/
[MediaWiki]: https://www.mediawiki.org/wiki/MediaWiki
[670465]: https://gerrit.wikimedia.org/r/c/mediawiki/core/+/670465
[Latest Stable Version]: https://poser.pugx.org/wikimedia/normalized-exception/v/stable.svg
[License]: https://poser.pugx.org/wikimedia/normalized-exception/license.svg
