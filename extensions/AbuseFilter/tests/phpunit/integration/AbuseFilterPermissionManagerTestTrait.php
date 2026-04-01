<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Integration;

use MediaWiki\Content\ContentHandler;
use MediaWiki\Logging\LogPage;
use MediaWiki\Logging\ManualLogEntry;
use MediaWiki\Permissions\Authority;
use MediaWiki\Permissions\SimpleAuthority;
use MediaWiki\RecentChanges\RecentChange;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use Wikimedia\Rdbms\IDatabase;

/**
 * Helper assertions for testing the access control logic in
 * AbuseFilterPermissionManager::hasRevisionAccess and ::hasRCEntryAccess.
 *
 * XXX: This trait relies on rc_deleted overloading log_deleted and rev_deleted semantics.
 */
trait AbuseFilterPermissionManagerTestTrait {

	/**
	 * @see MediaWikiIntegrationTestCase::getDb
	 * @return IDatabase
	 */
	abstract protected function getDb();

	/** @var array<string,string[]> */
	private const PERMSET_LOG = [
		'none' => [],
		'history' => [ 'deletedhistory' ],
		'suppressrevision' => [ 'suppressrevision' ],
		'viewsuppressed' => [ 'viewsuppressed' ],
		'suppress+view' => [ 'suppressrevision', 'viewsuppressed' ],
		'all' => [ 'deletedhistory', 'suppressrevision', 'viewsuppressed', ],
	];
	/** @var array<string,string[]> */
	private const PERMSET_REVISION = [
		'none' => [],
		'text' => [ 'deletedtext' ],
		'history' => [ 'deletedhistory' ],
		'text+history' => [ 'deletedtext', 'deletedhistory' ],
		'suppressrevision' => [ 'suppressrevision' ],
		'viewsuppressed' => [ 'viewsuppressed' ],
		'suppress+view' => [ 'suppressrevision', 'viewsuppressed' ],
		'all' => [ 'deletedtext', 'deletedhistory', 'suppressrevision', 'viewsuppressed', ],
	];
	/** @var int 15 */
	private const LOG_DELETED_ALL = LogPage::DELETED_ACTION | LogPage::DELETED_COMMENT
		| LogPage::DELETED_USER | LogPage::DELETED_RESTRICTED;
	/** @var int 15 */
	private const REV_DELETED_ALL = RevisionRecord::DELETED_TEXT | RevisionRecord::DELETED_COMMENT
		| RevisionRecord::DELETED_USER | RevisionRecord::DELETED_RESTRICTED;
	/** @var int 0 */
	private const DELETED_ANY = 0;

	/**
	 * Create a mock Authority for a edit filter manager with $permissions.
	 *
	 * @param string[] $permissions
	 * @return Authority
	 */
	private static function mockFilterEditorAuthorityWithPermissions( array $permissions = [] ): Authority {
		$perms = array_merge( $permissions, [ 'abusefilter-modify' ] );
		return new SimpleAuthority( new UserIdentityValue( 7777, 'AbuseFilterManager' ), $perms );
	}

	/**
	 * Compute the expected revision visibility outcome for a given permission set.
	 *
	 * This method mirrors the intended behavior of AbuseFilterPermissionManager::hasRevisionAccess.
	 *
	 * @param int $visibility rev_deleted bitfield
	 * @param string[] $perms User permissions
	 * @return bool
	 * @see AbuseFilterPermissionManager::hasRevisionAccess
	 */
	private static function shouldHaveRevisionAccess( int $visibility, array $perms ): bool {
		if ( !$visibility ) {
			return true;
		}
		if ( $visibility & RevisionRecord::DELETED_RESTRICTED ) {
			return in_array( 'suppressrevision', $perms, true )
				|| in_array( 'viewsuppressed', $perms, true );
		}
		if ( ( $visibility & RevisionRecord::DELETED_TEXT ) &&
			!in_array( 'deletedtext', $perms, true )
		) {
			return false;
		}
		if ( ( $visibility & ( RevisionRecord::DELETED_COMMENT | RevisionRecord::DELETED_USER ) ) &&
			!in_array( 'deletedhistory', $perms, true )
		) {
			return false;
		}
		return true;
	}

