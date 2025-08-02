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

namespace MediaWiki\CheckUser\Maintenance;

use MediaWiki\CheckUser\Services\CheckUserLogService;
use MediaWiki\Maintenance\LoggedUpdateMaintenance;
use Psr\Log\NullLogger;
use Wikimedia\Services\NoSuchServiceException;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

/**
 * Maintenance script for filling up cul_reason_id
 *  and cul_reason_plaintext_id
 *
 * @author Modified version of populateCucComment by Zabe
 */
class PopulateCulComment extends LoggedUpdateMaintenance {

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'CheckUser' );
		$this->addDescription( 'Populate the cul_reason_id and cul_reason_plaintext_id columns.' );
		$this->addOption(
			'sleep',
			'Sleep time (in seconds) between every batch. Default: 0',
			false,
			true
		);
	}

	/**
	 * @inheritDoc
	 */
	protected function getUpdateKey() {
		return 'PopulateCulComment-2';
	}

	/**
	 * @inheritDoc
	 */
	protected function doDBUpdates() {
		$services = $this->getServiceContainer();
		$commentStore = $services->getCommentStore();
		try {
			/** @var CheckUserLogService $checkUserLogService */
			$checkUserLogService = $services->get( 'CheckUserLogService' );
		} catch ( NoSuchServiceException $ex ) {
			# CheckUser ServiceWiring files may not loaded until
			#  postDatabaseUpdateMaintenance is run.
			# If this is the case, manually get the service.
			$checkUserLogService = new CheckUserLogService(
				$services->getDBLoadBalancerFactory(),
				$services->getCommentStore(),
				$services->getCommentFormatter(),
				// No need to log as this maintenance script does not use any methods
				//  that use the logger.
				new NullLogger(),
				$services->getActorStore(),
				$services->getUserIdentityLookup()
			);
		}
		$mainLb = $services->getDBLoadBalancerFactory()->getMainLB();
		$dbr = $mainLb->getConnection( DB_REPLICA, 'vslow' );
		$dbw = $mainLb->getMaintenanceConnectionRef( DB_PRIMARY );
		$batchSize = $this->getBatchSize();

		$prevId = 1;
		$curId = $prevId + $batchSize;
		$maxId = (int)$dbr->newSelectQueryBuilder()
			->field( 'MAX(cul_id)' )
			->table( 'cu_log' )
			->caller( __METHOD__ )
			->fetchField();

		if ( !$maxId ) {
			$this->output( "The cu_log table seems to be empty.\n" );
			return true;
		}

		if ( !$dbw->fieldExists( 'cu_log', 'cul_reason' ) ) {
			$this->output( "The cul_reason field does not exist which is needed for migration.\n" );
			return true;
		}

		$this->output( "Populating the cul_reason_id and cul_reason_plaintext_id columns...\n" );

		$diff = $maxId - $prevId;
		if ( $batchSize > $diff ) {
			$batchSize = $diff;
		}
		$failed = 0;
		$sleep = (int)$this->getOption( 'sleep', 0 );

		do {
			$res = $dbr->newSelectQueryBuilder()
				->fields( [ 'cul_id', 'cul_reason' ] )
				->table( 'cu_log' )
				->conds( [
					'cul_reason_id' => [ 0, null ],
					$dbr->expr( 'cul_id', '>=', $prevId ),
					$dbr->expr( 'cul_id', '<=', $curId ),
				] )
				->caller( __METHOD__ )
				->fetchResultSet();

			foreach ( $res as $row ) {
				$culReasonId = $commentStore->createComment( $dbw, $row->cul_reason )->id;
				$culReasonPlaintextId = $commentStore->createComment(
					$dbw, $checkUserLogService->getPlaintextReason( $row->cul_reason )
				)->id;

				if ( !$culReasonId || !$culReasonPlaintextId ) {
					$failed++;
					continue;
				}

				$dbw->newUpdateQueryBuilder()
					->update( 'cu_log' )
					->set( [
						'cul_reason_id' => $culReasonId,
						'cul_reason_plaintext_id' => $culReasonPlaintextId,
					] )
					->where( [ 'cul_id' => $row->cul_id ] )
					->caller( __METHOD__ )
					->execute();
			}

			$this->waitForReplication();

			if ( $sleep > 0 ) {
				sleep( $sleep );
			}

			$this->output( "Processed $batchSize rows out of $diff.\n" );

			$prevId = $curId;
			$curId += $batchSize;
		} while ( $prevId <= $maxId );

		$this->output( "Done. Migration failed for $failed row(s).\n" );
		return true;
	}
}

$maintClass = PopulateCulComment::class;
require_once RUN_MAINTENANCE_IF_MAIN;
