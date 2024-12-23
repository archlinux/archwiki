<?php

namespace Shellbox\Command;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Shellbox\Client;
use Shellbox\Shellbox;
use Shellbox\ShellboxError;

/**
 * A BoxedExecutor which works by running the command on a remote server via
 * HTTP/HTTPS.
 */
class RemoteBoxedExecutor extends BoxedExecutor {
	/** @var Client */
	private $client;

	/** @var LoggerInterface */
	private $logger;

	/**
	 * @param Client $client
	 */
	public function __construct( Client $client ) {
		$this->client = $client;
		$this->logger = new NullLogger;
	}

	/**
	 * Set the logger
	 *
	 * @param LoggerInterface $logger
	 */
	public function setLogger( LoggerInterface $logger ) {
		$this->logger = $logger;
	}

	public function executeValid( BoxedCommand $command ) {
		$this->checkForUrlFiles( $command );

		// Compile the binary parts
		$parts = [ [
			'name' => 'json-data',
			'headers' => [
				'Content-Disposition' => "json-data",
				'Content-Type' => 'application/json',
			],
			'contents' => Shellbox::jsonEncode( [
				'action' => 'shell',
				'command' => $command->getClientData(),
			] )
		] ];
		foreach ( $command->getInputFiles() as $boxedName => $file ) {
			if ( $file instanceof InputFileWithContents ) {
				$parts[] = [
					'name' => $boxedName,
					'headers' => [
						'Content-Disposition' =>
							"attachment; name=\"$boxedName\"; filename=\"$boxedName\"",
						'Content-Type' => 'application/octet-stream',
					],
					'contents' => $file->getStreamOrString()
				];
			}
		}
		$stdin = $command->getStdin();
		if ( $stdin !== '' ) {
			$parts[] = [
				'name' => 'stdin',
				'headers' => [
					'Content-Disposition' => "form-data; name=stdin",
					'Content-Type' => 'application/octet-stream'
				],
				'contents' => $stdin
			];
		}
		$files = $command->getOutputFiles();
		$globs = $command->getOutputGlobs();

		// Send the request
		$resultData = $this->client->sendRequest(
			'shell/' . $command->getRouteName(),
			$parts,
			$files,
			$globs
		);

		// Validate the response
		if ( !isset( $resultData['exitCode'] ) ) {
			throw new ShellboxError( 'Server result is missing the exit code' );
		}

		// Forward log entries
		foreach ( $resultData['log'] ?? [] as $logEntry ) {
			$this->logger->log(
				$logEntry['level'],
				$logEntry['message'],
				$logEntry['context']
			);
		}

		// Construct the result
		$result = ( new BoxedResult )
			->exitCode( $resultData['exitCode'] )
			->stdout( $resultData['stdout'] ?? null )
			->stderr( $resultData['stderr'] ?? null );

		$uploadedFiles = array_fill_keys( $resultData['uploadedFiles'] ?? [], true );
		foreach ( $files as $boxedName => $file ) {
			if ( $file->wasReceived() || isset( $uploadedFiles[$boxedName] ) ) {
				$result->addOutputFile( $boxedName, $file );
			}
		}

		foreach ( $globs as $glob ) {
			foreach ( $glob->getFiles() as $boxedName => $file ) {
				$result->addOutputFile( $boxedName, $file );
			}
		}

		return $result;
	}

	public function areUrlFilesAllowed() {
		return $this->client->areUrlFilesAllowed();
	}

	/**
	 * If URL files are not allowed by client configuration, throw an exception
	 * if the specified command has any such files.
	 *
	 * Applications should check areUrlFilesAllowed() before calling
	 * inputFileFromUrl() etc. to avoid an exception from this method.
	 *
	 * @param BoxedCommand $command
	 * @throws ShellboxError
	 */
	private function checkForUrlFiles( BoxedCommand $command ) {
		if ( $this->areUrlFilesAllowed() ) {
			return;
		}
		foreach ( $command->getInputFiles() as $boxedName => $file ) {
			if ( $file instanceof InputFileFromUrl ) {
				throw new ShellboxError(
					"Can't download input file \"$boxedName\" when " .
					"allowUrlFiles is not set in the Client options" );
			}
		}
		foreach ( $command->getOutputFiles() as $boxedName => $outputFile ) {
			if ( $outputFile instanceof OutputFileToUrl ) {
				throw new ShellboxError(
					"Can't upload output file \"$boxedName\" when " .
					"allowUrlOutput is not set in the Client options" );
			}
		}
		foreach ( $command->getOutputGlobs() as $id => $outputGlob ) {
			if ( $outputGlob instanceof OutputGlobToUrl ) {
				throw new ShellboxError(
					"Can't upload output glob \"$id\" when " .
					"allowUrlOutput is not set in the Client options" );
			}
		}
	}
}
