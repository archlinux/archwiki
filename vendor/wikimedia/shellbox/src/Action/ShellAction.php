<?php

namespace Shellbox\Action;

use GuzzleHttp\Client as GuzzleClient;
use Shellbox\Command\BoxedCommand;
use Shellbox\Command\InputFile;
use Shellbox\Command\InputFileFromUrl;
use Shellbox\Command\OutputFile;
use Shellbox\Command\OutputFileToUrl;
use Shellbox\Command\OutputGlob;
use Shellbox\Command\OutputGlobToUrl;
use Shellbox\Command\ServerBoxedExecutor;
use Shellbox\Command\ServerUnboxedExecutor;
use Shellbox\ShellboxError;

/**
 * Shell command handler
 */
class ShellAction extends MultipartAction {
	/**
	 * @param string[] $pathParts
	 */
	protected function execute( $pathParts ) {
		$commandData = $this->getRequiredParam( 'command' );
		$command = $this->createCommand( $commandData );
		$this->validateInputFiles( $command->getInputFiles() );
		$this->validateRoute( $command->getRouteName(), $pathParts );
		$this->validateOutputs( $command->getOutputFiles(), $command->getOutputGlobs() );
		$result = $command->execute();
		$binaryData = [];
		if ( $result->getStdout() !== null ) {
			$binaryData['stdout'] = $result->getStdout();
		}
		if ( $result->getStderr() !== null ) {
			$binaryData['stderr'] = $result->getStderr();
		}
		$this->writeResult(
			[
				'exitCode' => $result->getExitCode(),
				'uploadedFiles' => $result->getUploadedFileNames(),
			],
			$binaryData,
			$result->getReceivedFileNames()
		);
	}

	protected function getActionName() {
		return 'shell';
	}

	/**
	 * @return ServerBoxedExecutor
	 */
	private function createExecutor() {
		$unboxedExecutor = new ServerUnboxedExecutor( $this->tempDirManager );
		$unboxedExecutor->setLogger( $this->logger );
		$unboxedExecutor->addWrappersFromConfiguration( [
			'useSystemd' => $this->getConfig( 'useSystemd' ),
			'useBashWrapper' => $this->getConfig( 'useBashWrapper' ),
			'useFirejail' => $this->getConfig( 'useFirejail' ),
			'firejailPath' => $this->getConfig( 'firejailPath' ),
			'firejailProfile' => $this->getConfig( 'firejailProfile' )
		] );

		$executor = new ServerBoxedExecutor( $unboxedExecutor, $this->tempDirManager );
		$executor->setLogger( $this->logger );
		$executor->setValidationConfig( [
			'allowedRoutes' => $this->getConfig( 'allowedRoutes' ),
			'routeSpecs' => $this->getConfig( 'routeSpecs' )
		] );

		if ( $this->getConfig( 'allowUrlFiles' ) ) {
			$executor->setUrlFileClient(
				new GuzzleClient( [
					'connect_timeout' => $this->getConfig( 'urlFileConnectTimeout' ),
					'timeout' => $this->getConfig( 'urlFileRequestTimeout' ),
					'exceptions' => false,
				] ),
				[
					'concurrency' => $this->getConfig( 'urlFileConcurrency' ),
					'uploadAttempts' => $this->getConfig( 'urlFileUploadAttempts' ),
					'retryDelay' => $this->getConfig( 'urlFileRetryDelay' ),
				],
			);
		}

		return $executor;
	}

	/**
	 * @param array $commandData
	 * @return BoxedCommand
	 */
	private function createCommand( $commandData ) {
		$executor = $this->createExecutor();
		$command = $executor->createCommand();
		$command->setClientData( $commandData );
		$stdin = $this->getParam( 'stdin' );
		if ( $stdin !== null ) {
			$command->stdin( $stdin );
		}
		return $command;
	}

	/**
	 * Confirm that the received input file list matches the one in the command
	 * client data. This ensures that the input file list will be correctly
	 * validated by Validator.
	 *
	 * @param InputFile[] $clientDataFiles
	 * @throws ShellboxError
	 */
	private function validateInputFiles( $clientDataFiles ) {
		$allowUrlFiles = $this->getConfig( 'allowUrlFiles' );
		$sent = [];
		foreach ( $clientDataFiles as $path => $file ) {
			if ( $file instanceof InputFileFromUrl ) {
				if ( !$allowUrlFiles ) {
					throw new ShellboxError(
						'Received a URL download request but allowUrlFiles is ' .
						'false in the server config' );
				}
			} else {
				$sent[] = $path;
			}
		}
		$received = $this->getReceivedFileNames();
		sort( $received );
		sort( $sent );

		if ( $sent !== $received ) {
			throw new ShellboxError(
				"The received file list does not match the command client data" );
		}
	}

	/**
	 * Confirm that the path route matches the command parameter route
	 *
	 * @param string $commandRoute
	 * @param string[] $pathParts
	 * @throws ShellboxError
	 */
	private function validateRoute( $commandRoute, $pathParts ) {
		if ( count( $pathParts ) !== 1 || $pathParts[0] !== $commandRoute ) {
			throw new ShellboxError(
				"The request path does not match the route in the command" );
		}
	}

	/**
	 * Check for URL output files if they are not allowed.
	 *
	 * We don't inject an HTTP client when download/upload is not allowed, so
	 * there are two layers of protection, but we can give a better error
	 * message here.
	 *
	 * @param OutputFile[] $files
	 * @param OutputGlob[] $globs
	 */
	private function validateOutputs( $files, $globs ) {
		if ( !$this->getConfig( 'allowUrlFiles' ) ) {
			foreach ( $files as $file ) {
				if ( $file instanceof OutputFileToUrl ) {
					throw new ShellboxError(
						'Received a URL upload request but allowUrlFiles is ' .
						'false in the server config'
					);
				}
			}
			foreach ( $globs as $glob ) {
				if ( $glob instanceof OutputGlobToUrl ) {
					throw new ShellboxError(
						'Received a URL upload request but allowUrlFiles is ' .
						'false in the server config'
					);
				}
			}
		}
	}

}
