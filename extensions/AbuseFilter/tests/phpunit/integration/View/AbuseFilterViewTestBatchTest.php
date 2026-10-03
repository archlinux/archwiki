<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Integration\View;

use MediaWiki\Extension\AbuseFilter\ProtectedVarsAccessLogger;
use MediaWiki\Extension\AbuseFilter\ServiceNames;
use MediaWiki\Extension\AbuseFilter\Special\SpecialAbuseFilter;
use MediaWiki\Extension\AbuseFilter\Tests\Integration\AbuseFilterPermissionManagerTestTrait;
use MediaWiki\Extension\AbuseFilter\Tests\Integration\ProtectedVarsTestTrait;
use MediaWiki\Logging\LogPage;
use MediaWiki\Logging\ManualLogEntry;
use MediaWiki\MainConfigNames;
use MediaWiki\Permissions\Authority;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Tests\Specials\SpecialPageTestBase;

/**
 * @group Database
 * @covers \MediaWiki\Extension\AbuseFilter\View\AbuseFilterViewTestBatch
 * @covers \MediaWiki\Extension\AbuseFilter\AbuseFilterChangesList
 *
 * Indirectly covers:
 * @covers \MediaWiki\Extension\AbuseFilter\AbuseFilterPermissionManager
 * @covers \MediaWiki\Extension\AbuseFilter\View\AbuseFilterView
 */
class AbuseFilterViewTestBatchTest extends SpecialPageTestBase {
	use AbuseFilterPermissionManagerTestTrait;
	use ProtectedVarsTestTrait;

	private Authority $authorityCannotUseProtectedVar;
	private Authority $authorityCanUseProtectedVar;
	private static string $userWhoHitFilter;

	/**
	 * @inheritDoc
	 */
	protected function newSpecialPage(): SpecialAbuseFilter {
		$services = $this->getServiceContainer();
		$sp = new SpecialAbuseFilter(
			$services->getService( ServiceNames::PermManager ),
			$services->getObjectFactory()
		);
		$sp->setLinkRenderer(
			$services->getLinkRendererFactory()->create()
		);
		return $sp;
	}

	/**
	 * @inheritDoc
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->clearProtectedVarRelatedHooks();
		$this->authorityCannotUseProtectedVar = $this->mockFilterEditorAuthorityWithoutProtectedVarsAccess();
		$this->authorityCanUseProtectedVar = $this->mockFilterEditorAuthorityWithProtectedVarsAccess();
	}

	/**
	 * @inheritDoc
	 */
	public function addDBDataOnce() {
		$userWhoHitFilter = $this->createFiltersWithProtectedVariables();

		// Create a testing recentchanges table row by creating a logging table row that is sent to recentchanges.
		$logEntry = new ManualLogEntry( 'move', 'move' );
		$logEntry->setPerformer( $userWhoHitFilter );
		$logEntry->setTarget( $this->getExistingTestPage()->getTitle() );
		$logEntry->setComment( 'A very good reason' );
		$logEntry->setParameters( [
			'4::target' => wfRandomString(),
			'5::noredir' => '0'
		] );
		$logId = $logEntry->insert();
		$logEntry->publish( $logId );

		// Check that the recentchanges row for the log entry exists and get the ID for it.
		$recentChangeId = $this->newSelectQueryBuilder()
			->select( 'rc_id' )
			->from( 'recentchanges' )
			->where( [ 'rc_logid' => $logId ] )
			->caller( __METHOD__ )
			->fetchField();
		$this->assertNotFalse( $recentChangeId );
		self::$userWhoHitFilter = $userWhoHitFilter->getName();
	}

	protected function tearDown(): void {
		parent::tearDown();
		$this->dropProtectedVarAccessLogs();
	}

	public function testViewTestBatchProtectedVarsFilterVisibility() {
		// Assert that the user who cannot see protected variables cannot load the filter
		[ $html, ] = $this->executeSpecialPage(
			'test/1',
			performer: $this->authorityCannotUseProtectedVar
		);
		$this->assertStringNotContainsString( '1.2.3.4', $html );

		// Assert that the user who can see protected variables can load the filter
		[ $html, ] = $this->executeSpecialPage(
			'test/1',
			performer: $this->getTestSysop()->getAuthority()
		);
		$this->assertStringContainsString( '1.2.3.4', $html );
	}

	public function testViewTestBatchWhenSubmittedForProtectedFilterButReadOnlyEnabled(): void {
		$this->addCustomProtectedVariableToGenericVars();
		$this->clearHook( 'ChangesListInitRows' );

		$this->getServiceContainer()->getReadOnlyMode()->setReason( 'test' );
		[ $html, ] = $this->executeSpecialPage(
			'test',
			new FauxRequest( [
				'wpFilterRules' => "custom_variable = 'custom_variable_value'",
				'wpTestAction' => 0,
				'wpTestUser' => '',
				'wpTestPeriodStart'	=> '',
				'wpTestPeriodEnd' => '',
				'wpTestPage' => '',
				'wpShowNegative' => 1,
			], true ),
			null,
			$this->authorityCanUseProtectedVar
		);

		$this->assertStringContainsString(
			'(readonlytext: test',
			$html,
			'Read only mode warning should be shown if in read only mode'
		);
	}

