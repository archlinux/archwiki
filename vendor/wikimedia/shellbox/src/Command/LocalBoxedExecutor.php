<?php

namespace Shellbox\Command;

use GuzzleHttp\ClientInterface as GuzzleClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\MessageFormatter;
use GuzzleHttp\Middleware;
use GuzzleHttp\Promise\EachPromise;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Shellbox\FileUtils;
use Shellbox\ShellboxError;
use Shellbox\TempDirManager;

/**
 * An executor which runs a BoxedCommand locally.
 *
 * This is overridden by ServerBoxedExecutor, which runs a BoxedCommand in the
 * context of a Server.
 */
class LocalBoxedExecutor extends BoxedExecutor {
	/** @var UnboxedExecutor */
	protected $unboxedExecutor;
	/** @var TempDirManager */
	protected $tempDirManager;
	/** @var LoggerInterface */
	protected $logger;
	/** @var ClientInterface|null */
	private $urlFileClient;
	/** @var int */
	private $urlFileConcurrency = 1;
	/** @var int */
	private $uploadAttempts = 3;
	/** @var int|float */
	private $retryDelay = 1.0;

	private const PUT_SUCCESS = [ 200, 201, 202, 204 ];

	/**
	 * @param UnboxedExecutor $unboxedExecutor
	 * @param TempDirManager $tempDirManager
	 */
	public function __construct(
		UnboxedExecutor $unboxedExecutor,
		TempDirManager $tempDirManager
	) {
		$this->unboxedExecutor = $unboxedExecutor;
		$this->tempDirManager = $tempDirManager;
		$this->logger = new NullLogger;
	}

	public function executeValid( BoxedCommand $command ) {
		$command = $this->applyBoxConfig( $command );
		$this->createInputFiles( $command );
		$this->prepareOutputDirectories( $command );
		$result = $this->createResult();
		$result->merge( $this->unboxedExecutor->execute( $command ) );
		$this->collectOutputFiles( $command, $result );
		$this->cleanup();
		return $result;
	}

