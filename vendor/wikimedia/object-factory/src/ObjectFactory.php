<?php
/**
 * @license GPL-2.0-or-later
 * @file
 */

namespace Wikimedia\ObjectFactory;

use Closure;
use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use UnexpectedValueException;

/**
 * Construct objects based on a specification array.
 *
 * Contents of the specification array are as follows:
 *
 *     'factory' => callable,
 *     'class' => string,
 *
 * The specification array must contain either a 'class' key with string value
 * that specifies the class name to instantiate or a 'factory' key with a
 * callable (is_callable() === true). If both are passed, 'factory' takes
 * precedence but an InvalidArgumentException will be thrown if the resulting
 * object is not an instance of the named class.
 *
 *     'args' => array,
 *     'closure_expansion' => bool, // default true
 *     'spec_is_arg' => bool, // default false
 *     'services' => (string|null)[], // default empty
 *     'optional_services' => (string|null)[], // default empty
 *
 * The 'args' key, if provided, specifies arguments to pass to the constructor/callable.
 * Values in 'args' which are Closure instances will be expanded by invoking
 * them with no arguments before passing the resulting value on to the
 * constructor/callable. This can be used to pass live objects to the
 * constructor/callable. This behavior can be suppressed by adding
 * closure_expansion => false to the specification.
 *
 * If 'spec_is_arg' => true is in the specification, 'args' is ignored. The
 * entire spec array is passed to the constructor/callable instead.
 *
 * If 'services' is supplied and non-empty (and a service container is available),
 * the named services are requested from the PSR-11 service container and
 * prepended before 'args'. `null` values in 'services' are passed to the constructor
 * unchanged.
 *
 * Optional services declared via 'optional_services' are handled the same,
 * except that if the service is not available from the service container
 * `null` is passed as a parameter instead. Optional services are appended
 * directly after the normal required services.
 *
 * If any extra arguments are passed in the options to getObjectFromSpec() or
 * createObject(), these are prepended before the 'services' and 'args'.
 *
 *     'calls' => array
 *
 * The specification may also contain a 'calls' key that describes method
 * calls to make on the newly created object before returning it. This
 * pattern is often known as "setter injection". The value of this key is
 * expected to be an associative array with method names as keys and
 * argument lists as values. The argument list will be expanded (or not)
 * in the same way as the 'args' key for the main object.
 *
 * Note these calls are not passed the extra arguments.
 *
 * @copyright © 2014 Wikimedia Foundation and contributors
 */
class ObjectFactory {

	/** @var ContainerInterface Service container */
	protected ContainerInterface $serviceContainer;

	/**
	 * @param ContainerInterface $serviceContainer Service container
	 */
	public function __construct( ContainerInterface $serviceContainer ) {
		$this->serviceContainer = $serviceContainer;
	}