	/**
	 * Compute the expected recent change entry visibility for log-source entries.
	 *
	 * This method mirrors the intended behavior of AbuseFilterPermissionManager::hasRCEntryAccess
	 * for entries whose source is RecentChange::SRC_LOG.
	 *
	 * @param int $visibility rc_deleted bitfield
	 * @param string[] $perms User permissions
	 * @return bool
	 * @see AbuseFilterPermissionManager::hasRCEntryAccess
	 */
	private static function shouldHaveRCEntryAccess( int $visibility, array $perms ): bool {
		if ( !$visibility ) {
			return true;
		}
		if ( $visibility & LogPage::DELETED_RESTRICTED ) {
			return in_array( 'suppressrevision', $perms, true )
				|| in_array( 'viewsuppressed', $perms, true );
		}
		return in_array( 'deletedhistory', $perms, true );
	}

	private static function formatVisibilityError( int $visibility, string $permissionLabel ): string {
		return sprintf( 'visibility=%04b (%d), perms=%s', $visibility, $visibility, $permissionLabel );
	}

	/**
	 * This method mirrors the filtering logic in
	 * AbuseFilterView::buildVisibilityConditions.
	 *
	 * Given an RC row visibility bitfield and a permission set, returns whether
	 * the row is expected to be visible to the user.
	 *
	 * @param int $visibility rc_deleted, rev_deleted, or log_deleted bitfield
	 * @param string[] $perms User permissions
	 * @return bool
	 * @see AbuseFilterView::buildVisibilityConditions
	 */
	private static function expectRCRowVisibility( int $visibility, array $perms ): bool {
		if ( !in_array( 'deletedhistory', $perms, true ) ) {
			$bitmask = RevisionRecord::DELETED_USER;
		} elseif ( !array_intersect( [ 'suppressrevision', 'viewsuppressed' ], $perms ) ) {
			$bitmask = RevisionRecord::DELETED_USER | RevisionRecord::DELETED_RESTRICTED;
		} else {
			return true;
		}
		return ( $visibility & $bitmask ) !== $bitmask;
	}

	/**
	 * Check whether an RC, revision, or log field is deleted.
	 *
	 * By default, this excludes suppressed fields. To include suppressed fields,
	 * pass `$excludeSuppressed = false`. To check specifically for suppression,
	 * use ::isFieldSuppressed instead.
	 *
	 * Passing self::DELETED_ANY checks whether *any* aspect is deleted.
	 *
	 * @param int $visibility rc_deleted, rev_deleted, or log_deleted bitfield
	 * @param int $field One of the visibility constants:
	 * - DELETED_ACTION (= DELETED_TEXT)
	 * - DELETED_COMMENT
	 * - DELETED_USER
	 * - self::DELETED_ANY (any deleted aspect)
	 *   - NOTE: When $excludeSuppressed is true, this returns false if *any*
	 *     aspect is suppressed.
	 * @param bool $excludeSuppressed
	 * @return bool
	 * @see RevisionRecord
	 * @see LogPage
	 */
	private static function isFieldDeleted( int $visibility, int $field, bool $excludeSuppressed = true ): bool {
		if ( $field === self::DELETED_ANY ) {
			$field = LogPage::DELETED_ACTION | LogPage::DELETED_COMMENT | LogPage::DELETED_USER;
		} else {
			// 0b111 = 7
			self::assertFieldInputValid( $field, 0b111 );
		}
		return (bool)(
			( $visibility & $field ) &&
			( !$excludeSuppressed || !( $visibility & LogPage::DELETED_RESTRICTED ) )
		);
	}

