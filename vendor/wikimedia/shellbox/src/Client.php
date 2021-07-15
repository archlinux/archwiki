<?php

namespace Shellbox;

use GuzzleHttp\Psr7\MultipartStream;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Shellbox\Command\OutputFile;
use Shellbox\Command\OutputGlob;
use Shellbox\Multipart\MultipartReader;
use Shellbox\Multipart\MultipartUtils;

/**
 * A generic client which executes actions on the Shellbox server
 */
class Client {
	/** @var HttpClientInterface */
	private $httpClient;
	/** @var UriInterface */
	private $uri;
	/** @var string */
	private $key;
	/** @var LoggerInterface */
	private $logger;

	/**
	 * @param HttpClientInterface $httpClient An object which requests an HTTP
	 *   resource
	 * @param UriInterface $uri The base URI of the server
	 * @param string $key The key for HMAC authentication
	 */
	public function __construct( HttpClientInterface $httpClient, UriInterface $uri, $key ) {
		$this->httpClient = $httpClient;
		$this->uri = $uri;
		$this->key = $key;
		$this->logger = new NullLogger;
	}

	/**
	 * @param LoggerInterface $logger
	 */
	public function setLogger( LoggerInterface $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Call a PHP function remotely.
	 *
	 * @param string $routeName A short string identifying the function
	 * @param array|string $functionName A JSON-serializable callback
	 * @param array $params Function parameters. If "binary" is false or absent,
	 *   the parameters must be JSON-serializable, which means that any strings
	 *   must be valid UTF-8. If "binary" is true, the parameters must all be
	 *   strings.
	 * @param array $options An associative array of options:
	 *    - sources: An array of source file paths, to be executed on the
	 *      remote side prior to calling the function.
	 *    - classes: An array of class names. The source files for the classes
	 *      will be identified using reflection, and the files will be sent to
	 *      the server as if they were specified in the "sources" array.
	 *    - binary: If true, $params will be sent as 8-bit clean strings, and the
	 *      return value will be similarly converted to a string.
	 * @return mixed
	 * @throws ShellboxError
	 */
	public function call( $routeName, $functionName, $params = [], $options = [] ) {
		$sources = $options['sources'] ?? [];
		$binary = !empty( $options['binary'] );
		foreach ( $options['classes'] ?? [] as $class ) {
			$rc = new \ReflectionClass( $class );
			$sources[] = $rc->getFileName();
		}
		$sources = array_unique( $sources );
		$parts = [];
		$remoteSourceNames = [];
		foreach ( $sources as $i => $source ) {
			$stream = FileUtils::openInputFileStream( $source );
			$remoteSourceName = $i . '_' . basename( $source );
			$parts[] = [
				'name' => $remoteSourceName,
				'headers' => [
					'Content-Type' => 'application/x-php',
					'Content-Disposition' =>
						"attachment; name=\"$remoteSourceName\"; filename=\"$remoteSourceName\"",
				],
				'contents' => $stream
			];
			$remoteSourceNames[] = $remoteSourceName;
		}
		$inputData = [
			'action' => 'call',
			'functionName' => $functionName,
			'sources' => $remoteSourceNames,
			'binary' => $binary
		];
		if ( $binary ) {
			foreach ( $params as $i => $param ) {
				$parts[] = [
					'name' => "param$i",
					'contents' => (string)$param
				];
			}
		} else {
			$inputData['params'] = $params;
		}

		$parts[] = [
			'name' => 'json-data',
			'headers' => [
				'Content-Type' => 'application/json',
				'Content-Disposition' => 'json-data',
			],
			'contents' => Shellbox::jsonEncode( $inputData )
		];
		$outputData = $this->sendRequest( "call/$routeName", $parts );
		$this->forwardLog( $outputData['log'] );
		return $outputData['returnValue'];
	}

	/**
	 * Forward log entries which came to the server to the client's logger.
	 *
	 * @param array $entries
	 */
	private function forwardLog( $entries ) {
		foreach ( $entries as $entry ) {
			$this->logger->log(
				$entry['level'],
				$entry['message'],
				$entry['context']
			);
		}
	}

	/**
	 * Send an arbitrary request to the server.
	 *
	 * @param string $path The URL path relative to the server's base URL
	 * @param array $parts An array of multipart parts to send, in the format
	 *   specified by MultipartStream. Each part is an associative array
	 *   which for our purposes may contain:
	 *     - "name": The part name. Required but ignored when there is a
	 *       Content-Disposition header.
	 *     - "contents": Here always a StreamInterface or string
	 *     - "headers": An associative array of part headers.
	 * @param OutputFile[] $outputFiles Output files. The objects will have
	 *   their contents populated with data received from the server.
	 * @param OutputGlob[] $outputGlobs Output globs. The objects will have
	 *   be populated with data received from the server.
	 * @return array An associative array of output data
	 * @throws ShellboxError
	 */
	public function sendRequest( $path, $parts, $outputFiles = [], $outputGlobs = [] ) {
		$boundary = Shellbox::getUniqueString();
		$bodyStream = new MultipartStream( $parts, $boundary );

		$hmac = $this->computeHmac( $bodyStream );
		$bodyStream->rewind();

		$request = new Request(
			'POST',
			$this->uri->withPath( $this->uri->getPath() . '/' . $path ),
			[
				'Content-Type' => "multipart/mixed; boundary=\"$boundary\"",
				'Authorization' => "sha256 $hmac"
			],
			$bodyStream
		);

		$response = $this->httpClient->send( $request );
		$contentType = $response->getHeaderLine( 'Content-Type' );
		if ( $response->getStatusCode() !== 200 ) {
			if ( $contentType === 'application/json' ) {
				$data = Shellbox::jsonDecode( $response->getBody()->getContents() );
				if ( isset( $data['message'] ) && isset( $data['log'] ) ) {
					$this->forwardLog( $data['log'] );
					throw new ShellboxError( 'Shellbox server error: ' . $data['message'] );
				}
			}
			throw new ShellboxError( "Shellbox server returned status code " .
				$response->getStatusCode() );
		}

		$boundary = MultipartUtils::extractBoundary( $contentType );
		if ( $boundary === false ) {
			throw new ShellboxError( "Shellbox server returned incorrect Content-Type" );
		}
		$multipartReader = new MultipartReader( $response->getBody(), $boundary );
		$multipartReader->readPreamble();

		$data = [];
		$outputStrings = [];
		$partIndex = 0;
		// phpcs:ignore MediaWiki.ControlStructures.AssignmentInControlStructures
		while ( ( $headers = $multipartReader->readPartHeaders() ) !== false ) {
			if ( !isset( $headers['content-disposition'] ) ) {
				throw new ShellboxError( "Part #$partIndex has no Content-Disposition" );
			}
			$disposition = $headers['content-disposition'];
			if ( $disposition['type'] === 'json-data' ) {
				$data = $multipartReader->readPartAsJson( $headers );
			} elseif ( $disposition['type'] === 'form-data' ) {
				if ( !isset( $disposition['name'] ) ) {
					throw new ShellboxError( "Part #$partIndex has no name" );
				}
				$outputStrings[$disposition['name']] = $multipartReader->readPartAsString();
			} elseif ( $disposition['type'] === 'attachment' ) {
				$name = $disposition['name'] ?? '';
				if ( isset( $outputFiles[$name] ) ) {
					$outputFiles[$name]->readFromMultipart( $multipartReader );
				} else {
					$found = false;
					foreach ( $outputGlobs as $glob ) {
						if ( $glob->isMatch( $name ) ) {
							$found = true;
							$instance = $glob->getInstance( $name );
							$instance->readFromMultipart( $multipartReader );
							break;
						}
					}
					if ( !$found ) {
						throw new ShellboxError( "Server returned an unexpected file \"$name\"" );
					}
				}
			} else {
				throw new ShellboxError( "Unknown content disposition type" );
			}
			$partIndex++;
		}
		$multipartReader->readEpilogue();

		return $data + $outputStrings;
	}

	/**
	 * Read all data from a stream and return its HMAC.
	 *
	 * @param StreamInterface $stream
	 * @return string
	 */
	private function computeHmac( StreamInterface $stream ) {
		$hashContext = hash_init( 'sha256', HASH_HMAC, $this->key );
		while ( !$stream->eof() ) {
			hash_update( $hashContext, $stream->read( 8192 ) );
		}
		return hash_final( $hashContext );
	}
}