	/**
	 * @dataProvider provideIsLogSourceForRCAccessControl
	 */
	public function testViewTestBatchWhenSubmittedAccessControl( bool $isLogSource ) {
		$this->overrideConfigValue( MainConfigNames::PageCreationLog, false );

		$sysop = $this->getTestSysop()->getUser();
		if ( $isLogSource ) {
			$rc = $this->createRCEntryDeleteLog( $sysop );
			$ceil = self::LOG_DELETED_ALL;
			$permSet = self::PERMSET_LOG;
			$action = 'delete';
			$checkAccess = $this->shouldHaveRCEntryAccess( ... );
		} else {
			$rc = $this->createRCEntryEdit( $sysop );
			$ceil = self::REV_DELETED_ALL;
			$permSet = self::PERMSET_REVISION;
			$action = 'edit';
			$checkAccess = $this->shouldHaveRevisionAccess( ... );
		}
		$rcid = (int)$rc->getAttribute( 'rc_id' );

		static $extractChangelist;
		$extractChangelist ??= static function ( string $html ): string {
			$pos = strpos( $html, '<div class="mw-changeslist">' );
			return $pos !== false ? substr( $html, $pos ) : $html;
		};

		for ( $vis = 0; $vis <= $ceil; $vis++ ) {
			if ( $vis === LogPage::DELETED_RESTRICTED ) {
				// This bitfield is always composite in DB
				continue;
			}
			if ( $vis !== 0 ) {
				$this->updateRCEntryVisibility( $vis, $rcid );
			}

			foreach ( $permSet as $label => $perms ) {
				[ $html ] = $this->executeSpecialPage(
					'test',
					new FauxRequest( [
						'wpFilterRules' => "action === '$action'",
						'wpTestAction' => 0,
						'wpTestUser' => $sysop->getName(),
						'wpTestPeriodStart'	=> '',
						'wpTestPeriodEnd' => '',
						'wpTestPage' => $rc->getPage()->getDBkey(),
						'wpShowNegative' => 1,
					], true ),
					'qqx',
					$this->mockFilterEditorAuthorityWithPermissions( $perms )
				);

				$expectedRowVisibility = $this->expectRCRowVisibility( $vis, $perms );
				$rowCount = (int)preg_match_all( '/\bmw-changeslist-line(?!-)/', $html );
				$this->assertSame(
					(int)$expectedRowVisibility, $rowCount,
					$this->formatVisibilityError( $vis, $label ) .
						", expectedRowVisibility: $expectedRowVisibility\n" . $extractChangelist( $html )
				);

				if ( !$rowCount ) {
					continue;
				}

				$expectedExamineLinks = (int)$checkAccess( $vis, $perms );
				$actualExamineLinks = substr_count( $html, '(abusefilter-changeslist-examine)' );
				$this->assertSame(
					$expectedExamineLinks, $actualExamineLinks,
					$this->formatVisibilityError( $vis, $label ) .
						"\nExpected $expectedExamineLinks \"examine\" link(s), but got $actualExamineLinks\n" .
						$extractChangelist( $html )
				);

				$expectedHiddenWarning = (int)( $vis !== 0 );
				$actualHiddenWarning = substr_count( $html, '(abusefilter-log-hidden-implicit)' );
				$this->assertSame(
					$expectedHiddenWarning, $actualHiddenWarning,
					$this->formatVisibilityError( $vis, $label ) .
						"\nExpected $expectedHiddenWarning \"hidden-implicit\" message(s)\n" .
						$extractChangelist( $html )
				);
			}
		}
	}

	public static function provideIsLogSourceForRCAccessControl() {
		return [
			'Access control for a RecentChange::SRC_LOG entry' => [ true ],
			'Access control for a non-RecentChange::SRC_LOG entry' => [ false ],
		];
	}

	public function testViewTestBatchWhenSubmittedForProtectedFilter() {
		$this->addCustomProtectedVariableToGenericVars();

		// Assert that the user who can see protected variables can submit the form for a protected filter
		// and that this submission causes protected variable access logs to be created
		[ $html, ] = $this->executeSpecialPage(
			'test',
			new FauxRequest( [
				'wpFilterRules' => "custom_variable = 'custom_variable_value'",
				'wpTestAction' => 0,
				'wpTestUser' => '',
				'wpTestPeriodStart'	=> '',
				'wpTestPeriodEnd' => '',
				'wpTestPage' => '',
				'wpShowNegative' => 1,
			], true ),
			null,
			$this->authorityCanUseProtectedVar
		);

		$this->assertStringContainsString( 'custom_variable_value', $html );

		// Verify that a protected variable access log was created as protected variable values were viewed.
		$this->assertProtectedVariableAccessLogExists(
			$this->authorityCanUseProtectedVar->getUser(), self::$userWhoHitFilter, [ 'custom_variable' ]
		);
	}

	public function testViewTestBatchWhenSubmittedWithAllNullProtectedValues() {
		$this->addCustomProtectedVariableToGenericVars( null );

		// Assert that the user who can see protected variables can submit the form for a protected filter
		// and that this submission causes protected variable access logs to be created
		[ $html, ] = $this->executeSpecialPage(
			'test',
			new FauxRequest( [
				'wpFilterRules' => "custom_variable = 'custom_variable_value'",
				'wpTestAction' => 0,
				'wpTestUser' => '',
				'wpTestPeriodStart'	=> '',
				'wpTestPeriodEnd' => '',
				'wpTestPage' => '',
				'wpShowNegative' => 1,
			], true ),
			null,
			$this->authorityCanUseProtectedVar
		);

		$this->assertStringContainsString( 'custom_variable_value', $html );

		// Verify that a protected variable access log was not created created
		// as the value was null and so nothing was viewed that was protected
		$this->newSelectQueryBuilder()
			->select( '1' )
			->from( 'logging' )
			->join( 'actor', null, 'actor_id=log_actor' )
			->where( [
				'log_action' => 'view-protected-var-value',
				'log_type' => ProtectedVarsAccessLogger::LOG_TYPE,
			] )
			->caller( __METHOD__ )
			->assertEmptyResult();
	}
}
