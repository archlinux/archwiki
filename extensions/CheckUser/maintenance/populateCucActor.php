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
 * Maintenance script for filling up cuc_actor.
 *
 * @author Zabe
 */
class PopulateCucActor extends LoggedUpdateMaintenance {

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'CheckUser' );
		$this->addDescription( 'Populate the cuc_actor column.' );
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
		return 'PopulateCucActor';
	}

	/**
	 * @inheritDoc
	 */
	protected function doDBUpdates() {
		$services = $this->getServiceContainer();
		$actorStore = $services->getActorStore();
		$mainLb = $services->getDBLoadBalancerFactory()->getMainLB();
		$dbr = $mainLb->getConnection( DB_REPLICA, 'vslow' );
		$dbw = $mainLb->getMaintenanceConnectionRef( DB_PRIMARY );
		$batchSize = $this->getBatchSize();

		$prevId = (int)$dbr->newSelectQueryBuilder()
			->field( 'MIN(cuc_id)' )
			->table( 'cu_changes' )
			->caller( __METHOD__ )
			->fetchField();
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

		if ( !$dbw->fieldExists( 'cu_changes', 'cuc_user' ) ) {
			$this->output( "cuc_user and cuc_user_text have already been dropped.\n" );
			return true;
		}

		$diff = $maxId - $prevId;
		$failed = 0;
		$sleep = (int)$this->getOption( 'sleep', 0 );

		do {
			$res = $dbr->newSelectQueryBuilder()
				->fields( [ 'cuc_id', 'cuc_user_text' ] )
				->table( 'cu_changes' )
				->conds( [
					'cuc_actor' => 0,
					$dbr->expr( 'cuc_id', '>=', $prevId ),
					$dbr->expr( 'cuc_id', '<=', $curId ),
				] )
				->caller( __METHOD__ )
				->fetchResultSet();

			foreach ( $res as $row ) {
				$actor = $actorStore->findActorIdByName( $row->cuc_user_text, $dbr );

				if ( !$actor ) {
					$failed++;
					continue;
				}

				$dbw->newUpdateQueryBuilder()
					->update( 'cu_changes' )
					->set( [ 'cuc_actor' => $actor ] )
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

$maintClass = PopulateCucActor::class;
require_once RUN_MAINTENANCE_IF_MAIN;
