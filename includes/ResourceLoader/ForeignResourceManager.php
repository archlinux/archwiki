<?php
/**
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

namespace MediaWiki\ResourceLoader;

use Composer\Spdx\SpdxLicenses;
use LogicException;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use MediaWiki\Message\Message;
use PharData;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Yaml\Yaml;
use Wikimedia\UUID\GlobalIdGenerator;

/**
 * Manage foreign resources registered with ResourceLoader.
 *
 * @since 1.32
 * @ingroup ResourceLoader
 * @see https://www.mediawiki.org/wiki/Foreign_resources
 */
class ForeignResourceManager {
	/** @var string */
	private $defaultAlgo = 'sha384';

	/** @var bool */
	private $hasErrors = false;

	/** @var string */
	private $registryFile;

	/** @var string */
	private $libDir;

	/** @var string */
	private $tmpParentDir;

	/** @var string */
	private $cacheDir;

	/**
	 * @var callable|Closure
	 * @phan-var callable(string):void
	 */
	private $infoPrinter;

	/**
	 * @var callable|Closure
	 * @phan-var callable(string):void
	 */
	private $errorPrinter;
	/**
	 * @var callable|Closure
	 * @phan-var callable(string):void
	 */
	private $verbosePrinter;

	/** @var string */
	private $action;

	/** @var array[] */
	private $registry;

	private GlobalIdGenerator $globalIdGenerator;

	/**
	 * @param string $registryFile Path to YAML file
	 * @param string $libDir Path to a modules directory
	 * @param callable|null $infoPrinter Callback for printing info about the run.
	 * @param callable|null $errorPrinter Callback for printing errors from the run.
	 * @param callable|null $verbosePrinter Callback for printing extra verbose
	 *  progress information from the run.
	 */
	public function __construct(
		$registryFile,
		$libDir,
		?callable $infoPrinter = null,
		?callable $errorPrinter = null,
		?callable $verbosePrinter = null
	) {
		$this->globalIdGenerator = MediaWikiServices::getInstance()->getGlobalIdGenerator();
		$this->registryFile = $registryFile;
		$this->libDir = $libDir;
		$this->infoPrinter = $infoPrinter ?? static function ( $_ ) {
		};
		$this->errorPrinter = $errorPrinter ?? $this->infoPrinter;
		$this->verbosePrinter = $verbosePrinter ?? static function ( $_ ) {
		};

		// Support XDG_CACHE_HOME to speed up CI by avoiding repeated downloads.
		$cacheHome = getenv( 'XDG_CACHE_HOME' );
		if ( $cacheHome !== false ) {
			$this->cacheDir = realpath( $cacheHome ) . '/mw-foreign';
		} else {
			$conf = MediaWikiServices::getInstance()->getMainConfig();
			$cacheConf = $conf->get( MainConfigNames::CacheDirectory );
			if ( $cacheConf !== false ) {
				$this->cacheDir = "$cacheConf/ForeignResourceManager";
			} else {
				$this->cacheDir = "{$this->libDir}/.foreign/cache";
			}
		}
	}

