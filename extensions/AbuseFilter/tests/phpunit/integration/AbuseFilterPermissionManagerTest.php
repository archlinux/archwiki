<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Integration;

use MediaWiki\Extension\AbuseFilter\AbuseFilterPermissionManager;
use MediaWiki\Extension\AbuseFilter\Filter\MutableFilter;
use MediaWiki\RecentChanges\RecentChange;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\Tests\User\TempUser\TempUserTestTrait;
use MediaWikiIntegrationTestCase;
use StatusValue;

/**
 * @covers \MediaWiki\Extension\AbuseFilter\AbuseFilterPermissionManager
 */
class AbuseFilterPermissionManagerTest extends MediaWikiIntegrationTestCase {
	use AbuseFilterPermissionManagerTestTrait;
	use MockAuthorityTrait;
	use TempUserTestTrait;

	private function getPermissionManager() {
		return $this->getServiceContainer()->get( AbuseFilterPermissionManager::SERVICE_NAME );
	}

	public function testCanViewProtectedVariablesInFilterWhenHookDisallows() {
		// Define the AbuseFilterCanViewProtectedVariables hook to make the status a fatal status
		$this->setTemporaryHook(
			'AbuseFilterCanViewProtectedVariables',
			static function ( $performer, $variables, StatusValue $status ) {
				$status->fatal( 'test-error' );
			}
		);

		$filter = MutableFilter::newDefault();
		$filter->setProtected( true );
		/** @var AbuseFilterPermissionManager $permissionManager */
		$permissionManager = $this->getPermissionManager();
		$actualStatus = $permissionManager->canViewProtectedVariablesInFilter(
			$this->mockRegisteredUltimateAuthority(),
			$filter
		);

		$this->assertStatusError( 'test-error', $actualStatus );
	}

	/** @dataProvider provideCanSeeIPForFilterLog */
	public function testCanSeeIPForFilterLog(
		bool $canViewTempIPs,
		bool $canSeeLogDetails,
		bool $logUserIsTemp,
		bool $expected,
		bool $withCheckUser = false,
	) {
		if ( $withCheckUser ) {
			$this->markTestSkippedIfExtensionNotLoaded( 'CheckUser' );
		}

		$this->enableAutoCreateTempUser();

		$permissions = [];
		if ( $canViewTempIPs ) {
			$permissions[] = 'checkuser-temporary-account-no-preference';
		}
		if ( $canSeeLogDetails ) {
			$permissions[] = 'abusefilter-log-detail';
		}
		$performer = $this->mockRegisteredAuthorityWithPermissions( $permissions );
		$filter = MutableFilter::newDefault();
		$userName = $logUserIsTemp ? '~12345' : 'Test';

		// Mock ExtensionRegistry service to say whether the CheckUser extension is loaded,
		// so we can mock it isn't loaded when it actually is
		$extensionRegistry = $this->createMock( ExtensionRegistry::class );
		$extensionRegistry->method( 'isLoaded' )
			->willReturn( $withCheckUser );
		$this->setService( 'ExtensionRegistry', $extensionRegistry );

		/** @var AbuseFilterPermissionManager $permissionManager */
		$permissionManager = $this->getPermissionManager();

		$this->assertSame(
			$expected,
			$permissionManager->CanSeeIPForFilterLog( $performer, $filter, $userName )
		);
	}

	public static function provideCanSeeIPForFilterLog() {
		return [
			'Has all permissions' => [
				'canViewTempIPs' => true,
				'canSeeLogDetails' => true,
				'logUserIsTemp' => true,
				'expected' => true,
			],
			'Only has IP viewer permissions, temp account log (with CheckUser)' => [
				'canViewTempIPs' => true,
				'canSeeLogDetails' => false,
				'logUserIsTemp' => true,
				'expected' => true,
				'withCheckUser' => true,
			],
			'Only has IP viewer permissions, temp account log (without CheckUser)' => [
				'canViewTempIPs' => true,
				'canSeeLogDetails' => false,
				'logUserIsTemp' => true,
				'expected' => false,
				'withCheckUser' => false,
			],
			'Only has IP viewer permissions, non-temp account log' => [
				'canViewTempIPs' => true,
				'canSeeLogDetails' => false,
				'logUserIsTemp' => false,
				'expected' => false,
			],
			'Has no permissions, temp account log' => [
				'canViewTempIPs' => false,
				'canSeeLogDetails' => false,
				'logUserIsTemp' => true,
				'expected' => false,
			],
		];
	}

	/**
	 * @todo This should be put in the corresponding unit test,
	 * but the test trait requires DB
	 */
	public function testHasRevisionAccessBruteForce() {
		for ( $visibility = 0; $visibility <= self::REV_DELETED_ALL; $visibility++ ) {
			foreach ( self::PERMSET_REVISION as $label => $perms ) {
				$authority = $this->mockFilterEditorAuthorityWithPermissions( $perms );
				$this->assertSame(
					$this->shouldHaveRevisionAccess( $visibility, $perms ),
					AbuseFilterPermissionManager::hasRevisionAccess( $visibility, $authority ),
					$this->formatVisibilityError( $visibility, $label )
				);
			}
		}
	}

	/**
	 * @todo This should be put in the corresponding unit test,
	 * but the test trait requires DB
	 */
	public function testHasRCEntryAccessBruteForce() {
		for ( $visibility = 0; $visibility <= self::LOG_DELETED_ALL; $visibility++ ) {
			foreach ( self::PERMSET_LOG as $label => $perms ) {
				$rc = $this->createMock( RecentChange::class );
				$rc->method( 'getAttribute' )->willReturnMap( [
					[ 'rc_source', RecentChange::SRC_LOG ],
					[ 'rc_deleted', $visibility ],
				] );

				$authority = $this->mockFilterEditorAuthorityWithPermissions( $perms );
				$this->assertSame(
					$this->shouldHaveRCEntryAccess( $visibility, $perms ),
					AbuseFilterPermissionManager::hasRCEntryAccess( $rc, $authority ),
					$this->formatVisibilityError( $visibility, $label )
				);
			}
		}
	}

}
