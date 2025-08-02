<?php

namespace Wikimedia\Message;

/**
 * Converts MessageSpecifier objects to localized plain text in a certain language.
 *
 * The caller cannot modify the details of message translation, such as which
 * of multiple sources the message is taken from. Any such flags may be injected
 * into the factory constructor.
 *
 * Implementations of TextFormatter are not required to perfectly format
 * any message in any language. Implementations should make a best effort to
 * produce human-readable text.
 *
 * @package MediaWiki\MessageFormatter
 */
interface ITextFormatter {
	/**
	 * Get the internal language code in which format() is
	 * @return string
	 */
	public function getLangCode(): string;

	/**
	 * Convert a MessageSpecifier to text.
	 *
	 * The result is not safe for use as raw HTML.
	 *
	 * @param MessageSpecifier $message
	 * @return string
	 * @return-taint tainted
	 */
	public function format( MessageSpecifier $message ): string;
}