	/**
	 * @param string $action
	 * @param string $module
	 * @return bool
	 * @throws LogicException
	 */
	public function run( $action, $module ) {
		$actions = [ 'update', 'verify', 'make-sri', 'make-cdx' ];
		if ( !in_array( $action, $actions ) ) {
			$this->error( "Invalid action.\n\nMust be one of " . implode( ', ', $actions ) . '.' );
			return false;
		}
		$this->action = $action;
		$this->setupTempDir( $action );

		$this->registry = Yaml::parseFile( $this->registryFile );
		if ( $module === 'all' ) {
			$modules = $this->registry;
		} elseif ( isset( $this->registry[$module] ) ) {
			$modules = [ $module => $this->registry[$module] ];
		} else {
			$this->error( "Unknown module name.\n\nMust be one of:\n" .
				wordwrap( implode( ', ', array_keys( $this->registry ) ), 80 ) .
				'.'
			);
			return false;
		}

		if ( $this->action === 'make-cdx' ) {
			$cdxFile = $this->getCdxFileLocation();
			$cdxJson = json_encode(
				$this->generateCdxForModules( $modules ),
				JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
			);
			file_put_contents( $cdxFile, $cdxJson );
			$this->output( "Created CycloneDX file at $cdxFile\n" );
			return true;
		}

		foreach ( $modules as $moduleName => $info ) {
			$this->verbose( "\n### {$moduleName}\n\n" );

			if ( $this->action === 'update' ) {
				$this->output( "... updating '{$moduleName}'\n" );
			} elseif ( $this->action === 'verify' ) {
				$this->output( "... verifying '{$moduleName}'\n" );
			} else {
				$this->output( "... checking '{$moduleName}'\n" );
			}

			// Do checks on yaml content (such as license existence, validity and type keys)
			// before doing any potentially destructive actions (potentially deleting directories,
			// depending on action.

			if ( !isset( $info['type'] ) ) {
				throw new LogicException( "Module '$moduleName' must have a 'type' key." );
			}

			$this->validateLicense( $moduleName, $info );

			if ( $info['type'] === 'doc-only' ) {
				$this->output( "... {$moduleName} is documentation-only, skipping integrity checks.\n" );
				continue;
			}

			$destDir = "{$this->libDir}/$moduleName";

			if ( $this->action === 'update' ) {
				$this->verbose( "... emptying directory for $moduleName\n" );
				wfRecursiveRemoveDir( $destDir );
			}

			$this->verbose( "... preparing {$this->tmpParentDir}\n" );
			wfRecursiveRemoveDir( $this->tmpParentDir );
			if ( !wfMkdirParents( $this->tmpParentDir ) ) {
				throw new LogicException( "Unable to create {$this->tmpParentDir}" );
			}

			switch ( $info['type'] ) {
				case 'tar':
				case 'zip':
					$this->handleTypeTar( $moduleName, $destDir, $info, $info['type'] );
					break;
				case 'file':
					$this->handleTypeFile( $moduleName, $destDir, $info );
					break;
				case 'multi-file':
					$this->handleTypeMultiFile( $moduleName, $destDir, $info );
					break;
				default:
					throw new LogicException( "Unknown type '{$info['type']}' for '$moduleName'" );
			}
		}

		$this->cleanUp();
		if ( $this->hasErrors ) {
			// The "verify" action should check all modules and files and fail after, not during.
			// We don't throw on the first issue so that developers enjoy access to all actionable
			// information at once (given we can't have cascading errors).
			// The "verify" action prints errors along the way and simply exits here.
			return false;
		}

		return true;
	}

	/**
	 * Returns a JSON string describing the foreign resources in a CycloneDX format.
	 */
	public function generateCdx(): string {
		$this->registry = Yaml::parseFile( $this->registryFile );
		return json_encode(
			$this->generateCdxForModules( $this->registry ),
			JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
		);
	}

	/**
	 * Get the path to the CycloneDX file that describes the foreign resources.
	 */
	public function getCdxFileLocation(): string {
		return "$this->libDir/foreign-resources.cdx.json";
	}

	/**
	 * Choose the temp parent directory
	 *
	 * @param string $action
	 */
	private function setupTempDir( $action ) {
		if ( $action === 'verify' ) {
			$this->tmpParentDir = wfTempDir() . '/ForeignResourceManager';
		} else {
			// Use a temporary directory under the destination directory instead
			// of wfTempDir() because PHP's rename() does not work across file
			// systems, and the user's /tmp and $IP may be on different filesystems.
			$this->tmpParentDir = "{$this->libDir}/.foreign/tmp";
		}
	}

	/**
	 * @param string $src
	 * @param string $integrity
	 * @param string $moduleName
	 * @return string
	 */
	private function cacheKey( $src, $integrity, $moduleName ) {
		$key = $moduleName
			. '_' . hash( 'fnv132', $integrity )
			. '_' . hash( 'fnv132', $src )
			// Append readable filename to aid cache inspection and debugging
			. '_' . basename( $src );
		$key = preg_replace( '/[.\/+?=_-]+/', '_', $key );
		return rtrim( $key, '_' );
	}

	/**
	 * @param string $key
	 * @return string|false
	 */
	private function cacheGet( $key ) {
		// phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
		return @file_get_contents( "{$this->cacheDir}/$key.data" );
	}

	/**
	 * @param string $key
	 * @param mixed $data
	 */
	private function cacheSet( $key, $data ) {
		// phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
		@mkdir( $this->cacheDir, 0777, true );
		file_put_contents( "{$this->cacheDir}/$key.data", $data, LOCK_EX );
	}

