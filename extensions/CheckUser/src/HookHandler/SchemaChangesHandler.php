<?php

namespace MediaWiki\Extension\CheckUser\HookHandler;

use MediaWiki\Extension\CheckUser\CheckUserQueryInterface;
use MediaWiki\Extension\CheckUser\Maintenance\DeleteReadOldRowsInCuChanges;
use MediaWiki\Extension\CheckUser\Maintenance\FixTrailingSpacesInLogs;
use MediaWiki\Extension\CheckUser\Maintenance\MigrateTemporaryAccountIPViewerGroup;
use MediaWiki\Extension\CheckUser\Maintenance\MoveLogEntriesFromCuChanges;
use MediaWiki\Extension\CheckUser\Maintenance\PopulateCentralCheckUserIndexTables;
use MediaWiki\Extension\CheckUser\Maintenance\PopulateCheckUserTable;
use MediaWiki\Extension\CheckUser\Maintenance\PopulateCucComment;
use MediaWiki\Extension\CheckUser\Maintenance\PopulateCulComment;
use MediaWiki\Extension\CheckUser\Maintenance\PopulateSicUpdatedTimestamp;
use MediaWiki\Extension\CheckUser\Maintenance\PopulateSicUrlIdentifier;
use MediaWiki\Extension\CheckUser\Maintenance\PopulateUserAgentTable;
use MediaWiki\Extension\CheckUser\Maintenance\QueueAutoCloseSICases;
use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;

class SchemaChangesHandler implements LoadExtensionSchemaUpdatesHook, CheckUserQueryInterface {
	/**
	 * @codeCoverageIgnore This is tested by installing or updating MediaWiki
	 * @inheritDoc
	 */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		$base = __DIR__ . '/../../schema';
		$maintenanceDb = $updater->getDB();
		$dbType = $maintenanceDb->getType();
		$isCUInstalled = $updater->tableExists( 'cu_changes' );

		$updater->addExtensionTable( 'cu_changes', "$base/$dbType/tables-generated.sql" );
		$updater->addExtensionUpdateOnVirtualDomain( [
			self::VIRTUAL_DB_DOMAIN, 'addTable', 'cusi_case',
			"$base/$dbType/tables-virtual-checkuser-generated.sql", true,
		] );

		// Added 1.43, but will need to remain here forever as it creates tables which are not in tables-generated.sql
		$updater->addExtensionUpdateOnVirtualDomain( [
			self::VIRTUAL_GLOBAL_DB_DOMAIN, 'addTable', 'cuci_wiki_map',
			"$base/$dbType/cuci_wiki_map.sql", true,
		] );
		$updater->addExtensionUpdateOnVirtualDomain( [
			self::VIRTUAL_GLOBAL_DB_DOMAIN, 'addTable', 'cuci_temp_edit',
			"$base/$dbType/cuci_temp_edit.sql", true,
		] );
		$updater->addExtensionUpdateOnVirtualDomain( [
			self::VIRTUAL_GLOBAL_DB_DOMAIN, 'addTable', 'cuci_user',
			"$base/$dbType/cuci_user.sql", true,
		] );

