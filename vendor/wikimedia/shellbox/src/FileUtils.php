<?php

namespace Shellbox;

use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;

/**
 * Throwing wrappers for file functions
 *
 * @internal
 */
class FileUtils {
	private const COPY_BUFFER_SIZE = 65536;

	/**
	 * Copy file
	 *
	 * @param string $source
	 * @param string $dest
	 * @throws ShellboxError
	 */
	public static function copy( $source, $dest ) {
		if ( !copy( $source, $dest ) ) {
			throw new ShellboxError( "Error while copying " .
				basename( $source ) . ' to ' . basename( $dest ) );
		}
	}

	/**
	 * Get contents
	 *
	 * @param string $path
	 * @return string
	 * @throws ShellboxError
	 */
	public static function getContents( $path ) {
		$contents = file_get_contents( $path );
		if ( $contents === false ) {
			throw new ShellboxError( "Unable to read " . basename( $path ) );
		}
		return $contents;
	}

	/**
	 * Put contents
	 *
	 * @param string $path
	 * @param string $contents
	 * @throws ShellboxError
	 */
	public static function putContents( $path, $contents ) {
		if ( !file_put_contents( $path, $contents ) ) {
			throw new ShellboxError( "Unable to write " . basename( $path ) );
		}
	}

	/**
	 * Open a file in read mode
	 *
	 * @param string $path
	 * @return resource
	 * @throws ShellboxError
	 */
	public static function openInputFile( $path ) {
		$file = fopen( $path, 'r' );
		if ( !$file ) {
			throw new ShellboxError( "Error opening input file " . basename( $path ) );
		}
		return $file;
	}

	/**
	 * Open a file in write mode
	 *
	 * @param string $path
	 * @return resource
	 * @throws ShellboxError
	 */
	public static function openOutputFile( $path ) {
		$file = fopen( $path, 'w' );
		if ( !$file ) {
			throw new ShellboxError( "Error opening output file " . basename( $path ) );
		}
		return $file;
	}

	/**
	 * Open a file in read mode and convert it to a StreamInterface
	 *
	 * @param string $path
	 * @return StreamInterface
	 * @throws ShellboxError
	 */
	public static function openInputFileStream( $path ) {
		return Utils::streamFor( self::openInputFile( $path ) );
	}

	/**
	 * Open a file in write mode and convert it to a StreamInterface
	 *
	 * @param string $path
	 * @return StreamInterface
	 * @throws ShellboxError
	 */
	public static function openOutputFileStream( $path ) {
		return Utils::streamFor( self::openOutputFile( $path ) );
	}

	/**
	 * Copy a stream to a file
	 *
	 * @param StreamInterface $stream
	 * @param string $path
	 * @throws ShellboxError
	 */
	public static function copyStreamToFile( StreamInterface $stream, string $path ) {
		$file = self::openOutputFile( $path );
		while ( !$stream->eof() ) {
			$buf = $stream->read( self::COPY_BUFFER_SIZE );
			if ( fwrite( $file, $buf ) !== strlen( $buf ) ) {
				throw new ShellboxError( "Error copying stream to file " . basename( $path ) );
			}
		}
		if ( !fclose( $file ) ) {
			throw new ShellboxError( "Error copying stream to file " . basename( $path ) );
		}
	}

	/**
	 * Make a directory with group/other permission bits masked out
	 *
	 * @param string $path
	 * @throws ShellboxError
	 */
	public static function mkdir( $path ) {
		if ( !mkdir( $path, 0700 ) ) {
			throw new ShellboxError( "Error creating directory " . basename( $path ) );
		}
	}

	/**
	 * Get content hash headers for MediaWiki from a stream
	 *
	 * @param StreamInterface $stream
	 * @return array
	 * @throws ShellboxError
	 */
	public static function getMwHashes( StreamInterface $stream ) {
		$srcSize = $stream->getSize();
		$md5Context = hash_init( 'md5' );
		$sha1Context = hash_init( 'sha1' );
		$hashDigestSize = 0;
		while ( !$stream->eof() ) {
			$buffer = $stream->read( 131_072 ); // 128 KiB
			hash_update( $md5Context, $buffer );
			hash_update( $sha1Context, $buffer );
			$hashDigestSize += strlen( $buffer );
		}
		if ( $hashDigestSize !== $srcSize ) {
			throw new ShellboxError( "Stream truncated while hashing" );
		}
		return [
			'etag' => hash_final( $md5Context ),
			'x-object-meta-sha1base36' =>
				\Wikimedia\base_convert( hash_final( $sha1Context ), 16, 36, 31 )
		];
	}
}