	/**
	 * @param string $src
	 * @param string|null $integrity
	 * @param string $moduleName
	 * @return string
	 */
	private function fetch( string $src, $integrity, string $moduleName ) {
		if ( $integrity !== null ) {
			$key = $this->cacheKey( $src, $integrity, $moduleName );
			$data = $this->cacheGet( $key );
			if ( $data ) {
				return $data;
			}
		}

		$services = MediaWikiServices::getInstance();
		$req = $services->getHttpRequestFactory()
			->create( $src, [ 'method' => 'GET', 'followRedirects' => false ], __METHOD__ );
		$reqStatusValue = $req->execute();
		if ( !$reqStatusValue->isOK() ) {
			$message = "Failed to download resource at {$src}";
			$reqError = $reqStatusValue->getMessages( 'error' )[0] ?? null;
			if ( $reqError !== null ) {
				$message .= ': ' . Message::newFromSpecifier( $reqError )->inLanguage( 'en' )->plain();
			}
			throw new ForeignResourceNetworkException( $message );
		}
		if ( $req->getStatus() !== 200 ) {
			throw new ForeignResourceNetworkException( "Unexpected HTTP {$req->getStatus()} response from {$src}" );
		}
		$data = $req->getContent();
		$algo = $integrity === null ? $this->defaultAlgo : explode( '-', $integrity )[0];
		$actualIntegrity = $algo . '-' . base64_encode( hash( $algo, $data, true ) );
		if ( $integrity === $actualIntegrity ) {
			$this->verbose( "... passed integrity check for {$src}\n" );
			$key = $this->cacheKey( $src, $actualIntegrity, $moduleName );
			$this->cacheSet( $key, $data );
		} elseif ( $this->action === 'make-sri' ) {
			$this->output( "Integrity for {$src}\n\tintegrity: {$actualIntegrity}\n" );
		} else {
			$expectedIntegrity = $integrity ?? 'null';
			throw new ForeignResourceNetworkException( "Integrity check failed for {$src}\n" .
				"\tExpected: {$expectedIntegrity}\n" .
				"\tActual: {$actualIntegrity}"
			);
		}
		return $data;
	}

	/**
	 * @param string $moduleName
	 * @param string $destDir
	 * @param array $info
	 */
	private function handleTypeFile( $moduleName, $destDir, array $info ) {
		if ( !isset( $info['src'] ) ) {
			throw new LogicException( "Module '$moduleName' must have a 'src' key." );
		}
		$data = $this->fetch( $info['src'], $info['integrity'] ?? null, $moduleName );
		$dest = $info['dest'] ?? basename( $info['src'] );
		$path = "$destDir/$dest";
		if ( $this->action === 'verify' && sha1_file( $path ) !== sha1( $data ) ) {
			$this->error( "File for '$moduleName' is different.\n" );
		}
		if ( $this->action === 'update' ) {
			wfMkdirParents( $destDir );
			file_put_contents( "$destDir/$dest", $data );
		}
	}

	/**
	 * @param string $moduleName
	 * @param string $destDir
	 * @param array $info
	 */
	private function handleTypeMultiFile( $moduleName, $destDir, array $info ) {
		if ( !isset( $info['files'] ) ) {
			throw new LogicException( "Module '$moduleName' must have a 'files' key." );
		}
		foreach ( $info['files'] as $dest => $file ) {
			if ( !isset( $file['src'] ) ) {
				throw new LogicException( "Module '$moduleName' file '$dest' must have a 'src' key." );
			}
			$data = $this->fetch( $file['src'], $file['integrity'] ?? null, $moduleName );
			$path = "$destDir/$dest";
			if ( $this->action === 'verify' && sha1_file( $path ) !== sha1( $data ) ) {
				$this->error( "File '$dest' for '$moduleName' is different.\n" );
			} elseif ( $this->action === 'update' ) {
				wfMkdirParents( $destDir );
				file_put_contents( "$destDir/$dest", $data );
			}
		}
	}

	/**
	 * @param string $moduleName
	 * @param string $destDir
	 * @param array $info
	 * @param string $fileType
	 */
	private function handleTypeTar( $moduleName, $destDir, array $info, string $fileType ) {
		$info += [ 'src' => null, 'integrity' => null, 'dest' => null ];
		if ( $info['src'] === null ) {
			throw new LogicException( "Module '$moduleName' must have a 'src' key." );
		}
		// Download the resource to a temporary file and open it
		$data = $this->fetch( $info['src'], $info['integrity'], $moduleName );
		$tmpFile = "{$this->tmpParentDir}/$moduleName." . $fileType;
		$this->verbose( "... writing '$moduleName' src to $tmpFile\n" );
		file_put_contents( $tmpFile, $data );
		$p = new PharData( $tmpFile );
		$tmpDir = "{$this->tmpParentDir}/$moduleName";
		$p->extractTo( $tmpDir );
		unset( $data, $p );

		if ( $info['dest'] === null ) {
			// Default: Replace the entire directory
			$toCopy = [ $tmpDir => $destDir ];
		} else {
			// Expand and normalise the 'dest' entries
			$toCopy = [];
			foreach ( $info['dest'] as $fromSubPath => $toSubPath ) {
				// Use glob() to expand wildcards and check existence
				$fromPaths = glob( "{$tmpDir}/{$fromSubPath}", GLOB_BRACE );
				if ( !$fromPaths ) {
					throw new LogicException( "Path '$fromSubPath' of '$moduleName' not found." );
				}
				foreach ( $fromPaths as $fromPath ) {
					$toCopy[$fromPath] = $toSubPath === null
						? "$destDir/" . basename( $fromPath )
						: "$destDir/$toSubPath/" . basename( $fromPath );
				}
			}
		}
		foreach ( $toCopy as $from => $to ) {
			if ( $this->action === 'verify' ) {
				$this->verbose( "... verifying $to\n" );
				if ( is_dir( $from ) ) {
					$rii = new RecursiveIteratorIterator( new RecursiveDirectoryIterator(
						$from,
						RecursiveDirectoryIterator::SKIP_DOTS
					) );
					/** @var SplFileInfo $file */
					foreach ( $rii as $file ) {
						$remote = $file->getPathname();
						$local = strtr( $remote, [ $from => $to ] );
						if ( sha1_file( $remote ) !== sha1_file( $local ) ) {
							$this->error( "File '$local' is different.\n" );
						}
					}
				} elseif ( sha1_file( $from ) !== sha1_file( $to ) ) {
					$this->error( "File '$to' is different.\n" );
				}
			} elseif ( $this->action === 'update' ) {
				$this->verbose( "... moving $from to $to\n" );
				wfMkdirParents( dirname( $to ) );
				if ( !rename( $from, $to ) ) {
					throw new LogicException( "Could not move $from to $to." );
				}
			}
		}
	}