		// 1.40
		$updater->addExtensionUpdate( [
			'runMaintenance',
			PopulateCulComment::class,
		] );
		$updater->addExtensionUpdate( [
			'runMaintenance',
			PopulateCucComment::class,
		] );
		$updater->addExtensionTable(
			'cu_log_event',
			"$base/$dbType/patch-cu_log_event-def.sql"
		);
		$updater->addExtensionTable(
			'cu_private_event',
			"$base/$dbType/patch-cu_private_event-def.sql"
		);
		$updater->dropExtensionField(
			'cu_log',
			'cul_user',
			"$base/$dbType/patch-cu_log-drop-cul_user.sql"
		);
		if (
			$dbType !== 'sqlite' ||
			$maintenanceDb->fieldExists( 'cu_log', 'cul_reason', __METHOD__ )
		) {
			// Only run this for SQLite if cul_reason exists,
			//  as modifyExtensionField does not take into account
			//  SQLite patches that use temporary tables. If the cul_reason
			//  field does not exist this SQL would fail, however, cul_reason
			//  not existing also means this change has been previously applied.
			$updater->modifyExtensionField(
				'cu_log',
				'cul_actor',
				"$base/$dbType/patch-cu_log-drop-actor_default.sql"
			);
		}
		$updater->dropExtensionField(
			'cu_log',
			'cul_reason',
			"$base/$dbType/patch-cu_log-drop-cul_reason.sql"
		);
		$updater->modifyExtensionField(
			'cu_log',
			'cul_reason_id',
			"$base/$dbType/patch-cu_log-drop-cul_reason_id_default.sql"
		);
		$updater->dropExtensionField(
			'cu_changes',
			'cuc_user',
			"$base/$dbType/patch-cu_changes-drop-cuc_user.sql"
		);
		// Skip adding the cuc_only_for_read_old column if:
		// * This is an install of CheckUser MW 1.43 or later (and therefore no migration is necesary)
		// * The column has been in the table before (checked by seeing if the cuc_agent_id column exists
		//   in the cu_changes, and if it does then this update has been run before therefore it should be
		//   skipped).
		if ( $isCUInstalled && !$maintenanceDb->fieldExists( 'cu_changes', 'cuc_agent_id', __METHOD__ ) ) {
			$updater->addExtensionField(
				'cu_changes',
				'cuc_only_for_read_old',
				"$base/$dbType/patch-cu_changes-add-cuc_only_for_read_old.sql"
			);
		}
		$updater->dropExtensionField(
			'cu_changes',
			'cuc_comment',
			"$base/$dbType/patch-cu_changes-drop-cuc_comment.sql"
		);
		// Only run this for SQLite if cuc_only_for_read_old exists, as modifyExtensionField does not take into
		// account that SQLite patches that use temporary tables. If the cuc_only_for_read_old field does not exist
		// this SQL would fail, however, cuc_only_for_read_old not existing also means this change has
		// been previously applied.
		if (
			$dbType !== 'sqlite' ||
			$maintenanceDb->fieldExists( 'cu_changes', 'cuc_only_for_read_old', __METHOD__ )
		) {
			$updater->modifyExtensionField(
				'cu_changes',
				'cuc_actor',
				"$base/$dbType/patch-cu_changes-drop-defaults.sql"
			);
		}

		// 1.41
		$updater->addExtensionTable( 'cu_useragent_clienthints', "$base/$dbType/cu_useragent_clienthints.sql" );
		$updater->addExtensionTable( 'cu_useragent_clienthints_map', "$base/$dbType/cu_useragent_clienthints_map.sql" );
		// Must be run before deleteReadOldEntriesInCuChanges.php is run or the cuc_only_for_read_old column is
		// removed, as the script needs the column to be present and needs to be allowed to set the value of the
		// column to 1.
		$updater->addExtensionUpdate( [
			'runMaintenance',
			MoveLogEntriesFromCuChanges::class,
		] );

		// 1.42
		$updater->addExtensionField(
			'cu_log',
			'cul_result_id',
			"$base/$dbType/patch-cu_log-add-cul_result_id.sql"
		);
		if ( $dbType !== 'sqlite' ) {
			$updater->modifyExtensionField(
				'cu_changes',
				'cuc_id',
				"$base/$dbType/patch-cu_changes-modify-cuc_id-bigint.sql"
			);
		}
		$updater->addPostDatabaseUpdateMaintenance( FixTrailingSpacesInLogs::class );
		// If using SQLite and the cupe_private column no longer exists exists, then this update has
		// already been applied and we should skip it entirely because temporary tables used
		// here would fail due to cupe_private no longer being a column
		if (
			$dbType !== 'sqlite' ||
			$maintenanceDb->fieldExists( 'cu_private_event', 'cupe_private', __METHOD__ )
		) {
			$updater->modifyExtensionField(
				'cu_private_event',
				'cupe_actor',
				"$base/$dbType/patch-cu_private_event-modify-cupe_actor-nullable.sql"
			);
		}
		$updater->addExtensionTable( 'cu_useragent', "$base/$dbType/cu_useragent.sql" );
		$updater->addExtensionField(
			'cu_changes',
			'cuc_agent_id',
			"$base/$dbType/patch-cu_changes-add-cuc_agent_id.sql"
		);
		$updater->addExtensionField(
			'cu_log_event',
			'cule_agent_id',
			"$base/$dbType/patch-cu_log_event-add-cule_agent_id.sql"
		);
		$updater->addExtensionField(
			'cu_private_event',
			'cupe_agent_id',
			"$base/$dbType/patch-cu_private_event-add-cupe_agent_id.sql"
		);

