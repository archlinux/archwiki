<?php
/**
 * TypeDefinitions.php
 *
 * This file is part of the Codex design system, providing built-in type definitions
 * for parameter validation. The `TypeDefinitions` class includes validation logic for standard
 * types like integer, string, boolean, float, and array.
 *
 * @category ParamValidator
 * @package  Codex\ParamValidator
 * @since    0.3.0
 * @author   Doğu Abaris <abaris@null.net>
 * @license  https://www.gnu.org/copyleft/gpl.html GPL-2.0-or-later
 * @link     https://doc.wikimedia.org/codex/main/ Codex Documentation
 */

namespace Wikimedia\Codex\ParamValidator;

use UnexpectedValueException;

/**
 * TypeDefinitions provides built-in type definitions for parameter validation.
 *
 * This class defines a set of standard type validation functions that are used by
 * the `ParamValidator` to validate request parameters. Developers can also extend
 * these definitions by adding custom types.
 *
 * @category ParamValidator
 * @package  Codex\ParamValidator
 * @since    0.3.0
 * @author   Doğu Abaris <abaris@null.net>
 * @license  https://www.gnu.org/copyleft/gpl.html GPL-2.0-or-later
 * @link     https://doc.wikimedia.org/codex/main/ Codex Documentation
 * TODO: Once MediaWiki's ParamValidator is split into a standalone library,this class will no longer be necessary.
 */
class TypeDefinitions {

	/**
	 * Get default type definitions.
	 *
	 * This method returns an array of callable functions for validating standard
	 * parameter types, such as integer, string, boolean, float, and array.
	 *
	 * @since 0.3.0
	 * @return array<string, callable> An array of type definitions.
	 */
	public static function getDefaultTypeDefs(): array {
		return [
			'integer' => static function ( string $name, $value ): int {
				if ( !is_numeric( $value ) || (int)$value != $value ) {
					throw new UnexpectedValueException( "$name must be an integer." );
				}
				return (int)$value;
			},
			'string' => static function ( string $name, $value ): string {
				if ( !is_string( $value ) ) {
					throw new UnexpectedValueException( "$name must be a string." );
				}
				return $value;
			},
			'boolean' => static function ( string $name, $value ): bool {
				if ( !is_bool( $value ) && $value !== 'true' && $value !== 'false' ) {
					throw new UnexpectedValueException( "$name must be a boolean." );
				}
				return filter_var( $value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE ) ?? false;
			},
			'float' => static function ( string $name, $value ): float {
				if ( !is_numeric( $value ) || (float)$value != $value ) {
					throw new UnexpectedValueException( "$name must be a float." );
				}
				return (float)$value;
			},
			'array' => static function ( string $name, $value ): array {
				if ( !is_array( $value ) ) {
					throw new UnexpectedValueException( "$name must be an array." );
				}
				return $value;
			},
		];
	}
}
