<?php

namespace MediaWiki\Extension\AbuseFilter\Hooks\Handlers;

use DatabaseUpdater;
use MediaWiki\Extension\AbuseFilter\Maintenance\FixOldLogEntries;
use MediaWiki\Extension\AbuseFilter\Maintenance\MigrateActorsAF;
use MediaWiki\Extension\AbuseFilter\Maintenance\NormalizeThrottleParameters;
use MediaWiki\Extension\AbuseFilter\Maintenance\UpdateVarDumps;
use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;
use MediaWiki\MediaWikiServices;
use MediaWiki\User\UserGroupManager;
use MessageLocalizer;
use RequestContext;
use User;

class SchemaChangesHandler implements LoadExtensionSchemaUpdatesHook {
	/** @var MessageLocalizer */
	private $messageLocalizer;
	/** @var UserGroupManager */
	private $userGroupManager;

	/**
	 * @param MessageLocalizer $messageLocalizer
	 * @param UserGroupManager $userGroupManager
	 */
	public function __construct( MessageLocalizer $messageLocalizer, UserGroupManager $userGroupManager ) {
		$this->messageLocalizer = $messageLocalizer;
		$this->userGroupManager = $userGroupManager;
	}

	/**
	 * @note The hook doesn't allow injecting services!
	 * @codeCoverageIgnore
	 * @return self
	 */
	public static function newFromGlobalState(): self {
		return new self(
			// @todo Use a proper MessageLocalizer once available (T247127)
			RequestContext::getMain(),
			MediaWikiServices::getInstance()->getUserGroupManager()
		);
	}

	/**
	 * @codeCoverageIgnore This is tested by installing or updating MediaWiki
	 * @param DatabaseUpdater $updater
	 */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		global $wgAbuseFilterActorTableSchemaMigrationStage;

		$dbType = $updater->getDB()->getType();
		$dir = __DIR__ . "/../../../db_patches";

		$updater->addExtensionTable(
			'abuse_filter',
			"$dir/$dbType/tables-generated.sql"
		);

		if ( $dbType === 'mysql' || $dbType === 'sqlite' ) {
			$updater->dropExtensionField(
				'abuse_filter_log',
				'afl_log_id',
				"$dir/$dbType/patch-drop_afl_log_id.sql"
			);

			$updater->addExtensionField(
				'abuse_filter_log',
				'afl_filter_id',
				"$dir/$dbType/patch-split-afl_filter.sql"
			);

			if ( $dbType === 'mysql' ) {
				$updater->renameExtensionIndex(
					'abuse_filter_log',
					'ip_timestamp',
					'afl_ip_timestamp',
					"$dir/mysql/patch-rename-indexes.sql",
					true
				);
				// This one has its own files because apparently, sometimes this particular index can already
				// have the correct name (T291725)
				$updater->renameExtensionIndex(
					'abuse_filter_log',
					'wiki_timestamp',
					'afl_wiki_timestamp',
					"$dir/mysql/patch-rename-wiki-timestamp-index.sql",
					true
				);
				// This one is also separate to avoid interferences with the afl_filter field removal below.
				$updater->renameExtensionIndex(
					'abuse_filter_log',
					'filter_timestamp',
					'afl_filter_timestamp',
					"$dir/mysql/patch-rename-filter_timestamp-index.sql",
					true
				);
			}
			$updater->dropExtensionField(
				'abuse_filter_log',
				'afl_filter',
				"$dir/$dbType/patch-remove-afl_filter.sql"
			);
		} elseif ( $dbType === 'postgres' ) {
			$updater->addExtensionUpdate( [
				'dropPgField', 'abuse_filter_log', 'afl_log_id' ] );
			$updater->addExtensionUpdate( [
				'setDefault', 'abuse_filter_log', 'afl_filter', ''
			] );
			$updater->addExtensionUpdate( [
				'addPgField', 'abuse_filter_log', 'afl_global', 'SMALLINT NOT NULL DEFAULT 0'
			] );
			$updater->addExtensionUpdate( [
				'addPgField', 'abuse_filter_log', 'afl_filter_id', 'INTEGER NOT NULL DEFAULT 0'
			] );
			$updater->addExtensionUpdate( [
				'addPgIndex', 'abuse_filter_log', 'abuse_filter_log_filter_timestamp_full',
				'(afl_global, afl_filter_id, afl_timestamp)'
			] );
			$updater->addExtensionUpdate( [
				'dropPgIndex', 'abuse_filter_log', 'abuse_filter_log_timestamp'
			] );
			$updater->addExtensionUpdate( [
				'dropPgField', 'abuse_filter_log', 'afl_filter'
			] );
			$updater->addExtensionUpdate( [
				'dropDefault', 'abuse_filter_log', 'afl_filter_id'
			] );
			$updater->addExtensionUpdate( [
				'dropDefault', 'abuse_filter_log', 'afl_global'
			] );
			$updater->addExtensionUpdate( [
				'renameIndex', 'abuse_filter', 'abuse_filter_user', 'af_user'
			] );
			$updater->addExtensionUpdate( [
				'renameIndex', 'abuse_filter', 'abuse_filter_group_enabled_id', 'af_group_enabled'
			] );
			$updater->addExtensionUpdate( [
				'renameIndex', 'abuse_filter_action', 'abuse_filter_action_consequence', 'afa_consequence'
			] );
			$updater->addExtensionUpdate( [
				'renameIndex', 'abuse_filter_log', 'abuse_filter_log_filter_timestamp_full', 'afl_filter_timestamp_full'
			] );
			$updater->addExtensionUpdate( [
				'renameIndex', 'abuse_filter_log', 'abuse_filter_log_user_timestamp', 'afl_user_timestamp'
			] );
			$updater->addExtensionUpdate( [
				'renameIndex', 'abuse_filter_log', 'abuse_filter_log_timestamp', 'afl_timestamp'
			] );
			$updater->addExtensionUpdate( [
				'renameIndex', 'abuse_filter_log', 'abuse_filter_log_page_timestamp', 'afl_page_timestamp'
			] );
			$updater->addExtensionUpdate( [
				'renameIndex', 'abuse_filter_log', 'abuse_filter_log_ip_timestamp', 'afl_ip_timestamp'
			] );
			$updater->addExtensionUpdate( [
				'renameIndex', 'abuse_filter_log', 'abuse_filter_log_rev_id', 'afl_rev_id'
			] );
			$updater->addExtensionUpdate( [
				'renameIndex', 'abuse_filter_log', 'abuse_filter_log_wiki_timestamp', 'afl_wiki_timestamp'
			] );
			$updater->addExtensionUpdate( [
				'renameIndex', 'abuse_filter_history', 'abuse_filter_history_filter', 'afh_filter'
			] );
			$updater->addExtensionUpdate( [
				'renameIndex', 'abuse_filter_history', 'abuse_filter_history_user', 'afh_user'
			] );
			$updater->addExtensionUpdate( [
				'renameIndex', 'abuse_filter_history', 'abuse_filter_history_user_text', 'afh_user_text'
			] );
			$updater->addExtensionUpdate( [
				'renameIndex', 'abuse_filter_history', 'abuse_filter_history_timestamp', 'afh_timestamp'
			] );
			$updater->addExtensionUpdate( [
				'changeNullableField', ' abuse_filter_history', 'afh_public_comments', 'NULL', true
			] );
			$updater->addExtensionUpdate( [
				'changeNullableField', ' abuse_filter_history', 'afh_actions', 'NULL', true
			] );
		}

