<?php
/**
 * ParamDefinitions.php
 *
 * This file is part of the Codex design system, which provides a standard interface
 * for parameter validation across various components.
 *
 * The `ParamDefinitions` class offers a centralized place to define validation rules
 * for parameters used in different contexts, such as "table" or "tabs". This helps keep
 * parameter handling consistent and maintainable throughout the codebase.
 *
 * @category ParamValidator
 * @package  Codex\ParamValidator
 * @since    0.3.0
 * @author   Doğu Abaris <abaris@null.net>
 * @license  https://www.gnu.org/copyleft/gpl.html GPL-2.0-or-later
 * @link     https://doc.wikimedia.org/codex/main/ Codex Documentation
 */

namespace Wikimedia\Codex\ParamValidator;

use InvalidArgumentException;

/**
 * ParamDefinitions provides a centralized mapping of parameter validation rules for different contexts.
 *
 * This class returns an associative array of parameter definitions keyed by context. Each context maps to
 * an array of parameters, each with its own type, default, and requirement flags.
 *
 * By separating parameter definitions from the validation logic, this class allows for a more maintainable
 * and flexible parameter handling system. Developers can add new contexts or modify existing parameter rules
 * without altering the underlying validation system.
 *
 * Example usage:
 *
 *     $definitions = ParamDefinitions::getDefinitionsForContext( 'tabs' );
 *     // Returns an array of parameter definitions applicable to the 'tabs' context.
 *
 * @category ParamValidator
 * @package  Codex\ParamValidator
 * @since    0.3.0
 * @author   Doğu Abaris <abaris@null.net>
 * @license  https://www.gnu.org/copyleft/gpl.html GPL-2.0-or-later
 * @link     https://doc.wikimedia.org/codex/main/ Codex Documentation
 */
class ParamDefinitions {
	/**
	 * Retrieve validation rules for a given context.
	 *
	 * This method looks up an associative array of parameter definitions based on the provided context.
	 * Each parameter definition specifies details like the parameter type, default value, and whether it
	 * is required. These rules are used in conjunction with the ParamValidator class to ensure consistent
	 * and reliable parameter handling.
	 *
	 * @since 0.3.0
	 * @param string $context The name of the context to fetch definitions for.
	 *                        For example: "pager", "table", "tabs".
	 *
	 * @return array<string, mixed> An associative array of parameter definitions.
	 *                              Keys are parameter names, and values are settings arrays.
	 *
	 * @throws InvalidArgumentException If no parameter definitions are found for the requested context.
	 */
	public static function getDefinitionsForContext( string $context ): array {
		$allDefinitions = [
			'table' => [
				'limit' => [
					ParamValidator::PARAM_TYPE => 'integer',
					ParamValidator::PARAM_DEFAULT => 10,
					ParamValidator::PARAM_REQUIRED => false,
				],
				'offset' => [
					ParamValidator::PARAM_TYPE => 'string',
					ParamValidator::PARAM_DEFAULT => '',
					ParamValidator::PARAM_REQUIRED => false,
				],
				'sort' => [
					ParamValidator::PARAM_TYPE => 'string',
					ParamValidator::PARAM_DEFAULT => '',
					ParamValidator::PARAM_REQUIRED => false,
				],
				'desc' => [
					ParamValidator::PARAM_TYPE => 'string',
					ParamValidator::PARAM_DEFAULT => '1',
					ParamValidator::PARAM_REQUIRED => false,
				],
				'asc' => [
					ParamValidator::PARAM_TYPE => 'string',
					ParamValidator::PARAM_DEFAULT => '1',
					ParamValidator::PARAM_REQUIRED => false,
				],
			],
			'tabs' => [
				'tab' => [
					ParamValidator::PARAM_TYPE => 'string',
					ParamValidator::PARAM_DEFAULT => '',
					ParamValidator::PARAM_REQUIRED => false,
				],
			],
		];

		if ( !isset( $allDefinitions[$context] ) ) {
			throw new InvalidArgumentException( "No parameter definitions found for context: $context" );
		}

		return $allDefinitions[$context];
	}
}