	/**
	 * Check whether an RC, revision, or log field is suppressed.
	 *
	 * Passing self::DELETED_ANY checks whether *any* aspect is suppressed.
	 *
	 * @param int $visibility rc_deleted, rev_deleted, or log_deleted bitfield
	 * @param int $field One of the visibility constants:
	 * - DELETED_ACTION (= DELETED_TEXT)
	 * - DELETED_COMMENT
	 * - DELETED_USER
	 * - self::DELETED_ANY (any suppressed aspect)
	 * @return bool
	 * @see RevisionRecord
	 * @see LogPage
	 */
	private static function isFieldSuppressed( int $visibility, int $field ): bool {
		if ( $field === self::DELETED_ANY ) {
			return (bool)( $visibility & LogPage::DELETED_RESTRICTED );
		}
		// 0b111 = 7
		self::assertFieldInputValid( $field, 0b111 );
		return ( $visibility & LogPage::DELETED_RESTRICTED ) && (bool)( $visibility & $field );
	}

	/** @internal */
	private static function assertFieldInputValid( int $field, int $ceil ) {
		// Disallow any composite bitmask. For example, if $ceil = 0b111 = 7,
		// this only allows 1, 2, or 4 as $field
		if ( !( $field & $ceil ) || ( $field & ( $field - 1 ) ) ) {
			self::fail( 'Invalid $field value' );
		}
	}

	/**
	 * Create a delete log for the RC feed.
	 *
	 * @param UserIdentity|null $deleter Optional deleter identity
	 * @return RecentChange
	 */
	private function createRCEntryDeleteLog( ?UserIdentity $deleter = null ): RecentChange {
		// Create a log entry for an action that both appears in the RC feed
		// and is supported by AbuseFilter
		$logEntry = new ManualLogEntry( 'delete', 'delete' );
		$logEntry->setPerformer( $deleter ?? $this->getTestSysop()->getUserIdentity() );
		$logEntry->setTarget( $this->getNonExistingTestPage()->getTitle() );
		$logEntry->setComment( 'A very good reason' );
		$logId = $logEntry->insert();
		$logEntry->publish( $logId );

		$rc = $this->getServiceContainer()->getRecentChangeStore()->getRecentChangeByConds(
			[ 'rc_logid' => $logId ], __METHOD__
		);
		$this->assertNotNull( $rc, 'Failed to retrieve an RC entry' );

		return $rc;
	}

	/**
	 * Make an edit for the RC feed.
	 *
	 * The edit will create a new page. If no page creation log is required,
	 * set MainConfigNames::PageCreationLog to false before calling this method.
	 *
	 * @param UserIdentity|null $editor Optional editor identity
	 * @return RecentChange
	 */
	private function createRCEntryEdit( ?UserIdentity $editor = null ): RecentChange {
		// Make an edit for the RC feed
		$services = $this->getServiceContainer();
		$title = $this->getNonExistingTestPage()->getTitle();
		$revRec = $services->getPageUpdaterFactory()->newPageUpdater(
			$title, $editor ?? $this->getTestSysop()->getUserIdentity()
		)
		->setContent( SlotRecord::MAIN, ContentHandler::makeContent( 'blahblahblah', $title ) )
		->saveRevision( '+', EDIT_NEW );
		$this->assertNotNull( $revRec, 'Failed to save revision' );

		$page = $revRec->getPage();
		$rc = $services->getRecentChangeStore()->getRecentChangeByConds(
			[
				'rc_namespace' => $page->getNamespace(),
				'rc_title' => $page->getDBkey()
			], __METHOD__
		);
		$this->assertNotNull( $rc, 'Failed to retrieve an RC entry' );

		return $rc;
	}

	/**
	 * Set a value to the `rc_deleted` field in the `recentchanges` table.
	 *
	 * This method directly issues an UPDATE query without any sub-operations.
	 *
	 * @param int $visibility
	 * @param int $rcid
	 * @return void
	 */
	private function updateRCEntryVisibility( int $visibility, int $rcid ): void {
		$this->getDb()->newUpdateQueryBuilder()
			->table( 'recentchanges' )
			->set( [ 'rc_deleted' => $visibility ] )
			->where( [ 'rc_id' => $rcid ] )
			->caller( __METHOD__ )
			->execute();
	}

}