	/**
	 * Set the logger
	 *
	 * @param LoggerInterface $logger
	 */
	public function setLogger( LoggerInterface $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Set the client to use for fetching and putting files specified by URL.
	 *
	 * @since 4.1.0
	 * @param ClientInterface $urlFileClient
	 * @param array $options Associative array of upload/download options:
	 *   - concurrency: The maximum number of concurrent connections to use.
	 *     This will be ignored if the client does not implement Guzzle's
	 *     interface, with its async methods.
	 *   - uploadAttempts: The maximum number of times to try to upload a given
	 *     file; the number of retries plus one.
	 *     before throwing a fatal error, discarding the command's results.
	 *   - retryDelay: The number of seconds to delay between retries.
	 */
	public function setUrlFileClient( ClientInterface $urlFileClient, array $options = [] ) {
		$this->urlFileClient = $urlFileClient;
		if ( isset( $options['concurrency'] ) ) {
			$this->urlFileConcurrency = $options['concurrency'];
		}
		if ( isset( $options['uploadAttempts'] ) ) {
			$this->uploadAttempts = $options['uploadAttempts'];
		}
		if ( isset( $options['retryDelay'] ) ) {
			$this->retryDelay = $options['retryDelay'];
		}
	}

	public function areUrlFilesAllowed() {
		return $this->urlFileClient !== null;
	}

	/**
	 * @return BoxedResult
	 */
	protected function createResult() {
		return new BoxedResult;
	}

	/**
	 * Copy input files from the BoxedCommand to the working directory, ready
	 * for command execution.
	 *
	 * @param BoxedCommand $command
	 */
	protected function createInputFiles( BoxedCommand $command ) {
		$filesToDownload = [];
		foreach ( $command->getInputFiles() as $boxedName => $file ) {
			if ( $file instanceof InputFileWithContents ) {
				$path = $this->tempDirManager->preparePath( $boxedName );
				$file->copyTo( $path );
			} elseif ( $file instanceof InputFileFromUrl ) {
				$filesToDownload[$boxedName] = $file;
			} else {
				throw new ShellboxError( "Unknown input file type for file \"$boxedName\"" );
			}
		}
		if ( $filesToDownload ) {
			$this->downloadFiles( $filesToDownload );
		}
	}

	/**
	 * Download the InputFileFromUrl files to the working directory
	 *
	 * @param InputFileFromUrl[] $files The files to download, keyed by name
	 */
	protected function downloadFiles( array $files ) {
		$requests = [];
		foreach ( $files as $boxedName => $file ) {
			$requests[$boxedName] = new Request(
				'GET',
				$file->getUrl(),
				$file->getHeaders()
			);
		}
		if ( $this->urlFileClient instanceof GuzzleClientInterface ) {
			$this->downloadFilesWithGuzzle( $this->urlFileClient, $requests );
		} elseif ( !$this->urlFileClient ) {
			throw new ShellboxError( "Can't download files with no HTTP client configured" );
		} else {
			$this->downloadFilesWithPsrClient( $this->urlFileClient, $requests );
		}
	}

	private function downloadFilesWithPsrClient( ClientInterface $client, array $requests ) {
		foreach ( $requests as $boxedName => $request ) {
			$response = $client->sendRequest( $request );
			$code = $response->getStatusCode();
			$this->logger->info( 'URL PSR file request: {method} {uri} -> {code}',
				[
					'method' => $request->getMethod(),
					'uri' => (string)$request->getUri(),
					'code' => $code,
				]
			);
			if ( $code !== 200 ) {
				$this->throwUrlFileError( $boxedName, $response,
					'Failed to download input file' );
			}
			$path = $this->tempDirManager->preparePath( $boxedName );
			FileUtils::copyStreamToFile( $response->getBody(), $path );
		}
	}

	/**
	 * Download the files with streaming and concurrency. It doesn't really
	 * have to use Guzzle, it could be a user-supplied client that implements
	 * Guzzle's interface. But the 'sink' option must be supported.
	 *
	 * @param GuzzleClientInterface $client
	 * @param Request[] $requests
	 */
	private function downloadFilesWithGuzzle( GuzzleClientInterface $client, array $requests ) {
		$handler = HandlerStack::create();
		$handler->unshift( Middleware::log( $this->logger, $this->getUrlMessageFormatter() ) );
		$options = [
			'handler' => $handler,
			'allow_redirects' => false
		];

		$promises = function () use ( $requests, $client, $options ) {
			foreach ( $requests as $boxedName => $request ) {
				$path = $this->tempDirManager->preparePath( $boxedName );
				yield $boxedName
					=> $client->sendAsync( $request, $options + [ 'sink' => $path ] );
			}
		};
		$each = new EachPromise(
			$promises(),
			[
				'concurrency' => $this->urlFileConcurrency,
				'fulfilled' => function ( Response $response, $boxedName ) {
					$code = $response->getStatusCode();
					if ( $code !== 200 ) {
						$this->throwUrlFileError( $boxedName, $response,
							'Failed to download input file' );
					}
				},
				'rejected' => static function ( RequestException $reason, $boxedName ) {
					// @phan-suppress-previous-line PhanPluginNeverReturnFunction
					throw new ShellboxError(
						"Failed to download input file \"$boxedName\": " .
						$reason->getMessage() );
				},
			]
		);
		$each->promise()->wait();
	}

	/**
	 * Throw an error due to an invalid response
	 *
	 * @param string $boxedName
	 * @param ResponseInterface $response
	 * @param string $prefix
	 * @return never
	 * @throws ShellboxError
	 */
	private function throwUrlFileError( string $boxedName, ResponseInterface $response, $prefix ) {
		$code = $response->getStatusCode();
		$message = $response->getBody()->getContents();
		$message = strip_tags( $message );
		if ( strlen( $message ) > 1000 ) {
			$message = substr( $message, 0, 1000 );
		}
		throw new ShellboxError(
			"$prefix \"$boxedName\" with code $code: $message" );
	}

	/**
	 * Ensure that any subdirectories named in registered output files are
	 * created, so that the command can write the files there.
	 *
	 * @param BoxedCommand $command
	 */
	protected function prepareOutputDirectories( BoxedCommand $command ) {
		foreach ( $command->getOutputFiles() as $boxedName => $file ) {
			$this->tempDirManager->preparePath( $boxedName );
		}

		foreach ( $command->getOutputGlobs() as $boxedName => $file ) {
			$this->tempDirManager->preparePath( $boxedName );
		}
	}

	/**
	 * Modify the configuration of the command as required by the BoxedCommand
	 * abstraction, returning a cloned BoxedCommand.
	 *
	 * @param BoxedCommand $command
	 * @return BoxedCommand
	 */
	protected function applyBoxConfig( BoxedCommand $command ) {
		$command = clone $command;
		return $command
			->workingDirectory( $this->tempDirManager->prepareBasePath() )
			->passStdin( false )
			->forwardStderr( false );
	}

	/**
	 * This is called after the command has run. Find any output files which
	 * match output files and globs that were registered in the BoxedCommand.
	 *
	 * @param BoxedCommand $command
	 * @param BoxedResult $result
	 * @return OutputFile[] OutputFile objects indexed by the found filename
	 *   relative to the working directory.
	 */
	protected function findOutputFiles( BoxedCommand $command, BoxedResult $result ) {
		$files = [];
		foreach ( $command->getOutputFiles() as $boxedName => $file ) {
			$sourcePath = $this->tempDirManager->getPath( $boxedName );
			if ( file_exists( $sourcePath ) ) {
				$requiredCode = $file->getRequiredExitCode();
				if ( $requiredCode !== null && $result->getExitCode() !== $requiredCode ) {
					$this->logger->debug(
						"Ignoring output file $boxedName: required exit code not returned" );
				} else {
					$files[$boxedName] = $file;
				}
			} else {
				$this->logger->debug( 'After executing route ' . $command->getRouteName() .
					": did not find expected output file $boxedName" );
			}
		}

		foreach ( $command->getOutputGlobs() as $glob ) {
			$prefixPath = $this->tempDirManager->getPath( $glob->getPrefix() );
			foreach ( glob( "$prefixPath*.{$glob->getExtension()}" ) as $path ) {
				$base = basename( $path );
				if ( strpos( $glob->getPrefix(), '/' ) === false ) {
					$boxedName = $base;
				} else {
					$boxedName = dirname( $glob->getPrefix() ) . '/' . $base;
				}
				$files[$boxedName] = $glob->getOutputFile( $boxedName );
			}
		}

		return $files;
	}

	/**
	 * This is called after the command has run. Find output files and copy
	 * them to the registered destination location, which may be either a path
	 * outside the working directory, or a string. Register the details in the
	 * supplied BoxedResult.
	 *
	 * @param BoxedCommand $command
	 * @param BoxedResult $result
	 */
	protected function collectOutputFiles( BoxedCommand $command, BoxedResult $result ) {
		$filesToUpload = [];
		foreach ( $this->findOutputFiles( $command, $result ) as $boxedName => $outputFile ) {
			if ( $outputFile instanceof OutputFileWithContents ) {
				$outputFile->copyFromFile( $this->tempDirManager->getPath( $boxedName ) );
			} elseif ( $outputFile instanceof OutputFileToUrl ) {
				$filesToUpload[$boxedName] = $outputFile;
			}
			$result->addOutputFile( $boxedName, $outputFile );
		}

		if ( $filesToUpload ) {
			$this->uploadFiles( $filesToUpload );
		}
	}

	/**
	 * @param OutputFileToUrl[] $files
	 */
	protected function uploadFiles( array $files ) {
		$requests = [];
		foreach ( $files as $boxedName => $file ) {
			$stream = FileUtils::openInputFileStream( $this->tempDirManager->getPath( $boxedName ) );
			$headers = $file->getHeaders();
			if ( $file->isMwContentHashEnabled() ) {
				$headers += FileUtils::getMwHashes( $stream );
				$stream->rewind();
			}
			$headers['content-length'] = $stream->getSize();
			$requests[$boxedName] = new Request(
				'PUT',
				$file->getUrl(),
				$headers,
				$stream
			);
		}
		if ( $this->urlFileClient instanceof GuzzleClientInterface ) {
			$this->uploadFilesWithGuzzle( $this->urlFileClient, $requests );
		} elseif ( !$this->urlFileClient ) {
			throw new ShellboxError( "Can't upload files with no HTTP client configured" );
		} else {
			$this->uploadFilesWithPsrClient( $this->urlFileClient, $requests );
		}
	}

	/**
	 * @param GuzzleClientInterface $client
	 * @param Request[] $requests
	 */
	private function uploadFilesWithGuzzle( GuzzleClientInterface $client, array $requests ) {
		$likelyPermanent = [ 404 ];
		$handler = HandlerStack::create();
		$handler->unshift( Middleware::log( $this->logger, $this->getUrlMessageFormatter() ) );
		$options = [
			'handler' => $handler,
			'allow_redirects' => false,
		];

		$shouldRetry = function ( $retries, Request $request, ?Response $response,
			?RequestException $exception ) use ( $likelyPermanent )
		{
			if ( $retries + 1 >= $this->uploadAttempts ) {
				return false;
			}
			if ( !$response || $exception ) {
				return true;
			}
			$code = $response->getStatusCode();
			if ( in_array( $code, $likelyPermanent ) ) {
				return false;
			}
			return !in_array( $code, self::PUT_SUCCESS );
		};

		$delay = $this->retryDelay;
		$delayFunc = static function () use ( $delay ) {
			return $delay;
		};

		$handler->unshift( Middleware::retry( $shouldRetry, $delayFunc ) );

		$promises = static function () use ( $requests, $client, $options ) {
			foreach ( $requests as $boxedName => $request ) {
				yield $boxedName => $client->sendAsync( $request, $options );
			}
		};

		$each = new EachPromise(
			$promises(),
			[
				'concurrency' => $this->urlFileConcurrency,
				'fulfilled' => function ( Response $response, $boxedName ) {
					if ( !in_array( $response->getStatusCode(), self::PUT_SUCCESS ) ) {
						$this->throwUrlFileError( $boxedName, $response,
							'Failed to upload output file' );
					}
				},
				'rejected' => static function ( RequestException $ex, $boxedName ) {
					// @phan-suppress-previous-line PhanPluginNeverReturnFunction
					throw new ShellboxError(
						"Failed to upload output file \"$boxedName\": " .
						$ex->getMessage() );
				},
			]
		);
		$each->promise()->wait();
	}

	/**
	 * @param ClientInterface $client
	 * @param Request[] $requests
	 */
	private function uploadFilesWithPsrClient( ClientInterface $client, array $requests ) {
		foreach ( $requests as $boxedName => $request ) {
			$response = $client->sendRequest( $request );
			$code = $response->getStatusCode();
			$this->logger->info( 'URL PSR file request: {method} {uri} -> {code}',
				[
					'method' => $request->getMethod(),
					'uri' => (string)$request->getUri(),
					'code' => $code,
				]
			);
			if ( !in_array( $code, self::PUT_SUCCESS ) ) {
				$this->throwUrlFileError( $boxedName, $response,
					'Failed to upload output file' );
			}
			$path = $this->tempDirManager->preparePath( $boxedName );
			FileUtils::copyStreamToFile( $response->getBody(), $path );
		}
	}

	/**
	 * Get the MessageFormatter to use for logging URL file requests
	 * @return MessageFormatter
	 */
	private function getUrlMessageFormatter() {
		return new MessageFormatter( 'URL file request: {method} {uri} -> {code} {error}' );
	}

	/**
	 * Clean up files after execute
	 */
	protected function cleanup() {
		$this->tempDirManager->teardown();
	}
}
