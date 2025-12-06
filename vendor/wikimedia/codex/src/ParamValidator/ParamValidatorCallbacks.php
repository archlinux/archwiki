<?php
/**
 * ParamValidatorCallbacks.php
 *
 * This file is part of the Codex design system, the official design system
 * for Wikimedia projects. It provides the `ParamValidatorCallbacks` class, which
 * implements the `IParamValidatorCallbacks` interface. This class serves as a simple wrapper
 * around an associative array, providing standardized access to web request data
 * for Codex components.
 *
 * The `ParamValidatorCallbacks` class enables Codex to interact with request data in a consistent
 * manner without being tightly coupled to a specific web framework.
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

use Wikimedia\Codex\Contract\ParamValidator\IParamValidatorCallbacks;

/**
 * ParamValidatorCallbacks provides access to web request data using an array structure.
 *
 * The `ParamValidatorCallbacks` class implements the `IWebRequest` interface, allowing it
 * to provide a simple, array-based mechanism for accessing request parameters.
 * It adapts an associative array of request data, allowing Codex components to
 * retrieve values in a standardized way.
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
 * TODO: Once MediaWiki's ParamValidator is split into a standalone library, use it's `Callbacks` interface instead.
 * TODO: Once MediaWiki's ParamValidator is split into a standalone library, this class will no longer be necessary.
 */
class ParamValidatorCallbacks implements IParamValidatorCallbacks {

	/**
	 * The array containing request data from $_GET and $_POST.
	 *
	 * @var array<string, mixed>
	 */
	private array $params;

	/**
	 * Constructor for ParamValidatorCallbacks.
	 *
	 * @param array<string, mixed> $params Associative array of request parameters.
	 */
	public function __construct( array $params ) {
		$this->params = $params;
	}

	/**
	 * Test if a parameter exists in the request.
	 *
	 * This method checks whether a given parameter name exists in the request data.
	 *
	 * @since 0.3.0
	 * @param string $name The name of the parameter to check.
	 * @param array $options An associative array of options that may modify the behavior.
	 *
	 * @return bool True if the parameter exists; false otherwise.
	 */
	public function hasParam( string $name, array $options ): bool {
		return array_key_exists( $name, $this->params );
	}

	/**
	 * Fetch a value from the request data.
	 *
	 * This method retrieves the value of a specific parameter from the request data.
	 * If the parameter is not present, the provided default value will be returned.
	 *
	 * @since 0.3.0
	 * @param string $name The name of the parameter to fetch.
	 * @param mixed $default The default value to return if the parameter is not set.
	 * @param array $options An associative array of options that may modify the behavior.
	 *
	 * @return mixed The value of the parameter, or the `$default` value if the parameter is not set.
	 */
	public function getValue( string $name, $default, array $options ) {
		return $this->params[$name] ?? $default;
	}
}
