<?php
/**
 * IParamValidatorCallbacks.php
 *
 * This file is part of the Codex design system, the official design system
 * for Wikimedia projects. It defines the `IParamValidatorCallbacks` interface, which outlines
 * the methods required for handling various types of request parameters within
 * the Codex design system. Implementations of this interface can be used to
 * manage query parameters, file uploads, and other types of input data in a
 * consistent and flexible manner.
 *
 * The `IParamValidatorCallbacks` interface is crucial for decoupling request handling from
 * specific implementations, allowing for greater flexibility and testability
 * within the Codex system. By defining these methods, developers can create
 * custom implementations that suit their application's needs while adhering to
 * the expected behavior of the Codex components.
 *
 * @category Contract
 * @package  Codex\Contract
 * @since    0.2.0
 * @author   Doğu Abaris <abaris@null.net>
 * @license  https://www.gnu.org/copyleft/gpl.html GPL-2.0-or-later
 * @link     https://doc.wikimedia.org/codex/main/ Codex Documentation
 */

namespace Wikimedia\Codex\Contract\ParamValidator;

/**
 * Interface defining callbacks for handling request parameters.
 *
 * The `IParamValidatorCallbacks` interface outlines the methods required to interact with
 * various types of request parameters, including query parameters and file
 * uploads. Implementing this interface allows for consistent access to request
 * data across different components of the Codex system.
 *
 * @category Contract
 * @package  Codex\Contract\ParamValidator
 * @since    0.2.0
 * @author   Doğu Abaris <abaris@null.net>
 * @license  https://www.gnu.org/copyleft/gpl.html GPL-2.0-or-later
 * @link     https://doc.wikimedia.org/codex/main/ Codex Documentation
 * TODO: Once MediaWiki's ParamValidator is split into a standalone library,this interface will no longer be necessary.
 */
interface IParamValidatorCallbacks {

	/**
	 * Test if a parameter exists in the request
	 *
	 * @since 0.2.0
	 *
	 * @param string $name Parameter name
	 * @param array $options Options array
	 *
	 * @return bool True if present, false if absent.
	 *  Return false for file upload parameters.
	 */
	public function hasParam( string $name, array $options ): bool;

	/**
	 * Fetch a value from the request.
	 *
	 * This method retrieves the value of a specific parameter from the request.
	 * If the parameter is not present, the provided default value will be returned.
	 * For file upload parameters, this method should return the `$default` value.
	 *
	 * @since 0.2.0
	 * @param string $name The name of the parameter to fetch.
	 * @param mixed $default The default value to return if the parameter is not
	 *                       set in the request.
	 * @param array $options An associative array of options that may modify the
	 *                       behavior of the method.
	 * @return string|string[]|mixed The value of the parameter, or the `$default`
	 *                               value if the parameter is not set.
	 */
	public function getValue( string $name, $default, array $options );

}
