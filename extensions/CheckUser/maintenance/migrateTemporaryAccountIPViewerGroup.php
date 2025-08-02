<?php

namespace MediaWiki\CheckUser\Maintenance;

use MediaWiki\Maintenance\LoggedUpdateMaintenance;
use MigrateUserGroup;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

/**
 * Migrates the old checkuser-temporary-account-viewer group to the new
 * temporary-account-viewer group.
 */
class MigrateTemporaryAccountIPViewerGroup extends LoggedUpdateMaintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription(
			'Migrates the old checkuser-temporary-account-viewer group to the new temporary-account-viewer group.'
		);
		$this->setBatchSize( 200 );

		$this->requireExtension( 'CheckUser' );
	}

	/** @inheritDoc */
	protected function getUpdateKey() {
		return __CLASS__;
	}

	/** @inheritDoc */
	protected function doDBUpdates() {
		$this->output( "Renaming the 'checkuser-temporary-account-viewer' group to 'temporary-account-viewer'\n" );

		// Don't run the migrateUserGroup.php script if there are no users to rename, as it
		// throws a fatal status if there are no users to rename which would exit update.php early.
		$hasRows = $this->getPrimaryDB()->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->from( 'user_groups' )
			->where( [ 'ug_group' => 'checkuser-temporary-account-viewer' ] )
			->caller( __FUNCTION__ )
			->fetchField();
		if ( !$hasRows ) {
			$this->output( "Nothing to do - no users in the 'checkuser-temporary-account-viewer' group\n" );
			return true;
		}

		$migrateUserGroup = $this->createChild( MigrateUserGroup::class );
		$migrateUserGroup->setArg( 'oldgroup', 'checkuser-temporary-account-viewer' );
		$migrateUserGroup->setArg( 'newgroup', 'temporary-account-viewer' );
		$migrateUserGroup->setOption( 'batch-size', $this->getBatchSize() );
		$migrateUserGroup->execute();

		return true;
	}
}

// @codeCoverageIgnoreStart
$maintClass = MigrateTemporaryAccountIPViewerGroup::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
