<?php

// phpcs:disable MediaWiki.Commenting.FunctionComment.ObjectTypeHintReturn

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
 * @ingroup Cache Parser
 */

namespace Wikimedia\Tests;

use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Utilities for testing forward and backward compatibility serialized objects.
 */
class SerializationTestUtils {

	/** @var string */
	private $serializedDataPath;

	/** @var string */
	private $ext;

	/** @var callable */
	private $serializer;

	/** @var callable */
	private $deserializer;

	/** @var array */
	private $testInstances;

	/** @var LoggerInterface */
	private $logger;

	/**
	 * @param string $serializedDataPath absolute path to the directory with serialized objects.
	 * @param array $testInstances map of test names to test objects.
	 * @param string $ext file extension for serialized instance files
	 * @param callable $serializer
	 * @param callable $deserializer
	 */
	public function __construct(
		string $serializedDataPath,
		array $testInstances,
		string $ext,
		callable $serializer,
		callable $deserializer
	) {
		if ( !is_dir( $serializedDataPath ) ) {
			throw new InvalidArgumentException( "{$serializedDataPath} does not exist" );
		}
		$this->serializedDataPath = $serializedDataPath;
		$this->ext = $ext;
		$this->serializer = $serializer;
		$this->deserializer = $deserializer;
		$this->testInstances = $testInstances;
		$this->logger = new NullLogger();
	}

	/**
	 * @param LoggerInterface $logger
	 */
	public function setLogger( LoggerInterface $logger ): void {
		$this->logger = $logger;
	}

	/**
	 * Get the files with stored serialized instances of $class with extension $ext.
	 * @param string $class
	 * @param string $ext
	 * @return array
	 */
	private function getMatchingFiles( string $class, string $ext ): array {
		$glob = $this->serializedDataPath . "/*-{$class}-*.$ext";
		$matches = glob( $glob );

		if ( !$matches ) {
			$this->log( 'No matches found for ' . $glob );
			return [];
		}

		// File names should look something like this: "1.35-CacheTime-empty.serialized".
		$pattern = '!/([^/-]+)-' . preg_quote( $class, '!' ) . '-([^/-]+)\.[^/]+$!';

		$files = [];
		foreach ( $matches as $path ) {
			if ( !preg_match( $pattern, $path, $m ) ) {
				$this->logger->warning( 'Skipping file with malformed name: ' . $path );
				continue;
			}
			$version = $m[1];
			$testCaseName = $m[2];

			$files[] = $this->getStoredSerializedInstance( $class, $testCaseName, $version );
		}

		return $files;
	}

	/**
	 * Get an array of test instances for $class keyed with test case name.
	 * @return object[]
	 */
	public function getTestInstances(): array {
		return $this->testInstances;
	}

	/**
	 * Get an test instance of $class for test case named $testCaseName.
	 * @param string $testCaseName
	 * @return object
	 */
	public function getTestInstanceForTestCase( string $testCaseName ): object {
		$instances = $this->getTestInstances();
		if ( !array_key_exists( $testCaseName, $instances ) ) {
			throw new InvalidArgumentException(
				"Test instance not found for test case {$testCaseName}"
			);
		}
		return $instances[$testCaseName];
	}

	/**
	 * Get an array of instances of $class deserialized from
	 * files for different code versions, keyed by the test case name.
	 * @param string $class
	 * @return array
	 */
	private function getDeserializedInstances( string $class ): array {
		return array_map( function ( $fileInfo ) {
			$fileInfo->object = call_user_func( $this->deserializer, $fileInfo->data );
			return $fileInfo;
		}, $this->getMatchingFiles( $class, $this->ext ) );
	}

	/**
	 * Get an array of serialization fixtures for $class stored in files
	 * for different MW versions, for test case name $testCaseName.
	 * @param string $class
	 * @param string $testCaseName
	 * @return array
	 */
	public function getFixturesForTestCase( string $class, string $testCaseName ): array {
		return array_filter(
			$this->getMatchingFiles( $class, $this->ext ),
			static function ( $fileInfo ) use ( $testCaseName ) {
				return $fileInfo->testCaseName === $testCaseName;
			} );
	}

	/**
	 * Get an array of instances of $class deserialized from stored files
	 * for different MW versions, for test case named $testCaseName.
	 * @param string $class
	 * @param string $testCaseName
	 * @return array
	 */
	public function getDeserializedInstancesForTestCase( string $class, string $testCaseName ): array {
		return array_filter( $this->getDeserializedInstances( $class ),
			static function ( $fileInfo ) use ( $testCaseName ) {
				return $fileInfo->testCaseName === $testCaseName;
			}
		);
	}

	/**
	 * Get test objects of $class, serialized using $serializer,
	 * keyed by test case name.
	 * @return array
	 */
	public function getSerializedInstances(): array {
		$instances = $this->getTestInstances();
		return array_map( function ( $object )  {
			return call_user_func( $this->serializer, $object );
		}, $instances );
	}

	/**
	 * Get the file info about a stored serialized instance of $class,
	 * for test case $testCaseName with extension $ext for $version of MW.
	 * @param string $class
	 * @param string $testCaseName
	 * @param string|null $version
	 * @return \stdClass
	 */
	public function getStoredSerializedInstance(
		string $class,
		string $testCaseName,
		string $version = null
	) {
		if ( $version ) {
			$path = "$this->serializedDataPath/$version-$class-$testCaseName.$this->ext";
		} else {
			// Find the latest version we have saved.
			$savedFiles = glob( "$this->serializedDataPath/?.??-$class-$testCaseName.$this->ext" );
			sort( $savedFiles );
			$path = $savedFiles[count( $savedFiles ) - 1];
		}
		$curPath = "$this->serializedDataPath/{$this->getCurrentVersion()}-$class-$testCaseName.$this->ext";
		return (object)[
			'version' => $version,
			'class' => $class,
			'testCaseName' => $testCaseName,
			'ext' => $this->ext,
			'path' => $path,
			'currentVersionPath' => $curPath,
			'data' => is_file( $path ) ? file_get_contents( $path ) : null,
		];
	}

	/**
	 * Returns the current version of MediaWiki in `1.xx` format.
	 * @return string
	 */
	private function getCurrentVersion(): string {
		return preg_replace( '/^(\d\.\d+).*$/', '$1', MW_VERSION );
	}

	private function log( $msg ) {
		$this->logger->info( $msg );
	}
}
