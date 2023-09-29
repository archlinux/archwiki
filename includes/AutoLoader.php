<?php
/**
 * This defines autoloading handler for whole MediaWiki framework
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

// NO_AUTOLOAD -- file scope code, can't load self

/**
 * Locations of core classes
 * Extension classes are specified with $wgAutoloadClasses
 */
require_once __DIR__ . '/../autoload.php';

class AutoLoader {

	/**
	 * A mapping of namespace => file path for MediaWiki core.
	 * The namespaces should follow the PSR-4 standard for autoloading
	 *
	 * @see <https://www.php-fig.org/psr/psr-4/>
	 * @internal Only public for usage in AutoloadGenerator
	 * @phpcs-require-sorted-array
	 */
	public const CORE_NAMESPACES = [
		'MediaWiki\\' => __DIR__ . '/',
		'MediaWiki\\Actions\\' => __DIR__ . '/actions/',
		'MediaWiki\\Api\\' => __DIR__ . '/api/',
		'MediaWiki\\Auth\\' => __DIR__ . '/auth/',
		'MediaWiki\\Block\\' => __DIR__ . '/block/',
		'MediaWiki\\Cache\\' => __DIR__ . '/cache/',
		'MediaWiki\\ChangeTags\\' => __DIR__ . '/changetags/',
		'MediaWiki\\Config\\' => __DIR__ . '/config/',
		'MediaWiki\\Content\\' => __DIR__ . '/content/',
		'MediaWiki\\DB\\' => __DIR__ . '/db/',
		'MediaWiki\\Deferred\\LinksUpdate\\' => __DIR__ . '/deferred/LinksUpdate/',
		'MediaWiki\\Diff\\' => __DIR__ . '/diff/',
		'MediaWiki\\EditPage\\' => __DIR__ . '/editpage/',
		'MediaWiki\\Edit\\' => __DIR__ . '/edit/',
		'MediaWiki\\FileBackend\\LockManager\\' => __DIR__ . '/filebackend/lockmanager/',
		'MediaWiki\\Http\\' => __DIR__ . '/http/',
		'MediaWiki\\Installer\\' => __DIR__ . '/installer/',
		'MediaWiki\\Interwiki\\' => __DIR__ . '/interwiki/',
		'MediaWiki\\JobQueue\\' => __DIR__ . '/jobqueue/',
		'MediaWiki\\Json\\' => __DIR__ . '/json/',
		'MediaWiki\\Languages\\Data\\' => __DIR__ . '/languages/data/',
		'MediaWiki\\Linker\\' => __DIR__ . '/linker/',
		'MediaWiki\\Logger\\' => __DIR__ . '/debug/logger/',
		'MediaWiki\\Logger\\Monolog\\' => __DIR__ . '/debug/logger/monolog/',
		'MediaWiki\\Mail\\' => __DIR__ . '/mail/',
		'MediaWiki\\Page\\' => __DIR__ . '/page/',
		'MediaWiki\\Parser\\' => __DIR__ . '/parser/',
		'MediaWiki\\PoolCounter\\' => __DIR__ . '/poolcounter/',
		'MediaWiki\\Preferences\\' => __DIR__ . '/preferences/',
		'MediaWiki\\Search\\' => __DIR__ . '/search/',
		'MediaWiki\\Search\\SearchWidgets\\' => __DIR__ . '/search/searchwidgets/',
		'MediaWiki\\Session\\' => __DIR__ . '/session/',
		'MediaWiki\\Shell\\' => __DIR__ . '/shell/',
		'MediaWiki\\Site\\' => __DIR__ . '/site/',
		'MediaWiki\\Sparql\\' => __DIR__ . '/sparql/',
		'MediaWiki\\SpecialPage\\' => __DIR__ . '/specialpage/',
		'MediaWiki\\Specials\\Contribute\\' => __DIR__ . '/specials/Contribute',
		'MediaWiki\\Tidy\\' => __DIR__ . '/tidy/',
		'MediaWiki\\User\\' => __DIR__ . '/user/',
		'MediaWiki\\Utils\\' => __DIR__ . '/utils/',
		'MediaWiki\\Widget\\' => __DIR__ . '/widget/',
		'Wikimedia\\' => __DIR__ . '/libs/',
		'Wikimedia\\Http\\' => __DIR__ . '/libs/http/',
		'Wikimedia\\Rdbms\\Platform\\' => __DIR__ . '/libs/rdbms/platform/',
		'Wikimedia\\UUID\\' => __DIR__ . '/libs/uuid/',
	];

	/**
	 * @var string[] Namespace (ends with \) => Path (ends with /)
	 */
	private static $psr4Namespaces = self::CORE_NAMESPACES;

	/**
	 * @var string[] Class => File
	 */
	private static $classFiles = [];

	/**
	 * Register a directory to load the classes of a given namespace from,
	 * per PSR4.
	 *
	 * @see <https://www.php-fig.org/psr/psr-4/>
	 * @since 1.39
	 * @param string[] $dirs a map of namespace (ends with \) to path (ends with /)
	 */
	public static function registerNamespaces( array $dirs ): void {
		self::$psr4Namespaces += $dirs;
	}

	/**
	 * Register a file to load the given class from.
	 * @since 1.39
	 *
	 * @param string[] $files a map of qualified class names to file names
	 */
	public static function registerClasses( array $files ): void {
		self::$classFiles += $files;
	}

