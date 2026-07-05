<?php

namespace MediaWiki\Extension\CheckUser\Maintenance;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\CheckUser\CheckUserQueryInterface;
// phpcs:ignore Generic.Files.LineLength
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Instrumentation\NoOpSuggestedInvestigationsInstrumentationClient;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCaseManagerService;
use MediaWiki\Maintenance\LoggedUpdateMaintenance;
use Wikimedia\Services\NoSuchServiceException;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

/**
 * Maintenance script that populates the `sic_url_identifier` column in the `cusi_case` table.
 */
class PopulateSicUrlIdentifier extends LoggedUpdateMaintenance {

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'CheckUser' );
		$this->addDescription( 'Populate the sic_url_identifier column in cusi_case.' );
		$this->addOption(
			'sleep',
			'Sleep time (in seconds) between every batch. Default: 0',
			false,
			true
		);
	}

	/** @inheritDoc */
	protected function getUpdateKey() {
		return __CLASS__;
	}

	/** @inheritDoc */
	protected function doDBUpdates() {
		$this->output( "Populating sic_url_identifier in cusi_case...\n" );

		// If suggested investigations is not enabled, then return early. We cannot do this check if
		// this is being run by install.php as config is not fully available there.
		// If being run by install.php, the cusi_case table should exist and so we should just continue.
		if (
			$this->getConfig()->has( 'CheckUserSuggestedInvestigationsEnabled' ) &&
			!$this->getConfig()->get( 'CheckUserSuggestedInvestigationsEnabled' )
		) {
			$this->output( "Nothing to do as CheckUser Suggested Investigations is not enabled.\n" );
			return true;
		}

		$caseManager = $this->getSuggestedInvestigationsCaseManager();
		$dbProvider = $this->getServiceContainer()->getConnectionProvider();
		$dbw = $dbProvider->getPrimaryDatabase( CheckUserQueryInterface::VIRTUAL_DB_DOMAIN );
		$dbr = $dbProvider->getReplicaDatabase( CheckUserQueryInterface::VIRTUAL_DB_DOMAIN );

		$count = 0;
		do {
			$idsBatch = $dbr->newSelectQueryBuilder()
				->select( [ 'sic_id' ] )
				->from( 'cusi_case' )
				->where( [ 'sic_url_identifier' => 0 ] )
				->limit( $this->getBatchSize() ?? 200 )
				->caller( __METHOD__ )
				->fetchFieldValues();

			foreach ( $idsBatch as $id ) {
				$dbw->newUpdateQueryBuilder()
					->update( 'cusi_case' )
					->set( [ 'sic_url_identifier' => $caseManager->generateUniqueUrlIdentifier( true ) ] )
					->where( [ 'sic_id' => $id ] )
					->caller( __METHOD__ )
					->execute();
				$count += $dbw->affectedRows();
			}
			$this->output( "... $count rows populated\n" );

			sleep( intval( $this->getOption( 'sleep', 0 ) ) );
			$this->waitForReplication();
		} while ( count( $idsBatch ) > 0 );

		$this->output( "Done. Populated $count rows.\n" );
		return true;
	}

	/**
	 * Fetches an instance of the {@link SuggestedInvestigationsCaseManagerService}.
	 *
	 * We cannot directly fetch the service because we may be running this script during DB updates where
	 * we don't have either config or services defined from CheckUser. In this case, this method will
	 * manually construct the service.
	 */
	private function getSuggestedInvestigationsCaseManager(): SuggestedInvestigationsCaseManagerService {
		try {
			return $this->getServiceContainer()->get( 'CheckUserSuggestedInvestigationsCaseManager' );
		} catch ( NoSuchServiceException ) {
			return new SuggestedInvestigationsCaseManagerService(
				new ServiceOptions(
					SuggestedInvestigationsCaseManagerService::CONSTRUCTOR_OPTIONS,
					[ 'CheckUserSuggestedInvestigationsEnabled' => true ]
				),
				$this->getServiceContainer()->getConnectionProvider(),
				$this->getServiceContainer()->getUserIdentityLookup(),
				new NoOpSuggestedInvestigationsInstrumentationClient()
			);
		}
	}
}

// @codeCoverageIgnoreStart
$maintClass = PopulateSicUrlIdentifier::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