		// 1.43
		// Must be run before the removal of the cuc_only_for_read_old column, as the script needs the column to be
		// present to delete the rows where the column value is 1.
		$updater->addExtensionUpdate( [
			'runMaintenance',
			DeleteReadOldRowsInCuChanges::class,
		] );
		$updater->dropExtensionField(
			'cu_changes',
			'cuc_only_for_read_old',
			"$base/$dbType/patch-cu_changes-drop-cuc_only_for_read_old.sql"
		);
		$updater->dropExtensionField(
			'cu_changes',
			'cuc_actiontext',
			"$base/$dbType/patch-cu_changes-drop-cuc_actiontext.sql"
		);
		$updater->dropExtensionField(
			'cu_changes',
			'cuc_private',
			"$base/$dbType/patch-cu_changes-drop-cuc_private.sql"
		);
		if ( $isCUInstalled ) {
			// We only need to run this maintenance script if CU is already installed, because otherwise the script
			// will be run for us by the populateCheckUserTable.php script (after it's populated the tables).
			$updater->addPostDatabaseUpdateMaintenance( PopulateCentralCheckUserIndexTables::class );
		}

		// 1.44
		$updater->addPostDatabaseUpdateMaintenance( MigrateTemporaryAccountIPViewerGroup::class );

		// 1.45
		$updater->addExtensionIndex(
			'cu_changes',
			'cuc_actor_ip_hex_time',
			"$base/$dbType/patch-cu_changes-add-index-cuc_actor_ip_hex_time.sql"
		);
		$updater->addExtensionIndex(
			'cu_log_event',
			'cule_actor_ip_hex_time',
			"$base/$dbType/patch-cu_log_event-add-index-cule_actor_ip_hex_time.sql"
		);
		$updater->addExtensionIndex(
			'cu_private_event',
			'cupe_actor_ip_hex_time',
			"$base/$dbType/patch-cu_private_event-add-index-cupe_actor_ip_hex_time.sql"
		);
		$updater->addExtensionIndex(
			'cu_log',
			'cul_target',
			"$base/$dbType/patch-cu_log-add-index-cul_target.sql"
		);
		$updater->dropExtensionIndex(
			'cu_log',
			'cul_type_target',
			"$base/$dbType/patch-cu_log-drop-index-cul_type_target.sql"
		);
		$updater->addExtensionUpdateOnVirtualDomain( [
			self::VIRTUAL_DB_DOMAIN, 'addTable', 'cusi_case',
			"$base/$dbType/patch-cusi_case-def.sql", true,
		] );
		$updater->addExtensionUpdateOnVirtualDomain( [
			self::VIRTUAL_DB_DOMAIN, 'addTable', 'cusi_user',
			"$base/$dbType/patch-cusi_user-def.sql", true,
		] );
		$updater->addExtensionUpdateOnVirtualDomain( [
			self::VIRTUAL_DB_DOMAIN, 'addTable', 'cusi_signal',
			"$base/$dbType/patch-cusi_signal-def.sql", true,
		] );