		$updater->addExtensionUpdate( [
			'addField', 'abuse_filter', 'af_actor',
			"$dir/$dbType/patch-add-af_actor.sql", true
		] );

		$updater->addExtensionUpdate( [
			'addField', 'abuse_filter_history', 'afh_actor',
			"$dir/$dbType/patch-add-afh_actor.sql", true
		] );

		$updater->addExtensionUpdate( [ [ $this, 'createAbuseFilterUser' ] ] );
		$updater->addPostDatabaseUpdateMaintenance( NormalizeThrottleParameters::class );
		$updater->addPostDatabaseUpdateMaintenance( FixOldLogEntries::class );
		$updater->addPostDatabaseUpdateMaintenance( UpdateVarDumps::class );
		// Don't launch the script on update.php if the migration stage is not high enough.
		// This would throw an exception.
		// Also check if the global is set.
		// If globals aren't loaded, it's install.php, and not update.php. This is intentional,
		// see for instance T193855 or T198331.
		if ( isset( $wgAbuseFilterActorTableSchemaMigrationStage ) &&
			( $wgAbuseFilterActorTableSchemaMigrationStage & SCHEMA_COMPAT_WRITE_NEW )
		) {
			$updater->addPostDatabaseUpdateMaintenance( MigrateActorsAF::class );
		}
	}

	/**
	 * Updater callback to create the AbuseFilter user after the user tables have been updated.
	 * @param DatabaseUpdater $updater
	 * @return bool
	 */
	public function createAbuseFilterUser( DatabaseUpdater $updater ): bool {
		$username = $this->messageLocalizer->msg( 'abusefilter-blocker' )->inContentLanguage()->text();
		$user = User::newFromName( $username );

		if ( $user && !$updater->updateRowExists( 'create abusefilter-blocker-user' ) ) {
			$user = User::newSystemUser( $username, [ 'steal' => true ] );
			$updater->insertUpdateRow( 'create abusefilter-blocker-user' );
			// Promote user so it doesn't look too crazy.
			$this->userGroupManager->addUserToGroup( $user, 'sysop' );
			return true;
		}
		return false;
	}
}
