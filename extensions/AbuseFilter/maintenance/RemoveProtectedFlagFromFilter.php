<?php

namespace MediaWiki\Extension\AbuseFilter\Maintenance;

use MediaWiki\Extension\AbuseFilter\Filter\Flags;
use MediaWiki\Maintenance\Maintenance;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

/**
 * Maintenance script that allows an individual filter's privacy level to remove the
 * "protected" flag from a filter, while keeping other privacy flags. This is for
 * correcting filters that were mistakenly allowed to be protected (T378551).
 *
 * Before running this script, ensure that this filter does not use protected
 * variables. Also ensure that removing the protected flag will not leak private
 * data. (For example if the filter used protected variables in the past and was
 * triggered, this could leak the data of the users who triggered it.)
 *
 * After running this script, make an edit in the "Notes" section of the affected
 * filters, to explain that the script was run, and why.
 *
 * @ingroup Maintenance
 * @since 1.44
 */
class RemoveProtectedFlagFromFilter extends Maintenance {
	public function __construct() {
		parent::__construct();

		$this->addDescription(
			'Remove the "protected" flag from a filter, while keeping other privacy flags'
		);
		$this->addArg( 'filter', 'ID of the protected filter to update' );
		$this->requireExtension( 'Abuse Filter' );
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
		$filter = $this->getArg( 0 );

		$privacyLevel = $this->getReplicaDB()->newSelectQueryBuilder()
			->select( 'af_hidden' )
			->from( 'abuse_filter' )
			->where( [
				'af_id' => $filter
			] )
			->caller( __METHOD__ )
			->fetchField();

		if ( $privacyLevel === false ) {
			$this->fatalError( "Filter $filter not found.\n" );
		}

		if ( ( $privacyLevel & Flags::FILTER_USES_PROTECTED_VARS ) === 0 ) {
			$this->output( "Filter $filter is not protected. Nothing to update.\n" );
			return false;
		}

		// The new privacy level is the old level with the bit representing "protected" unset.
		$newPrivacyLevel = (string)( $privacyLevel & ( ~Flags::FILTER_USES_PROTECTED_VARS ) );

		$this->getPrimaryDB()->newUpdateQueryBuilder()
			->update( 'abuse_filter' )
			->set( [ 'af_hidden' => $newPrivacyLevel ] )
			->where( [ 'af_id' => $filter ] )
			->caller( __METHOD__ )
			->execute();

		$this->output( "Successfully removed \"protected\" flag from filter $filter.\n" );
		return true;
	}
}

$maintClass = RemoveProtectedFlagFromFilter::class;
require_once RUN_MAINTENANCE_IF_MAIN;