		// 1.46
		$updater->addExtensionUpdateOnVirtualDomain( [
			self::VIRTUAL_DB_DOMAIN, 'addField', 'cusi_case', 'sic_url_identifier',
			"$base/$dbType/patch-cusi_case-add-sic_url_identifier.sql", true,
		] );
		$updater->addExtensionUpdateOnVirtualDomain( [
			self::VIRTUAL_DB_DOMAIN, 'runMaintenance', PopulateSicUrlIdentifier::class,
		] );
		$updater->addExtensionUpdateOnVirtualDomain( [
			self::VIRTUAL_DB_DOMAIN, 'addField', 'cusi_signal', 'sis_trigger_id',
			"$base/$dbType/patch-cusi_signal-add-sis_trigger_id.sql", true,
		] );
		$updater->addExtensionUpdateOnVirtualDomain( [
			self::VIRTUAL_DB_DOMAIN, 'modifyTableIfFieldNotExists', 'cusi_case', 'sic_updated_timestamp',
			"$base/$dbType/patch-cusi_case-modify-sic_url_identifier.sql", true,
			'sic_url_identifier',
		] );
		$updater->addExtensionUpdateOnVirtualDomain( [
			self::VIRTUAL_DB_DOMAIN, 'addField', 'cusi_case', 'sic_updated_timestamp',
			"$base/$dbType/patch-cusi_case-add-sic_updated_timestamp.sql", true,
		] );
		$updater->dropExtensionField(
			'cu_private_event',
			'cupe_private',
			"$base/$dbType/patch-cu_private_event-drop-cupe_private.sql"
		);
		$updater->addExtensionUpdate( [
			'runMaintenance',
			PopulateUserAgentTable::class,
		] );
		$updater->addExtensionUpdateOnVirtualDomain( [
			self::VIRTUAL_DB_DOMAIN,
			'runMaintenance',
			PopulateSicUpdatedTimestamp::class,
		] );
		$updater->addPostDatabaseUpdateMaintenance( QueueAutoCloseSICases::class );
		$updater->addExtensionUpdateOnVirtualDomain( [
			self::VIRTUAL_DB_DOMAIN, 'addField', 'cusi_user', 'siu_info',
			"$base/$dbType/patch-cusi_user-add-siu_info.sql", true,
		] );
		$updater->dropExtensionField(
			'cu_private_event',
			'cupe_agent',
			"$base/$dbType/patch-cu_private_event-drop-cupe_agent.sql"
		);
		$updater->dropExtensionField(
			'cu_changes',
			'cuc_agent',
			"$base/$dbType/patch-cu_changes-drop-cuc_agent.sql"
		);
		$updater->dropExtensionField(
			'cu_log_event',
			'cule_agent',
			"$base/$dbType/patch-cu_log_event-drop-cule_agent.sql"
		);
		$updater->dropExtensionField(
			'cu_changes',
			'cuc_ip',
			"$base/$dbType/patch-cu_changes-drop-cuc_ip.sql"
		);
		$updater->dropExtensionField(
			'cu_log_event',
			'cule_ip',
			"$base/$dbType/patch-cu_log_event-drop-cule_ip.sql"
		);
		$updater->dropExtensionField(
			'cu_private_event',
			'cupe_ip',
			"$base/$dbType/patch-cu_private_event-drop-cupe_ip.sql"
		);
		$updater->addExtensionUpdateOnVirtualDomain( [
			self::VIRTUAL_DB_DOMAIN, 'modifyField', 'cusi_case', 'sic_updated_timestamp',
			"$base/$dbType/patch-cusi_case-modify-sic_updated_timestamp-remove_default.sql", true,
		] );
		$updater->addExtensionUpdateOnVirtualDomain( [
			self::VIRTUAL_DB_DOMAIN, 'dropIndex', 'cusi_case', 'sic_created_timestamp_id',
			"$base/$dbType/patch-cusi_case-drop-sic_created_timestamp-indexes.sql", true,
		] );
		$updater->addExtensionUpdateOnVirtualDomain( [
			self::VIRTUAL_DB_DOMAIN, 'addField', 'cusi_case', 'sic_status_changed_by',
			"$base/$dbType/patch-cusi_case-add-sic_status_changed_by.sql", true,
		] );

		if ( !$isCUInstalled ) {
			// First time so populate the CheckUser result tables with recentchanges data.
			// Note: We cannot completely rely on updatelog here for old entries
			// as populateCheckUserTable.php doesn't check for duplicates
			$updater->addPostDatabaseUpdateMaintenance( PopulateCheckUserTable::class );
		}
	}
}
