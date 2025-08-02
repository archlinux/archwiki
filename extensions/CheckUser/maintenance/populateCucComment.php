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

use MediaWiki\Maintenance\LoggedUpdateMaintenance;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

/**
 * Maintenance script for filling up cuc_comment_id.
 *
 * @author Zabe
 */
class PopulateCucComment extends LoggedUpdateMaintenance {

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'CheckUser' );
		$this->addDescription( 'Populate the cuc_comment_id column.' );
		$this->addOption(
			'sleep',
			'Sleep time (in seconds) between every batch. Default: 0',
			false,
			true
		);
		$this->addOption( 'start', 'Start after this cuc_id', false, true );
	}

	/**
	 * @inheritDoc
	 */
	protected function getUpdateKey() {
		return 'PopulateCucComment';
	}

	/**
	 * @inheritDoc
	 */
	protected function doDBUpdates() {
		$services = $this->getServiceContainer();
		$commentStore = $services->getCommentStore();
		$mainLb = $services->getDBLoadBalancerFactory()->getMainLB();
		$dbr = $mainLb->getConnection( DB_REPLICA, 'vslow' );
		$dbw = $mainLb->getMaintenanceConnectionRef( DB_PRIMARY );
		$batchSize = $this->getBatchSize();

		$start = (int)$this->getOption( 'start', 0 );

		if ( $start > 0 ) {
			$prevId = $start;
		} else {
			$prevId = (int)$dbr->newSelectQueryBuilder()
				->field( 'MIN(cuc_id)' )
				->table( 'cu_changes' )
				->caller( __METHOD__ )
				->fetchField();
		}
		$curId = $prevId + $batchSize;
		$maxId = (int)$dbr->newSelectQueryBuilder()
			->field( 'MAX(cuc_id)' )
			->table( 'cu_changes' )
			->caller( __METHOD__ )
			->fetchField();

		if ( !$maxId ) {
			$this->output( "The cu_changes table seems to be empty.\n" );
			return true;
		}

		if ( !$dbw->fieldExists( 'cu_changes', 'cuc_comment' ) ) {
			$this->output( "cuc_comment has already been dropped.\n" );
			return true;
		}

		$this->output( "Populating the cuc_comment_id column...\n" );

		$diff = $maxId - $prevId;
		if ( $batchSize > $diff ) {
			$batchSize = $diff;
		}
		$failed = 0;
		$sleep = (int)$this->getOption( 'sleep', 0 );

		do {
			$res = $dbr->newSelectQueryBuilder()
				->fields( [ 'cuc_id', 'cuc_comment' ] )
				->table( 'cu_changes' )
				->conds( [
					'cuc_comment_id' => 0,
					$dbr->expr( 'cuc_id', '>=', $prevId ),
					$dbr->expr( 'cuc_id', '<=', $curId ),
				] )
				->caller( __METHOD__ )
				->fetchResultSet();

			foreach ( $res as $row ) {
				$commentId = $commentStore->createComment( $dbw, $row->cuc_comment )->id;

				if ( !$commentId ) {
					$failed++;
					continue;
				}

				$dbw->newUpdateQueryBuilder()
					->update( 'cu_changes' )
					->set( [ 'cuc_comment_id' => $commentId ] )
					->where( [ 'cuc_id' => $row->cuc_id ] )
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

$maintClass = PopulateCucComment::class;
require_once RUN_MAINTENANCE_IF_MAIN;
