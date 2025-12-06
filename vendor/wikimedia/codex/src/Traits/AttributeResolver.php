<?php
/**
 * AttributeResolver.php
 *
 * This file is part of the Codex design system, which provides a standardized
 * approach to rendering HTML attributes. The `AttributeResolver` trait
 * is responsible for converting associative arrays of HTML attributes into
 * a string format suitable for use in HTML tags.
 *
 * @category Traits
 * @package  Codex\Traits
 * @since    0.1.0
 * @author   Doğu Abaris <abaris@null.net>
 * @license  https://www.gnu.org/copyleft/gpl.html GPL-2.0-or-later
 * @link     https://doc.wikimedia.org/codex/main/ Codex Documentation
 */

namespace Wikimedia\Codex\Traits;

/**
 * AttributeResolver is a utility trait responsible for converting an associative
 * array of HTML attributes into a string format suitable for use in HTML tags.
 *
 * The `AttributeResolver` trait provides a method to resolve attributes,
 * handling boolean attributes and concatenating array-based attributes.
 *
 * @category Traits
 * @package  Codex\Traits
 * @since    0.1.0
 * @author   Doğu Abaris <abaris@null.net>
 * @license  https://www.gnu.org/copyleft/gpl.html GPL-2.0-or-later
 * @link     https://doc.wikimedia.org/codex/main/ Codex Documentation
 */
trait AttributeResolver {

	/**
	 * Resolves an associative array of HTML attributes into a string for an HTML tag.
	 * Boolean attributes (like `disabled`) are rendered without a value.
	 * Array-based attributes (like `class`) are concatenated into a single string.
	 *
	 * @since 0.1.0
	 * @param array $attributes Key-value pairs of HTML attributes.
	 * @return string The attributes as a string, ready to be included in an HTML tag.
	 */
	public function resolve( array $attributes ): string {
		// Return an empty string if there are no attributes
		if ( !$attributes ) {
			return '';
		}

		$resolvedAttributes = [];

		foreach ( $attributes as $key => $value ) {

			// If the value is true, include the key as an attribute without a value.
			if ( $value === true ) {
				$resolvedAttributes[] = $key;
			} elseif ( is_array( $value ) ) {
				// If the value is an array (e.g., 'data' => ['toggle' => 'modal']), flatten it into a string
				$attributeValue = implode( ' ', $value );
				$resolvedAttributes[] = "$key=\"$attributeValue\"";
			} elseif ( $value !== false && $value !== null ) {
				// Handle other scalar values
				$attributeValue = (string)$value;
				$resolvedAttributes[] = "$key=\"$attributeValue\"";
			}
		}

		return implode( ' ', $resolvedAttributes );
	}
}
