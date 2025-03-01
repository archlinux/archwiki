<?php

namespace Shellbox\Command;

use Shellbox\ShellboxError;

/**
 * A BoxedExecutor for internal use by the Server.
 *
 * The Server reads input files directly from the multipart stream into the
 * temporary directory. So regular input files do not need to be created, we
 * only need to download the URL files. Similarly, output file destruction is
 * deferred until after the response has been sent.
 *
 * @internal
 */
class ServerBoxedExecutor extends LocalBoxedExecutor {
	protected function createResult() {
		return new ServerBoxedResult;
	}

	protected function createInputFiles( BoxedCommand $command ) {
		$filesToDownload = [];
		foreach ( $command->getInputFiles() as $boxedName => $file ) {
			if ( $file instanceof InputFileFromUrl ) {
				$filesToDownload[$boxedName] = $file;
			}
		}
		if ( $filesToDownload ) {
			$this->downloadFiles( $filesToDownload );
		}
	}

	protected function collectOutputFiles( BoxedCommand $command, BoxedResult $result ) {
		if ( !( $result instanceof ServerBoxedResult ) ) {
			throw new ShellboxError( "Unexpected result class" );
		}
		$generatedFilesNames = [];
		$filesToUpload = [];
		foreach ( $this->findOutputFiles( $command, $result ) as $boxedName => $file ) {
			if ( $file instanceof OutputFileToUrl ) {
				$filesToUpload[$boxedName] = $file;
			}
			$generatedFilesNames[] = $boxedName;
		}

		if ( $filesToUpload ) {
			$this->uploadFiles( $filesToUpload );
		}
		$sentFileNames = array_diff( $generatedFilesNames, array_keys( $filesToUpload ) );
		$result->setFileNames( $generatedFilesNames, $sentFileNames );
	}

	protected function cleanup() {
	}
}
