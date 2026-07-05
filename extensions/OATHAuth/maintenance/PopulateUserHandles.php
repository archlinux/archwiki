<?php
/**
 * @license GPL-2.0-or-later
 *
 * @file
 * @ingroup Maintenance
 */

declare( strict_types = 1 );

namespace MediaWiki\Extension\OATHAuth\Maintenance;

use MediaWiki\Extension\OATHAuth\Module\WebAuthn;
use MediaWiki\Extension\OATHAuth\OATHAuthServices;
use MediaWiki\Json\FormatJson;
use MediaWiki\Maintenance\LoggedUpdateMaintenance;
use MediaWiki\Utils\BatchRowIterator;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}

require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

/**
 * Populates the oathauth_user_handles table.
 *
 * Usage: php PopulateUserHandles.php
 */
class PopulateUserHandles extends LoggedUpdateMaintenance {

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'OATHAuth' );
		$this->addDescription( 'Populates the oathauth_user_handles table' );
		$this->setBatchSize( 500 );
	}

	/** @inheritDoc */
	protected function doDBUpdates() {
		$startTime = time();
		$updatedUsers = 0;
		$totalUsers = 0;

		$services = $this->getServiceContainer();

		$moduleRegistry = OATHAuthServices::getInstance()->getModuleRegistry();
		$webauthnModuleId = $moduleRegistry->getModuleId( WebAuthn::MODULE_ID );

		$dbw = $services
			->getDBLoadBalancerFactory()
			->getPrimaryDatabase( 'virtual-oathauth' );

		$sqb = $dbw->newSelectQueryBuilder()
			->select( [ 'oad_id', 'oad_user', 'oad_data' ] )
			->from( 'oathauth_devices' )
			->where( [ 'oad_type' => $webauthnModuleId ] )
			->caller( __METHOD__ );

		$batches = new BatchRowIterator( $dbw, $sqb, 'oad_user', $this->getBatchSize() );

		$prevUser = null;
		foreach ( $batches as $rows ) {
			$this->beginTransactionRound( __METHOD__ );

			foreach ( $rows as $row ) {
				// We may get multiple rows for the same user. If we already processed this user,
				// skip this row.
				if ( $row->oad_user === $prevUser ) {
					continue;
				}

				$keyData = FormatJson::decode( $row->oad_data, true );
				if ( !isset( $keyData['userHandle'] ) ) {
					$this->error( "Key Id {$row->oad_id} doesn't have a userHandle. This shouldn't happen." );
					continue;
				}

				$dbw->newInsertQueryBuilder()
					->insertInto( 'oathauth_user_handles' )
					->ignore()
					->row( [
						'oah_user' => $row->oad_user,
						// The userHandle value in $keyData is already base64-encoded, we don't need
						// to decode and re-encode it
						'oah_handle' => $keyData['userHandle']
					] )
					->caller( __METHOD__ )
					->execute();

				$prevUser = $row->oad_user;

				$updatedUsers += $dbw->affectedRows();
				$totalUsers++;

				if ( $totalUsers % 50 === 0 ) {
					$this->output( "{$totalUsers}\n" );
				}
			}

			$this->commitTransactionRound( __METHOD__ );
		}

		$totalTimeInSeconds = time() - $startTime;
		$this->output( "Done. Processed {$totalUsers} users and inserted {$updatedUsers} rows " .
			"in {$totalTimeInSeconds} seconds.\n" );
		return true;
	}

	/** @return string */
	protected function getUpdateKey() {
		return __CLASS__;
	}
}

// @codeCoverageIgnoreStart
$maintClass = PopulateUserHandles::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