	/**
	 * @param string $text
	 */
	private function verbose( $text ) {
		( $this->verbosePrinter )( $text );
	}

	/**
	 * @param string $text
	 */
	private function output( $text ) {
		( $this->infoPrinter )( $text );
	}

	/**
	 * @param string $text
	 */
	private function error( $text ) {
		$this->hasErrors = true;
		( $this->errorPrinter )( $text );
	}

	private function cleanUp() {
		wfRecursiveRemoveDir( $this->tmpParentDir );

		// Prune the cache of files we don't recognise.
		$knownKeys = [];
		foreach ( $this->registry as $module => $info ) {
			if ( $info['type'] === 'file' || $info['type'] === 'tar' ) {
				$knownKeys[] = $this->cacheKey( $info['src'], $info['integrity'], $module );
			} elseif ( $info['type'] === 'multi-file' ) {
				foreach ( $info['files'] as $file ) {
					$knownKeys[] = $this->cacheKey( $file['src'], $file['integrity'], $module );
				}
			}
		}
		foreach ( glob( "{$this->cacheDir}/*" ) as $cacheFile ) {
			if ( !in_array( basename( $cacheFile, '.data' ), $knownKeys ) ) {
				unlink( $cacheFile );
			}
		}
	}

	/**
	 * @param string $moduleName
	 * @param array $info
	 */
	private function validateLicense( $moduleName, $info ) {
		if ( !isset( $info['license'] ) || !is_string( $info['license'] ) ) {
			throw new LogicException(
				"Module '$moduleName' needs a valid SPDX license; no license is currently present"
			);
		}
		$licenses = new SpdxLicenses();
		if ( !$licenses->validate( $info['license'] ) ) {
			$this->error(
				"Module '$moduleName' has an invalid SPDX license identifier '{$info['license']}', "
				. "see <https://spdx.org/licenses/>.\n"
			);
		}
	}

	private function generateCdxForModules( array $modules ): array {
		$cdx = [
			'$schema' => 'http://cyclonedx.org/schema/bom-1.6.schema.json',
			'bomFormat' => 'CycloneDX',
			'specVersion' => '1.6',
			'serialNumber' => 'urn:uuid:' . $this->globalIdGenerator->newUUIDv4(),
			'version' => 1,
			'components' => [],
		];
		foreach ( $modules as $moduleName => $module ) {
			$moduleCdx = [
				'type' => 'library',
				'name' => $moduleName,
				'version' => $module['version'],
			];
			if ( preg_match( '/ (AND|OR|WITH) /', $module['license'] ) ) {
				$moduleCdx['licenses'][] = [ 'expression' => $module['license'] ];
			} else {
				$moduleCdx['licenses'][] = [ 'license' => [ 'id' => $module['license'] ] ];
			}
			if ( $module['purl'] ?? false ) {
				$moduleCdx['purl'] = $module['purl'];
			}
			if ( $module['version'] ?? false ) {
				$moduleCdx['version'] = $module['version'];
			}
			if ( $module['authors'] ?? false ) {
				$moduleCdx['authors'] = array_map(
					fn ( $author ) => [ 'name' => $author ],
					preg_split( '/,( and)? /', $module['authors'] )
				);
			}
			if ( $module['homepage'] ?? false ) {
				$moduleCdx['externalReferences'] = [ [ 'url' => $module['homepage'], 'type' => 'website' ] ];
			}
			$cdx['components'][] = $moduleCdx;
		}
		return $cdx;
	}
}

/** @deprecated class alias since 1.40 */
class_alias( ForeignResourceManager::class, 'ForeignResourceManager' );