	/**
	 * Load a file that declares classes, functions, or constants.
	 * The file will be loaded immediately using require_once in function scope.
	 *
	 * @note The file to be loaded MUST NOT set global variables or otherwise
	 * affect the global state. It MAY however use conditionals to determine
	 * what to declare and how, e.g. to provide polyfills.
	 *
	 * @note The file to be loaded MUST NOT assume that MediaWiki has been
	 * initialized. In particular, it MUST NOT access configuration variables
	 * or MediaWikiServices.
	 *
	 * @since 1.39
	 *
	 * @param string $file the path of the file to load.
	 */
	public static function loadFile( string $file ): void {
		require_once $file;
	}

	/**
	 * Batch version of loadFile()
	 *
	 * @see loadFile()
	 *
	 * @since 1.39
	 *
	 * @param string[] $files the paths of the files to load.
	 */
	public static function loadFiles( array $files ): void {
		foreach ( $files as $f ) {
			self::loadFile( $f );
		}
	}

	/**
	 * Find the file containing the given class.
	 *
	 * @param string $className Name of class we're looking for.
	 * @return string|null The path containing the class, not null if not found
	 */
	public static function find( $className ): ?string {
		global $wgAutoloadLocalClasses, $wgAutoloadClasses;

		// NOTE: $wgAutoloadClasses is supported for compatibility with old-style extension
		//       registration files.

		$filename = $wgAutoloadLocalClasses[$className] ??
			self::$classFiles[$className] ??
			$wgAutoloadClasses[$className] ??
			false;

		if ( !$filename && strpos( $className, '\\' ) !== false ) {
			// This class is namespaced, so look in the namespace map
			$prefix = $className;
			while ( ( $pos = strrpos( $prefix, '\\' ) ) !== false ) {
				// Check to see if this namespace prefix is in the map
				$prefix = substr( $className, 0, $pos + 1 );
				if ( isset( self::$psr4Namespaces[$prefix] ) ) {
					$relativeClass = substr( $className, $pos + 1 );
					// Build the expected filename, and see if it exists
					$file = self::$psr4Namespaces[$prefix] .
						'/' .
						strtr( $relativeClass, '\\', '/' ) .
						'.php';
					if ( is_file( $file ) ) {
						$filename = $file;
						break;
					}
				}

				// Remove trailing separator for next iteration
				$prefix = rtrim( $prefix, '\\' );
			}
		}

		if ( !$filename ) {
			// Class not found; let the next autoloader try to find it
			return null;
		}

		// Make an absolute path, this improves performance by avoiding some stat calls
		// Optimisation: use string offset access instead of substr
		if ( $filename[0] !== '/' && $filename[1] !== ':' ) {
			$filename = __DIR__ . '/../' . $filename;
		}

		return $filename;
	}

	/**
	 * autoload - take a class name and attempt to load it
	 *
	 * @param string $className Name of class we're looking for.
	 */
	public static function autoload( $className ) {
		$filename = self::find( $className );

		if ( $filename !== null ) {
			require $filename;
		}
	}

	///// Methods used during testing //////////////////////////////////////////////
	private static function assertTesting( $method ) {
		if ( !defined( 'MW_PHPUNIT_TEST' ) ) {
			throw new LogicException( "$method is not supported outside phpunit tests!" );
		}
	}

	/**
	 * Returns a map of class names to file paths for testing.
	 * @note Will throw if called outside of phpunit tests!
	 * @return string[]
	 */
	public static function getClassFiles(): array {
		global $wgAutoloadLocalClasses, $wgAutoloadClasses;

		self::assertTesting( __METHOD__ );

		// NOTE: ensure the order of preference is the same as used by find().
		return array_merge(
			$wgAutoloadClasses,
			self::$classFiles,
			$wgAutoloadLocalClasses
		);
	}

	/**
	 * Returns a map of namespace names to directories, per PSR4.
	 * @note Will throw if called outside of phpunit tests!
	 * @return string[]
	 */
	public static function getNamespaceDirectories(): array {
		self::assertTesting( __METHOD__ );
		return self::$psr4Namespaces;
	}

	/**
	 * Returns an array representing the internal state of Autoloader,
	 * so it can be remembered and later restored during testing.
	 * @internal
	 * @note Will throw if called outside of phpunit tests!
	 * @return array
	 */
	public static function getState(): array {
		self::assertTesting( __METHOD__ );
		return [
			'classFiles' => self::$classFiles,
			'psr4Namespaces' => self::$psr4Namespaces,
		];
	}

	/**
	 * Returns an array representing the internal state of Autoloader,
	 * so it can be remembered and later restored during testing.
	 * @internal
	 * @note Will throw if called outside of phpunit tests!
	 *
	 * @param array $state A state array returned by getState().
	 */
	public static function restoreState( $state ): void {
		self::assertTesting( __METHOD__ );

		self::$classFiles = $state['classFiles'];
		self::$psr4Namespaces = $state['psr4Namespaces'];
	}

}

spl_autoload_register( [ 'AutoLoader', 'autoload' ] );

// Load composer's autoloader if present
if ( is_readable( __DIR__ . '/../vendor/autoload.php' ) ) {
	require_once __DIR__ . '/../vendor/autoload.php';
} elseif ( file_exists( __DIR__ . '/../vendor/autoload.php' ) ) {
	die( __DIR__ . '/../vendor/autoload.php exists but is not readable' );
}
