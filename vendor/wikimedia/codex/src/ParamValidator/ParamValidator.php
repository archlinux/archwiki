<?php
/**
 * ParamValidator.php
 *
 * This file is part of the Codex design system, providing advanced validation
 * for web request parameters. The `ParamValidator` class integrates with
 * `ParamValidatorCallbacks` to ensure type safety, multi-value support, and constraint enforcement.
 *
 * This class is inspired by and borrows concepts from MediaWiki's `ParamValidator`.
 * While it has been adapted to meet the requirements of the Codex system, it maintains
 * a similar approach to parameter validation. Any direct code or conceptual borrowing
 * has been done with due acknowledgment of MediaWiki's contributors.
 *
 * @category ParamValidator
 * @package  Codex\ParamValidator
 * @since    0.3.0
 * @author   Doğu Abaris <abaris@null.net>
 * @license  https://www.gnu.org/copyleft/gpl.html GPL-2.0-or-later
 * @link     https://doc.wikimedia.org/codex/main/ Codex Documentation
 */

namespace Wikimedia\Codex\ParamValidator;

use DomainException;
use UnexpectedValueException;
use Wikimedia\Codex\Contract\ParamValidator\IParamValidatorCallbacks;

/**
 * ParamValidator provides advanced validation for web request parameters.
 *
 * The `ParamValidator` class validates and normalizes parameters fetched via
 * the `IParamValidatorCallbacks` interface. It supports multi-value parameters,
 * custom types, and a variety of constraints to standardize parameter validation.
 *
 * This implementation is inspired by MediaWiki's `ParamValidator`, with notable
 * adaptations for Codex-specific needs. The approach and structure of this class
 * owe much to the original design, and credit is extended to MediaWiki's contributors.
 *
 * @category ParamValidator
 * @package  Codex\ParamValidator
 * @since    0.3.0
 * @author   Doğu Abaris <abaris@null.net>
 * @license  https://www.gnu.org/copyleft/gpl.html GPL-2.0-or-later
 * @link     https://doc.wikimedia.org/codex/main/ Codex Documentation
 * TODO: Once MediaWiki's ParamValidator is split into a standalone library,this class will no longer be necessary.
 */
class ParamValidator {
	/**
	 * Provides access to web request parameters through callbacks.
	 */
	private IParamValidatorCallbacks $callbacks;
	/**
	 * Holds type definitions for parameter validation.
	 *
	 * @var array<string, callable|array>
	 */
	private array $typeDefs;
	/**
	 * Default limit for the number of values allowed for multi-value parameters.
	 */
	private int $ismultiLimit1;
	/**
	 * High limit for the number of values allowed for multi-value parameters when
	 * high limits are enabled.
	 */
	private int $ismultiLimit2;
	/**
	 * Default value for the parameter. If omitted, null is the default.
	 */
	public const PARAM_DEFAULT = 'param-default';
	/**
	 * Type of the parameter, defined as a string or an array of enumerated values.
	 */
	public const PARAM_TYPE = 'param-type';
	/**
	 * Indicates that the parameter is required.
	 */
	public const PARAM_REQUIRED = 'param-required';
	/**
	 * Indicates that the parameter accepts multiple values.
	 */
	public const PARAM_ISMULTI = 'param-ismulti';

	/**
	 * Constructor for ParamValidator.
	 *
	 * @since 0.3.0
	 *
	 * @param IParamValidatorCallbacks $callbacks Provides access to web request parameters.
	 * @param array<string, mixed> $options Configuration for the validator, including:
	 *                                      - `typeDefs`: Array of type definitions.
	 *                                      - `ismultiLimits`: Two integers for multi-value limits.
	 */
	public function __construct( IParamValidatorCallbacks $callbacks, array $options = [] ) {
		$this->callbacks = $callbacks;

		$this->typeDefs = array_merge(
			TypeDefinitions::getDefaultTypeDefs(),
			$options['typeDefs'] ?? []
		);

		$this->ismultiLimit1 = $options['ismultiLimits'][0] ?? 50;
		$this->ismultiLimit2 = $options['ismultiLimits'][1] ?? 500;
	}

	/**
	 * Normalizes parameter settings by ensuring they have consistent structure and defaults.
	 *
	 * @since 0.3.0
	 * @param mixed $settings Parameter settings or default value.
	 * @return array<string, mixed> Normalized settings.
	 */
	public function normalizeSettings( $settings ): array {
		if ( !is_array( $settings ) ) {
			$settings = [ self::PARAM_DEFAULT => $settings ];
		}

		if ( !isset( $settings[self::PARAM_TYPE] ) ) {
			$settings[self::PARAM_TYPE] = gettype( $settings[self::PARAM_DEFAULT] ?? null );
		}

		return $settings;
	}

	/**
	 * Validates a parameter value against its settings.
	 *
	 * @since 0.3.0
	 *
	 * @param string $name Parameter name.
	 * @param mixed $value Parameter value.
	 * @param array<string, mixed> $settings Parameter settings.
	 * @param array<string, mixed> $options Additional options for validation.
	 * @return mixed The validated value or values.
	 * @throws DomainException If the type is unknown.
	 * @throws UnexpectedValueException If validation fails.
	 */
	public function validateValue( string $name, $value, array $settings, array $options = [] ) {
		$settings = $this->normalizeSettings( $settings );

		if ( !isset( $this->typeDefs[$settings[self::PARAM_TYPE]] ) ) {
			throw new DomainException( "Unknown type: {$settings[self::PARAM_TYPE]}" );
		}

		$typeDef = $this->typeDefs[$settings[self::PARAM_TYPE]];

		if ( $settings[self::PARAM_ISMULTI] ?? false ) {
			if ( !is_array( $value ) ) {
				throw new UnexpectedValueException( "Multi-value parameter '$name' must be an array." );
			}

			$limit = $options['useHighLimits'] ?? false ? $this->ismultiLimit2 : $this->ismultiLimit1;

			if ( count( $value ) > $limit ) {
				throw new UnexpectedValueException( "Too many values for parameter '$name'. Limit: $limit" );
			}

			return array_map( static fn ( $v ) => $typeDef( $name, $v, $settings, $options ), $value );
		}

		return $typeDef( $name, $value, $settings, $options );
	}

	/**
	 * Validates and fetches a parameter value.
	 *
	 * @since 0.3.0
	 *
	 * @param string $name Parameter name.
	 * @param array<string, mixed> $settings Parameter settings.
	 * @param array<string, mixed> $options Additional options for validation.
	 * @return array<string, mixed> Validated value.
	 * @throws UnexpectedValueException If the parameter is missing and required.
	 */
	public function getValue( string $name, array $settings, array $options = [] ): array {
		$value = $this->callbacks->getValue( $name, $settings[self::PARAM_DEFAULT] ?? null, $options );

		if ( $value === null && ( $settings[self::PARAM_REQUIRED] ?? false ) ) {
			throw new UnexpectedValueException( "Missing required parameter: $name" );
		}

		return $this->validateValue( $name, $value, $settings, $options );
	}
}
