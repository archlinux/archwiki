<?php
/**
 * @license GPL-2.0-or-later
 *
 * @file
 * @ingroup Maintenance
 */

declare( strict_types = 1 );

namespace MediaWiki\Extension\OATHAuth\Maintenance;

use MediaWiki\Extension\OATHAuth\Key\RecoveryCodeKeys;
use MediaWiki\Extension\OATHAuth\Module\RecoveryCodes;
use MediaWiki\Extension\OATHAuth\OATHAuthServices;
use MediaWiki\Json\FormatJson;
use MediaWiki\Maintenance\Maintenance;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}

require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

class PurgeExpiredTemporaryRecoveryCodes extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Purge expired temporary recovery codes' );
	}

	public function execute() {
		$services = $this->getServiceContainer();
		$moduleRegistry = OATHAuthServices::getInstance( $services )->getModuleRegistry();
		$recoveryModuleId = $moduleRegistry->getModuleId( RecoveryCodes::MODULE_NAME );

		$dbw = $services
			->getDBLoadBalancerFactory()
			->getPrimaryDatabase( 'virtual-oathauth' );
		$res = $dbw->newSelectQueryBuilder()
			->select( [ 'oad_id', 'oad_data', 'oad_type' ] )
			->from( 'oathauth_devices' )
			->where( [ 'oad_type' => $recoveryModuleId ] )
			->caller( __METHOD__ )
			->fetchResultSet();

		$totalRows = 0;
		$updatedCount = 0;

		foreach ( $res as $row ) {
			$totalRows++;

			$data = FormatJson::decode( $row->oad_data, true );

			$numberOfKeys = count( $data['recoverycodekeys'] );

			// Creating a new key object will remove expired codes.
			// So if the count is the same, there are no expired codes to remove.
			$key = RecoveryCodeKeys::newFromArray( $data );
			if ( count( $key->getRecoveryCodes() ) === $numberOfKeys ) {
				continue;
			}

			// Because expired codes have already been removed, we can just re-serialize and update in the DB
			$dbw->newUpdateQueryBuilder()
				->update( 'oathauth_devices' )
				->set( [ 'oad_data' => FormatJson::encode( $key->jsonSerialize() ) ] )
				->where( [ 'oad_id' => $row->oad_id ] )
				->caller( __METHOD__ )
				->execute();

			$updatedCount++;
		}

		$this->output( "Done. Updated {$updatedCount} of {$totalRows} rows.\n" );
	}

}

// @codeCoverageIgnoreStart
$maintClass = PurgeExpiredTemporaryRecoveryCodes::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
