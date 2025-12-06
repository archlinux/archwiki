<?php

namespace MediaWiki\Extension\AbuseFilter\Maintenance;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

use MediaWiki\Maintenance\Maintenance;

/**
 * @deprecated since 1.45. Use PurgeOldLogData.php instead.
 */
class PurgeOldLogIPData extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Deprecated alias for the PurgeOldLogData.php maintenance script.' );
		$this->setBatchSize( 200 );

		$this->requireExtension( 'Abuse Filter' );
	}

	/** @inheritDoc */
	public function execute() {
		$maintenanceScript = $this->createChild( PurgeOldLogData::class );
		$maintenanceScript->setBatchSize( $this->getBatchSize() );
		$maintenanceScript->execute();
	}

}

// @codeCoverageIgnoreStart
$maintClass = PurgeOldLogIPData::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
