<?php

namespace MediaWiki\CheckUser\HookHandler;

use MediaWiki\CheckUser\CheckUserQueryInterface;
use MediaWiki\CheckUser\Maintenance\DeleteReadOldRowsInCuChanges;
use MediaWiki\CheckUser\Maintenance\FixTrailingSpacesInLogs;
use MediaWiki\CheckUser\Maintenance\MigrateTemporaryAccountIPViewerGroup;
use MediaWiki\CheckUser\Maintenance\MoveLogEntriesFromCuChanges;
use MediaWiki\CheckUser\Maintenance\PopulateCentralCheckUserIndexTables;
use MediaWiki\CheckUser\Maintenance\PopulateCheckUserTable;
use MediaWiki\CheckUser\Maintenance\PopulateCucActor;
use MediaWiki\CheckUser\Maintenance\PopulateCucComment;
use MediaWiki\CheckUser\Maintenance\PopulateCulActor;
use MediaWiki\CheckUser\Maintenance\PopulateCulComment;
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

		if ( $dbType === 'mysql' ) {
			// 1.35
			$updater->modifyExtensionField(
				'cu_changes',
				'cuc_id',
				"$base/$dbType/patch-cu_changes-cuc_id-unsigned.sql"
			);

			// 1.38
			$updater->addExtensionIndex(
				'cu_changes',
				'cuc_actor_ip_time',
				"$base/$dbType/patch-cu_changes-actor-comment.sql"
			);

			// 1.39
			$updater->modifyExtensionField(
				'cu_changes',
				'cuc_timestamp',
				"$base/$dbType/patch-cu_changes-cuc_timestamp.sql"
			);
			$updater->addExtensionField(
				'cu_log',
				'cul_reason_id',
				"$base/$dbType/patch-cu_log-comment_table_for_reason.sql"
			);
			$updater->addExtensionField(
				'cu_log',
				'cul_actor',
				"$base/$dbType/patch-cu_log-actor.sql"
			);
		} elseif ( $dbType === 'sqlite' ) {
			// 1.39
			$updater->addExtensionIndex(
				'cu_changes',
				'cuc_actor_ip_time',
				"$base/$dbType/patch-cu_changes-actor-comment.sql"
			);
			$updater->addExtensionField(
				'cu_log',
				'cul_reason_id',
				"$base/$dbType/patch-cu_log-comment_table_for_reason.sql"
			);
			$updater->addExtensionField(
				'cu_log',
				'cul_actor',
				"$base/$dbType/patch-cu_log-actor.sql"
			);
		} elseif ( $dbType === 'postgres' ) {
			// 1.37
			$updater->addExtensionUpdate( [ 'dropFkey', 'cu_log', 'cul_user' ] );
			$updater->addExtensionUpdate( [ 'dropFkey', 'cu_log', 'cul_target_id' ] );
			$updater->addExtensionUpdate( [ 'dropFkey', 'cu_changes', 'cuc_user' ] );
			$updater->addExtensionUpdate( [ 'dropFkey', 'cu_changes', 'cuc_page_id' ] );

			// 1.38
			$updater->addExtensionUpdate(
				[ 'addPgField', 'cu_changes', 'cuc_actor', 'INTEGER NOT NULL DEFAULT 0' ]
			);
			$updater->addExtensionUpdate(
				[ 'addPgField', 'cu_changes', 'cuc_comment_id', 'INTEGER NOT NULL DEFAULT 0' ]
			);
			$updater->addExtensionUpdate(
				[ 'setDefault', 'cu_changes', 'cuc_user_text', '' ]
			);
			$updater->addExtensionUpdate(
				[ 'addPgIndex', 'cu_changes', 'cuc_actor_ip_time', '( cuc_actor, cuc_ip, cuc_timestamp )' ]
			);

			// 1.39
			$updater->addExtensionIndex( 'cu_changes', 'cu_changes_pkey', "$base/$dbType/patch-cu_changes-pk.sql" );
			$updater->addExtensionUpdate(
				[ 'changeField', 'cu_changes', 'cuc_namespace', 'INT', 'cuc_namespace::INT DEFAULT 0' ]
			);
			if ( $maintenanceDb->fieldExists( 'cu_log', 'cuc_user', __METHOD__ ) ) {
				$updater->addExtensionUpdate(
					[ 'changeNullableField', 'cu_changes', 'cuc_user', 'NOT NULL', true ]
				);
			}
			if ( $maintenanceDb->fieldExists( 'cu_log', 'cuc_user_text', __METHOD__ ) ) {
				$updater->addExtensionUpdate(
					[ 'changeField', 'cu_changes', 'cuc_user_text', 'VARCHAR(255)', '' ]
				);
				$updater->addExtensionUpdate(
					[ 'setDefault', 'cu_changes', 'cuc_user_text', '' ]
				);
			}
			$updater->addExtensionUpdate(
				[ 'changeField', 'cu_changes', 'cuc_actor', 'BIGINT', 'cuc_actor::BIGINT DEFAULT 0' ]
			);
			$updater->addExtensionUpdate(
				[ 'changeField', 'cu_changes', 'cuc_comment_id', 'BIGINT', 'cuc_comment_id::BIGINT DEFAULT 0' ]
			);
			$updater->addExtensionUpdate(
				[ 'changeField', 'cu_changes', 'cuc_minor', 'SMALLINT', 'cuc_minor::SMALLINT DEFAULT 0' ]
			);
			$updater->addExtensionUpdate(
				[ 'changeNullableField', 'cu_changes', 'cuc_page_id', 'NOT NULL', true ]
			);
			$updater->addExtensionUpdate(
				[ 'setDefault', 'cu_changes', 'cuc_page_id', 0 ]
			);
			$updater->addExtensionUpdate(
				[ 'changeNullableField', 'cu_changes', 'cuc_timestamp', 'NOT NULL', true ]
			);
			$updater->addExtensionUpdate(
				[ 'changeField', 'cu_changes', 'cuc_ip', 'VARCHAR(255)', '' ]
			);
			$updater->addExtensionUpdate(
				[ 'setDefault', 'cu_changes', 'cuc_ip', '' ]
			);
			$updater->addExtensionUpdate(
				[ 'changeField', 'cu_changes', 'cuc_ip_hex', 'VARCHAR(255)', '' ]
			);
			$updater->addExtensionUpdate(
				[ 'setDefault', 'cu_changes', 'cuc_xff', '' ]
			);
			$updater->addExtensionUpdate(
				[ 'changeField', 'cu_changes', 'cuc_xff_hex', 'VARCHAR(255)', '' ]
			);
			if ( $maintenanceDb->fieldExists( 'cu_changes', 'cuc_private', __METHOD__ ) ) {
				$updater->addExtensionUpdate(
					[ 'changeField', 'cu_changes', 'cuc_private', 'TEXT', '' ]
				);
			}
			$updater->addExtensionIndex( 'cu_log', 'cu_log_pkey', "$base/$dbType/patch-cu_log-pk.sql" );
			$updater->addExtensionUpdate(
				[ 'changeNullableField', 'cu_log', 'cul_timestamp', 'NOT NULL', true ]
			);
			if ( $maintenanceDb->fieldExists( 'cu_log', 'cul_user', __METHOD__ ) ) {
				$updater->addExtensionUpdate(
					[ 'changeNullableField', 'cu_log', 'cul_user', 'NOT NULL', true ]
				);
			}
			$updater->addExtensionUpdate(
				[ 'dropDefault', 'cu_log', 'cul_type' ]
			);
			$updater->addExtensionUpdate(
				[ 'changeNullableField', 'cu_log', 'cul_target_id', 'NOT NULL', true ]
			);
			$updater->addExtensionUpdate(
				[ 'setDefault', 'cu_log', 'cul_target_id', 0 ]
			);
			$updater->addExtensionUpdate(
				[ 'dropDefault', 'cu_log', 'cul_target_text' ]
			);
			$updater->addExtensionUpdate(
				[ 'addPgField', 'cu_log', 'cul_reason_id', 'INTEGER NOT NULL DEFAULT 0' ]
			);
			$updater->addExtensionUpdate(
				[ 'addPgField', 'cu_log', 'cul_reason_plaintext_id', 'INTEGER NOT NULL DEFAULT 0' ]
			);
			$updater->addExtensionUpdate(
				[ 'addPgField', 'cu_log', 'cul_actor', 'INTEGER NOT NULL DEFAULT 0' ]
			);
			$updater->addExtensionUpdate(
				[ 'addPgIndex', 'cu_log', 'cul_actor_time', '( cul_actor, cul_timestamp )' ]
			);
		}

		$updater->addExtensionUpdate( [
			'runMaintenance',
			PopulateCulActor::class,
		] );
		$updater->addExtensionUpdate( [
			'runMaintenance',
			PopulateCulComment::class,
		] );
		if ( $dbType === 'postgres' ) {
			# For wikis which ran update.php after pulling the master branch of CheckUser between
			#  4 June 2022 and 6 June 2022, the cul_reason_id and cul_reason_plaintext_id columns
			#  were added but were by default NULL.
			# This is needed for postgres installations that did the above. All other DB types
			#  make the columns "NOT NULL" when removing the default.
			$updater->addExtensionUpdate(
				[ 'changeNullableField', 'cu_log', 'cul_reason_id', 'NOT NULL', true ]
			);
			$updater->addExtensionUpdate(
				[ 'changeNullableField', 'cu_log', 'cul_reason_plaintext_id', 'NOT NULL', true ]
			);
		}

		$updater->addExtensionUpdate( [
			'runMaintenance',
			PopulateCucActor::class,
		] );
		$updater->addExtensionUpdate( [
			'runMaintenance',
			PopulateCucComment::class,
		] );

		// 1.40
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
		// If any columns are modified or removed from cu_private_event in the future, then make sure to only apply this
		// patch if the later schema change has not yet been applied. Otherwise wikis using SQLite will have a DB error.
		$updater->modifyExtensionField(
			'cu_private_event',
			'cupe_actor',
			"$base/$dbType/patch-cu_private_event-modify-cupe_actor-nullable.sql"
		);
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

		if ( !$isCUInstalled ) {
			// First time so populate the CheckUser result tables with recentchanges data.
			// Note: We cannot completely rely on updatelog here for old entries
			// as populateCheckUserTable.php doesn't check for duplicates
			$updater->addPostDatabaseUpdateMaintenance( PopulateCheckUserTable::class );
		}
	}
}