	/**
	 * Instantiate an object based on a specification array.
	 *
	 * This calls getObjectFromSpec(), with the ContainerInterface that was
	 * passed to the constructor passed as `$options['serviceContainer']`.
	 *
	 * @template T of object
	 *
	 * @phpcs:ignore Generic.Files.LineLength
	 * @param class-string<T>|callable(mixed ...$args):T|array{class?:class-string<T>,factory?:callable(mixed ...$args):T,args?:array,services?:array<string|null>,optional_services?:array<string|null>,calls?:string[],closure_expansion?:bool,spec_is_arg?:bool} $spec
	 *   Specification array, or (when the respective $options flag is set) a class name or callable. Allowed fields
	 *   (see class documentation for more details):
	 *   - 'class': (string) Class of the object to create. If 'factory' is also specified,
	 *     it will be used to validate the object.
	 *   - 'factory': (callable) Factory method for creating the object.
	 *   - 'args': (array) Arguments to pass to the constructor or the factory method.
	 *   - 'services': (array of string/null) List of services to pass as arguments. Each
	 *     name will be looked up in the container given to ObjectFactory in its constructor,
	 *     and the results prepended to the argument list. Null values are passed unchanged.
	 *   - 'optional_services': (array of string/null) Handled the same as services, but if
	 *     the service is unavailable from the service container the parameter is set to 'null'
	 *     instead of causing an error.
	 *   - 'calls': (array) A list of calls to perform on the created object, for setter
	 *     injection. Keys of the array are method names and values are argument lists
	 *     (as arrays). These arguments are not affected by any of the other specification
	 *     fields that manipulate constructor arguments.
	 *   - 'closure_expansion': (bool, default true) Whether to expand (execute) closures
	 *     in 'args'.
	 *   - 'spec_is_arg': (bool, default false) When true, 'args' is ignored and the entire
	 *     specification array is passed as an argument.
	 *   One of 'class' and 'factory' is required.
	 * @param array{allowClassName?:bool,allowCallable?:bool,extraArgs?:array,assertClass?:class-string<T>} $options
	 *  Allowed keys are:
	 *  - 'allowClassName': (bool) If set and truthy, $spec may be a string class name.
	 *    In this case, it will be treated as if it were `[ 'class' => $spec ]`.
	 *  - 'allowCallable': (bool) If set and truthy, $spec may be a callable. In this
	 *    case, it will be treated as if it were `[ 'factory' => $spec ]`.
	 *  - 'extraArgs': (array) Extra arguments to pass to the constructor/callable. These
	 *    will come before services and normal args.
	 *  - 'assertClass': (string) Throw an UnexpectedValueException if the spec
	 *    does not create an object of this class.
	 * @return T
	 * @throws InvalidArgumentException when object specification is not valid.
	 * @throws UnexpectedValueException when the factory returns a non-object, or
	 *  the object is not an instance of the specified class.
	 */
	public function createObject( $spec, array $options = [] ) {
		$options['serviceContainer'] = $this->serviceContainer;
		return static::getObjectFromSpec( $spec, $options );
	}

