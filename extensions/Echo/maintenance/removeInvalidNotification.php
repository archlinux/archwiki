<?php
/**
 * Remove invalid events from echo_event and echo_notification
 *
 * @ingroup Maintenance
 */

use MediaWiki\Extension\Notifications\DbFactory;
use MediaWiki\Maintenance\Maintenance;

require_once getenv( 'MW_INSTALL_PATH' ) !== false
	? getenv( 'MW_INSTALL_PATH' ) . '/maintenance/Maintenance.php'
	: __DIR__ . '/../../../maintenance/Maintenance.php';

/**
 * Maintenance script that removes invalid notifications
 *
 * @ingroup Maintenance
 */
class RemoveInvalidNotification extends Maintenance {

	public function __construct() {
		parent::__construct();

		$this->addDescription( "Removes invalid notifications from the database." );
		$this->addOption( 'remove', 'Actually remove notifications instead of listing' );
		$this->setBatchSize( 500 );
		$this->requireExtension( 'Echo' );
	}

	public function execute() {
		global $wgEchoNotifications;

		$dryRun = !$this->hasOption( 'remove' );

		$lbFactory = DbFactory::newFromDefault();

		$validEventTypes = array_keys( $wgEchoNotifications );

		if ( $dryRun ) {
			$this->output( "Dry run mode. Use --remove to really remove notifications.\n" );
		}

		$dbw = $lbFactory->getEchoDb( DB_PRIMARY );
		$dbr = $lbFactory->getEchoDb( DB_REPLICA );

		$sqb = $dbr->newSelectQueryBuilder()
			->select( [ 'event_id', 'event_type' ] )
			->from( 'echo_event' )
			->where( $dbr->expr( 'event_type', '!=', $validEventTypes ) )
			->caller( __METHOD__ );

		// Using echo_event_type index
		$iterator = new BatchRowIterator( $dbr, $sqb, [ 'event_type', 'event_id' ], $this->getBatchSize() );

		$countByType = [];
		foreach ( $iterator as $batch ) {
			$event = [];
			foreach ( $batch as $row ) {
				// @phan-suppress-next-line PhanPossiblyUndeclaredVariable
				if ( !in_array( $row->event_id, $event ) ) {
					$event[] = $row->event_id;
					$countByType[$row->event_type] ??= 0;
					$countByType[$row->event_type]++;
				}
			}

			if ( $event && !$dryRun ) {
				$this->beginTransactionRound( __METHOD__ );

				$dbw->newDeleteQueryBuilder()
					->deleteFrom( 'echo_event' )
					->where( [ 'event_id' => $event ] )
					->caller( __METHOD__ )
					->execute();
				$dbw->newDeleteQueryBuilder()
					->deleteFrom( 'echo_notification' )
					->where( [ 'notification_event' => $event ] )
					->caller( __METHOD__ )
					->execute();

				$this->commitTransactionRound( __METHOD__ );

				$this->output( "...removing " . count( $event ) . " invalid events\n" );
			} else {
				$this->output( "...would remove " . count( $event ) . " invalid events\n" );
			}

			// Cleanup is not necessary for
			// 1. echo_email_batch, invalid notification is removed during the cron
		}

		if ( !$countByType ) {
			$this->output( "Nothing to do.\n" );
		} else {
			if ( !$dryRun ) {
				$this->output( "Removed " . array_sum( $countByType ) . " invalid events with types:\n" );
			} else {
				$this->output( "Would remove " . array_sum( $countByType ) . " invalid events with types:\n" );
			}
			foreach ( $countByType as $type => $removedCount ) {
				$this->output( " - $type: $removedCount\n" );
			}
		}
	}
}

$maintClass = RemoveInvalidNotification::class;
require_once RUN_MAINTENANCE_IF_MAIN;
