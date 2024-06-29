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

use MediaWiki\Context\RequestContext;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Request\WebRequestUpload;
use MediaWiki\Status\Status;
use Wikimedia\ScopedCallback;

/**
 * Assemble the segments of a chunked upload.
 *
 * @ingroup Upload
 * @ingroup JobQueue
 */
class AssembleUploadChunksJob extends Job implements GenericParameterJob {
	public function __construct( array $params ) {
		parent::__construct( 'AssembleUploadChunks', $params );
		$this->removeDuplicates = true;
	}

	public function run() {
		$scope = RequestContext::importScopedSession( $this->params['session'] );
		$this->addTeardownCallback( static function () use ( &$scope ) {
			ScopedCallback::consume( $scope ); // T126450
		} );

		$logger = LoggerFactory::getInstance( 'upload' );
		$context = RequestContext::getMain();
		$user = $context->getUser();
		try {
			if ( !$user->isRegistered() ) {
				$this->setLastError( "Could not load the author user from session." );

				return false;
			}

			// TODO add some sort of proper locking maybe
			$startingStatus = UploadBase::getSessionStatus( $user, $this->params['filekey'] );
			if (
				!$startingStatus ||
				( $startingStatus['result'] ?? '' ) !== 'Poll' ||
				( $startingStatus['stage'] ?? '' ) !== 'queued'
			) {
				$logger->warning( "Tried to assemble upload that is in stage {stage}/{result}",
					[
						'stage' => $startingStatus['stage'] ?? '-',
						'result' => $startingStatus['result'] ?? '-',
						'status' => (string)( $startingStatus['status'] ?? '-' ),
						'filekey' => $this->params['filekey'],
						'filename' => $this->params['filename'],
						'user' => $user->getName(),
					]
				);
				// If it is marked as currently in progress, abort. Otherwise
				// assume it is some sort of replag issue or maybe a retry even
				// though retries are impossible and just warn.
				if (
					$startingStatus &&
					$startingStatus['stage'] === 'assembling' &&
					$startingStatus['result'] !== 'Failure'
				) {
					$this->setLastError( __METHOD__ . " already in progress" );
					return false;
				}
			}
			UploadBase::setSessionStatus(
				$user,
				$this->params['filekey'],
				[ 'result' => 'Poll', 'stage' => 'assembling', 'status' => Status::newGood() ]
			);

			$upload = new UploadFromChunks( $user );
			$upload->continueChunks(
				$this->params['filename'],
				$this->params['filekey'],
				new WebRequestUpload( $context->getRequest(), 'null' )
			);
			if (
				isset( $this->params['filesize'] ) &&
				$this->params['filesize'] !== (int)$upload->getOffset()
			) {
				// Check to make sure we are not executing prior to the API's
				// transaction being committed. (T350917)
				throw new UnexpectedValueException(
					"UploadStash file size does not match job's. Potential mis-nested transaction?"
				);
			}
			// Combine all of the chunks into a local file and upload that to a new stash file
			$status = $upload->concatenateChunks();
			if ( !$status->isGood() ) {
				UploadBase::setSessionStatus(
					$user,
					$this->params['filekey'],
					[ 'result' => 'Failure', 'stage' => 'assembling', 'status' => $status ]
				);
				$logger->info( "Chunked upload assembly job failed for {filekey} because {status}",
					[
						'filekey' => $this->params['filekey'],
						'filename' => $this->params['filename'],
						'user' => $user->getName(),
						'status' => (string)$status
					]
				);
				// the chunks did not get assembled, but this should not be considered a job
				// failure - they simply didn't pass verification for some reason, and that
				// reason is stored in above session to inform the clients
				return true;
			}

			// We can only get warnings like 'duplicate' after concatenating the chunks
			$status = Status::newGood();
			$status->value = [
				'warnings' => UploadBase::makeWarningsSerializable(
					$upload->checkWarnings( $user )
				)
			];

			// We have a new filekey for the fully concatenated file
			$newFileKey = $upload->getStashFile()->getFileKey();

			// Remove the old stash file row and first chunk file
			// Note: This does not delete the chunks, only the stash file
			// which is same as first chunk but with a different name.
			$upload->stash->removeFileNoAuth( $this->params['filekey'] );

			// Build the image info array while we have the local reference handy
			$apiUpload = ApiUpload::getDummyInstance();
			$imageInfo = $apiUpload->getUploadImageInfo( $upload );

			// Cleanup any temporary local file
			$upload->cleanupTempFile();

			// Cache the info so the user doesn't have to wait forever to get the final info
			UploadBase::setSessionStatus(
				$user,
				$this->params['filekey'],
				[
					'result' => 'Success',
					'stage' => 'assembling',
					'filekey' => $newFileKey,
					'imageinfo' => $imageInfo,
					'status' => $status
				]
			);
			$logger->info( "{filekey} successfully assembled into {newkey}",
				[
					'filekey' => $this->params['filekey'],
					'newkey' => $newFileKey,
					'filename' => $this->params['filename'],
					'user' => $user->getName(),
					'status' => (string)$status
				]
			);
		} catch ( Exception $e ) {
			UploadBase::setSessionStatus(
				$user,
				$this->params['filekey'],
				[
					'result' => 'Failure',
					'stage' => 'assembling',
					'status' => Status::newFatal( 'api-error-stashfailed' )
				]
			);
			$this->setLastError( get_class( $e ) . ": " . $e->getMessage() );
			// To be extra robust.
			MWExceptionHandler::rollbackPrimaryChangesAndLog( $e );

			return false;
		}

		return true;
	}

	public function getDeduplicationInfo() {
		$info = parent::getDeduplicationInfo();
		if ( is_array( $info['params'] ) ) {
			$info['params'] = [ 'filekey' => $info['params']['filekey'] ];
		}

		return $info;
	}

	public function allowRetries() {
		return false;
	}
}
