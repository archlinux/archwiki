<?php

/**
 * Autoloader
 */
class Less_Autoloader {

	protected static bool $registered = false;

	/**
	 * Register the autoloader in the SPL autoloader
	 *
	 * @throws Exception If there was an error in registration
	 */
	public static function register(): void {
		if ( self::$registered ) {
			return;
		}

		if ( !spl_autoload_register( [ __CLASS__, 'loadClass' ] ) ) {
			throw new Exception( 'Unable to register Less_Autoloader::loadClass as an autoloading method.' );
		}

		self::$registered = true;
	}

	/**
	 * Unregister the autoloader
	 */
	public static function unregister(): void {
		spl_autoload_unregister( [ __CLASS__, 'loadClass' ] );
		self::$registered = false;
	}

	/**
	 * Load the class
	 *
	 * @param string $className The class to load
	 */
	public static function loadClass( string $className ): void {
		// handle only package classes
		if ( !str_starts_with( $className, 'Less_' ) ) {
			return;
		}

		$className = substr( $className, 5 );
		$fileName = __DIR__ . DIRECTORY_SEPARATOR . str_replace( '_', DIRECTORY_SEPARATOR, $className ) . '.php';

		require $fileName;
	}

}