	/**
	 * Instantiate an object based on a specification array.
	 *
	 * @template T of object
	 *
	 * @phpcs:disable Generic.Files.LineLength
	 * @param class-string<T>|callable(mixed ...$args):T|array{class?:class-string<T>,factory?:callable(mixed ...$args):T,args?:array,services?:array<string|null>,optional_services?:array<string|null>,calls?:string[],closure_expansion?:bool,spec_is_arg?:bool} $spec
	 *  As for createObject().
	 * @param array{allowClassName?:bool,allowCallable?:bool,extraArgs?:array,assertClass?:class-string<T>,serviceContainer?:ContainerInterface} $options
	 *  As for createObject(). Additionally:
	 *  - 'serviceContainer': (ContainerInterface) PSR-11 service container to use
	 *    to handle 'services'.
	 * @phpcs:enable
	 * @return T
	 * @throws InvalidArgumentException when object specification is not valid.
	 * @throws InvalidArgumentException when $spec['services'] or $spec['optional_services']
	 *  is used without $options['serviceContainer'] being set and implementing ContainerInterface.
	 * @throws UnexpectedValueException when the factory returns a non-object, or
	 *  the object is not an instance of the specified class.
	 */
	public static function getObjectFromSpec( $spec, array $options = [] ) {
		$spec = static::validateSpec( $spec, $options );

		$expandArgs = !isset( $spec['closure_expansion'] ) || $spec['closure_expansion'];

		if ( !empty( $spec['spec_is_arg'] ) ) {
			$args = [ $spec ];
		} else {
			$args = $spec['args'] ?? [];

			// $args should be a non-associative array; show nice error if that's not the case
			if ( !array_is_list( $args ) ) {
				throw new InvalidArgumentException( '\'args\' cannot be an associative array' );
			}

			if ( $expandArgs ) {
				$args = static::expandClosures( $args );
			}
		}

		$services = [];
		if ( !empty( $spec['services'] ) || !empty( $spec['optional_services'] ) ) {
			$container = $options['serviceContainer'] ?? null;
			if ( !$container instanceof ContainerInterface ) {
				throw new InvalidArgumentException(
					'\'services\' and \'optional_services\' cannot be used without a service container'
				);
			}

			if ( !empty( $spec['services'] ) ) {
				foreach ( $spec['services'] as $service ) {
					$services[] = $service === null ? null : $container->get( $service );
				}
			}

			if ( !empty( $spec['optional_services'] ) ) {
				foreach ( $spec['optional_services'] as $service ) {
					if ( $service !== null && $container->has( $service ) ) {
						$services[] = $container->get( $service );
					} else {
						// Either $service was null, or the service was not available
						$services[] = null;
					}
				}
			}
		}

		$args = array_merge(
			$options['extraArgs'] ?? [],
			$services,
			$args
		);

		if ( isset( $spec['factory'] ) ) {
			$obj = $spec['factory']( ...$args );
			if ( !is_object( $obj ) ) {
				throw new UnexpectedValueException( '\'factory\' did not return an object' );
			}
			// @phan-suppress-next-line PhanRedundantCondition
			if ( isset( $spec['class'] ) && !$obj instanceof $spec['class'] ) {
				throw new UnexpectedValueException(
					'\'factory\' was expected to return an instance of ' . $spec['class']
					. ', got ' . get_class( $obj )
				);
			}
		} elseif ( isset( $spec['class'] ) ) {
			$clazz = $spec['class'];
			$obj = new $clazz( ...$args );
		} else {
			throw new InvalidArgumentException(
				'Provided specification lacks both \'factory\' and \'class\' parameters.'
			);
		}

		// @phan-suppress-next-line PhanRedundantCondition
		if ( isset( $options['assertClass'] ) && !$obj instanceof $options['assertClass'] ) {
			throw new UnexpectedValueException(
				'Expected instance of ' . $options['assertClass'] . ', got ' . get_class( $obj )
			);
		}

		if ( isset( $spec['calls'] ) && is_array( $spec['calls'] ) ) {
			// Call additional methods on the newly created object
			foreach ( $spec['calls'] as $method => $margs ) {
				if ( $expandArgs ) {
					$margs = static::expandClosures( $margs );
				}
				$obj->$method( ...$margs );
			}
		}

		return $obj;
	}

	/**
	 * Convert a string or callable to a spec array
	 *
	 * @param array|string|callable $spec As for createObject() or getObjectFromSpec()
	 * @param array $options As for createObject() or getObjectFromSpec()
	 * @return array Specification array
	 * @throws InvalidArgumentException when object specification does not
	 *  contain 'class' or 'factory' keys
	 */
	protected static function validateSpec( $spec, array $options ): array {
		if ( is_callable( $spec ) ) {
			if ( empty( $options['allowCallable'] ) ) {
				throw new InvalidArgumentException(
					'Passing a raw callable is not allowed here. Use [ \'factory\' => $callable ] instead.'
				);
			}
			return [ 'factory' => $spec ];
		}
		if ( is_string( $spec ) && class_exists( $spec ) ) {
			if ( empty( $options['allowClassName'] ) ) {
				throw new InvalidArgumentException(
					'Passing a raw class name is not allowed here. Use [ \'class\' => $classname ] instead.'
				);
			}
			return [ 'class' => $spec ];
		}

		if ( !is_array( $spec ) ) {
			throw new InvalidArgumentException( 'Provided specification is not an array.' );
		}

		return $spec;
	}

	/**
	 * Iterate a list and call any closures it contains.
	 *
	 * @param array $list List of things
	 *
	 * @return array List with any Closures replaced with their output
	 */
	protected static function expandClosures( array $list ): array {
		return array_map( static function ( $value ) {
			if ( $value instanceof Closure ) {
				// If $value is a Closure, call it.
				return $value();
			}

			return $value;
		}, $list );
	}

}
