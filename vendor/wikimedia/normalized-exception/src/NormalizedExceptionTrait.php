<?php

namespace Wikimedia\NormalizedException;

use Throwable;

/**
 * Trait for creating a normalized exception
 */
trait NormalizedExceptionTrait {

	/** @var string */
	protected $normalizedMessage;

	/** @var (int|float|string|bool)[] */
	protected $messageContext;

	/**
	 * Turn a PSR-3 style normalized message and context into a real message,
	 * by interpolating the context variables into the message string.
	 *
	 * Intended for use in exception constructors to construct the message
	 * that's passed to the parent constructor.
	 *
	 * @stable to call
	 * @param string $normalizedMessage A message string with zero or more
	 *   {}-wrapped tokens in it.
	 * @param array $context An array mapping tokens (without the braces) to
	 *   values. Fields not used in the message are allowed. Values that are
	 *   used in the message should be scalars or have a __toString() method.
	 * @return string
	 */
	public static function getMessageFromNormalizedMessage( string $normalizedMessage, array $context ) {
		$replacements = [];
		foreach ( $context as $placeholder => $value ) {
			if ( is_bool( $value ) ) {
				$stringValue = $value ? '<true>' : '<false>';
			} elseif ( $value === null ) {
				$stringValue = '<null>';
			} else {
				$stringValue = (string)$value;
			}
			$replacements['{' . $placeholder . '}'] = $stringValue;
		}
		return strtr( $normalizedMessage, $replacements );
	}

	/** @inheritDoc */
	public function getNormalizedMessage(): string {
		return $this->normalizedMessage;
	}

	/** @inheritDoc */
	public function getMessageContext(): array {
		return $this->messageContext;
	}

	/**
	 * Convenience method for exceptions which use the standard exception constructor.
	 * Such exceptions can just inherit this method as a constructor via `use ... { ... as ... }`.
	 * @param string $normalizedMessage The normalized exception message.
	 * @param array $messageContext The exception's context data.
	 * @param int $code Error code.
	 * @param Throwable|null $previous Previous exception, when chaining exceptions.
	 * @see self::getMessageFromNormalizedMessage() for more information on the message and context.
	 */
	public function normalizedConstructor(
		string $normalizedMessage,
		array $messageContext = [],
		int $code = 0,
		?Throwable $previous = null
	) {
		$this->normalizedMessage = $normalizedMessage;
		$this->messageContext = $messageContext;
		parent::__construct(
			self::getMessageFromNormalizedMessage( $normalizedMessage, $messageContext ),
			$code,
			$previous
		);
	}

}
