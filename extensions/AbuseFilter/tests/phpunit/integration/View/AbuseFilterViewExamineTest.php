<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Integration\View;

use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Extension\AbuseFilter\AbuseFilterPermissionStatus;
use MediaWiki\Extension\AbuseFilter\CentralDBNotAvailableException;
use MediaWiki\Extension\AbuseFilter\FilterLookup;
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
use Wikimedia\Parsoid\Ext\DOMUtils;

/**
 * @group Database
 * @covers \MediaWiki\Extension\AbuseFilter\View\AbuseFilterViewExamine
 *
 * Indirectly covers:
 * @covers \MediaWiki\Extension\AbuseFilter\AbuseFilterPermissionManager
 * @covers \MediaWiki\Extension\AbuseFilter\View\AbuseFilterView
 * @covers \MediaWiki\Extension\AbuseFilter\Special\AbuseFilterSpecialPage
 * @covers \MediaWiki\Extension\AbuseFilter\Special\SpecialAbuseFilter
 */
class AbuseFilterViewExamineTest extends SpecialPageTestBase {
	use AbuseFilterPermissionManagerTestTrait;
	use ProtectedVarsTestTrait;

	private Authority $authorityCannotUseProtectedVar;
	private Authority $authorityCanUseProtectedVar;
	private static int $recentChangeId;
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
		self::$recentChangeId = $recentChangeId;
		self::$userWhoHitFilter = $userWhoHitFilter->getName();
	}

	private function verifyHasExamineIntroMessage( string $html ) {
		$this->assertStringContainsString(
			'(abusefilter-examine-intro', $html, 'Missing examine explainer message'
		);
	}

	protected function tearDown(): void {
		parent::tearDown();
		$this->dropProtectedVarAccessLogs();
	}

	public function testViewExamineForLogEntryWithMissingId() {
		[ $html, ] = $this->executeSpecialPage(
			'examine/log/1234',
			new FauxRequest(),
			null,
			$this->authorityCannotUseProtectedVar
		);

		$this->verifyHasExamineIntroMessage( $html );
		$this->assertStringContainsString(
			'(abusefilter-examine-notfound)',
			$html,
			'Missing error message for unknown AbuseLog ID.'
		);
	}

	public function testViewExamineForLogEntryWhereUserCannotSeeTheFilter() {
		[ $html, ] = $this->executeSpecialPage(
			'examine/log/1',
			new FauxRequest(),
			null,
			$this->authorityCannotUseProtectedVar
		);

		$this->verifyHasExamineIntroMessage( $html );
		$this->assertStringContainsString(
			'(abusefilter-log-cannot-see-details)',
			$html,
			'Missing protected filter access error.'
		);
	}

	public function testViewExamineForLogEntryWhereUserCannotSeeSpecificProtectedVariableDueToPermission() {
		// Mock that all users lack access to user_unnamed_ip only, so we can test denying access based on the
		// protected variables that are present in the log.
		$this->setTemporaryHook(
			'AbuseFilterCanViewProtectedVariables',
			static function ( Authority $performer, array $variables, AbuseFilterPermissionStatus $returnStatus ) {
				if ( in_array( 'user_unnamed_ip', $variables ) ) {
					$returnStatus->setPermission( 'test-permission' );
				}
			}
		);

		[ $html, ] = $this->executeSpecialPage( 'examine/log/1', performer: $this->authorityCanUseProtectedVar );

		$this->verifyHasExamineIntroMessage( $html );
		$this->assertStringContainsString(
			'(abusefilter-examine-error-protected-due-to-permission: (action-test-permission))',
			$html,
			'Missing protected filter access error.'
		);
	}

	public function testViewExamineForLogEntryWhereUserCannotSeeSpecificProtectedVariable() {
		// Mock that all users lack access to user_unnamed_ip only, so we can test denying access based on the
		// protected variables that are present in the log.
		$this->setTemporaryHook(
			'AbuseFilterCanViewProtectedVariables',
			static function ( Authority $performer, array $variables, AbuseFilterPermissionStatus $returnStatus ) {
				if ( in_array( 'user_unnamed_ip', $variables ) ) {
					$returnStatus->fatal( 'test' );
				}
			}
		);

		[ $html, ] = $this->executeSpecialPage(
			'examine/log/1',
			performer: $this->authorityCanUseProtectedVar
		);

		$this->verifyHasExamineIntroMessage( $html );
		$this->assertStringContainsString(
			'(abusefilter-examine-error-protected:',
			$html,
			'Missing protected filter access error.'
		);
	}

	public function testViewExamineForLogEntryWhenFilterIsGlobalAndGlobalFiltersHaveBeenDisabled() {
		// Mock FilterLookup::getFilter to throw a CentralDBNotAvailableException exception
		$mockFilterLookup = $this->createMock( FilterLookup::class );
		$mockFilterLookup->method( 'getFilter' )
			->willThrowException( new CentralDBNotAvailableException() );
		$this->setService( 'AbuseFilterFilterLookup', $mockFilterLookup );

		[ $html, ] = $this->executeSpecialPage(
			'examine/log/1',
			new FauxRequest(),
			null,
			$this->authorityCannotUseProtectedVar
		);

		// Verify that even though the Filter details could not be fetched, the filter is still considered
		// protected (to assume the most strict restrictions).
		$this->verifyHasExamineIntroMessage( $html );
		$this->assertStringContainsString(
			'(abusefilter-log-cannot-see-details)',
			$html,
			'Missing protected filter access error.'
		);
	}

	public function testViewExamineForLogEntryWhenProtectedVariablesUsedButReadOnly(): void {
		$this->getServiceContainer()->getReadOnlyMode()->setReason( 'test' );

		[ $html, ] = $this->executeSpecialPage(
			'examine/log/1',
			performer: $this->authorityCanUseProtectedVar
		);
		DeferredUpdates::doUpdates();

		$this->assertStringContainsString(
			'(readonlytext: test',
			$html,
			'A read only error should be displayed instead of showing protected variables'
		);

		// Assert no log is created (because the site is in read only mode)
		$this->newSelectQueryBuilder()
			->select( '1' )
			->from( 'logging' )
			->where( [
				'log_action' => 'view-protected-var-value',
				'log_type' => ProtectedVarsAccessLogger::LOG_TYPE,
			] )
			->caller( __METHOD__ )
			->assertEmptyResult();
	}

	public function testViewExamineForLogEntryWhenUserCanSeeLog() {
		[ $html, ] = $this->executeSpecialPage(
			'examine/log/1',
			new FauxRequest(),
			null,
			$this->authorityCanUseProtectedVar
		);
		DeferredUpdates::doUpdates();

		$this->verifyHasExamineIntroMessage( $html );

		// Check that the test tools elements are loaded
		$this->assertStringContainsString( '(abusefilter-examine-test', $html );
		$this->assertStringContainsString( '(abusefilter-examine-test-button', $html );

		// Verify that the examiner for the log entry is displayed by checking that the user_unnamed_ip
		// variable value is present.
		$this->assertStringContainsString( '(abusefilter-examine-vars', $html );
		$abuseLogDetailsTableHtml = $this->assertSelectorMatchesOneElement( $html, '.mw-abuselog-details' );
		$this->assertStringContainsString( '1.2.3.4', $abuseLogDetailsTableHtml );

		// Verify that a protected variable access log was created as protected variable values were viewed.
		$this->assertProtectedVariableAccessLogExists(
			$this->authorityCanUseProtectedVar->getUser(), self::$userWhoHitFilter, [ 'user_unnamed_ip' ]
		);
	}

	public function testViewExamineForRecentChangeWithMissingId() {
		[ $html, ] = $this->executeSpecialPage(
			'examine/1234',
			new FauxRequest(),
			null,
			$this->authorityCannotUseProtectedVar
		);

		$this->verifyHasExamineIntroMessage( $html );
		$this->assertStringContainsString(
			'(abusefilter-examine-notfound)',
			$html,
			'Missing error message for unknown AbuseLog ID.'
		);
	}

	/**
	 * @dataProvider provideIsLogSourceForRCAccessControl
	 */
	public function testViewExamineForShowExaminerForRCAccessControl( bool $isLogSource ) {
		$this->overrideConfigValue( MainConfigNames::PageCreationLog, false );

		$sysop = $this->getTestSysop()->getUser();
		if ( $isLogSource ) {
			$rc = $this->createRCEntryDeleteLog( $sysop );
			$ceil = self::LOG_DELETED_ALL;
			$permSet = self::PERMSET_LOG;
		} else {
			$rc = $this->createRCEntryEdit( $sysop );
			$ceil = self::REV_DELETED_ALL;
			$permSet = self::PERMSET_REVISION;
		}
		$rcid = (int)$rc->getAttribute( 'rc_id' );

		for ( $vis = 0; $vis <= $ceil; $vis++ ) {
			if ( $vis === LogPage::DELETED_RESTRICTED ) {
				// This bitfield is always composite in DB
				continue;
			}
			if ( $vis !== 0 ) {
				$this->updateRCEntryVisibility( $vis, $rcid );
			}

			foreach ( $permSet as $perms ) {
				$authority = $this->mockFilterEditorAuthorityWithPermissions( $perms );

				[ $html ] = $this->executeSpecialPage(
					"examine/$rcid", null, 'qqx', $authority
				);

				$shouldHaveAccess = $isLogSource
					? $this->shouldHaveRCEntryAccess( $vis, $perms )
					: $this->shouldHaveRevisionAccess( $vis, $perms );
				if ( $shouldHaveAccess ) {
					$this->assertStringContainsString( '(abusefilter-examine-test)', $html );
					$this->assertStringContainsString( '(abusefilter-examine-vars)', $html );
				} else {
					$this->assertStringContainsString( '(abusefilter-log-details-hidden-implicit)', $html );
				}
			}
		}
	}

	public static function provideIsLogSourceForRCAccessControl() {
		return [
			'Access control for a RecentChange::SRC_LOG entry' => [ true ],
			'Access control for a non-RecentChange::SRC_LOG entry' => [ false ],
		];
	}

	public function testViewExamineForRecentChangeWhereUserCannotSeeSpecificProtectedVariableDueToPermission() {
		// Mock that all users lack access to the 'custom_variable' variable due to it being a protected variable.
		$this->addCustomProtectedVariableToGenericVars();
		$this->setTemporaryHook(
			'AbuseFilterCanViewProtectedVariables',
			static function ( Authority $performer, array $variables, AbuseFilterPermissionStatus $returnStatus ) {
				if ( in_array( 'custom_variable', $variables ) ) {
					$returnStatus->setPermission( 'test-permission' );
				}
			}
		);

		[ $html, ] = $this->executeSpecialPage(
			'examine/' . self::$recentChangeId, null, null, $this->authorityCanUseProtectedVar
		);

		$this->verifyHasExamineIntroMessage( $html );
		$this->assertStringNotContainsString(
			'mw-abuselog-details-custom_variable',
			$html,
			'The "custom_variable" variable was not unset, but it should ' .
				'have been because the user cannot see it.'
		);
	}

	public function testViewExamineForRecentChangeForProtectedVariablesButReadOnly(): void {
		$this->addCustomProtectedVariableToGenericVars();

		$this->getServiceContainer()->getReadOnlyMode()->setReason( 'test' );
		[ $html, ] = $this->executeSpecialPage(
			'examine/' . self::$recentChangeId, performer: $this->authorityCanUseProtectedVar
		);
		DeferredUpdates::doUpdates();

		$this->assertStringContainsString(
			'(readonlytext: test',
			$html,
			'A read only error should be displayed instead of showing protected variables'
		);

		// Assert no log is created (because the site is in read only mode)
		$this->newSelectQueryBuilder()
			->select( '1' )
			->from( 'logging' )
			->where( [
				'log_action' => 'view-protected-var-value',
				'log_type' => ProtectedVarsAccessLogger::LOG_TYPE,
			] )
			->caller( __METHOD__ )
			->assertEmptyResult();
	}

	public function testViewExamineForRecentChangeWhenUserCanSeeRecentChange() {
		$this->addCustomProtectedVariableToGenericVars();
		[ $html, ] = $this->executeSpecialPage(
			'examine/' . self::$recentChangeId, performer: $this->authorityCanUseProtectedVar
		);
		$htmlDoc = DOMUtils::parseHTML( $html );
		DeferredUpdates::doUpdates();

		$this->verifyHasExamineIntroMessage( $html );

		// Check that the test tools elements are loaded
		$this->assertStringContainsString( '(abusefilter-examine-test', $html );
		$this->assertStringContainsString( '(abusefilter-examine-test-button', $html );

		// Verify that the custom_variable variable is shown with it's value.
		$customVariableTableRow = $this->assertSelectorMatchesOneElementInNode(
			$htmlDoc,
			'.mw-abuselog-details-custom_variable',
			true
		);
		$this->assertStringContainsString( 'custom_variable_value', $customVariableTableRow );

		// Verify that a lazily loaded non-protected variable is shown (regression testing for T403645)
		$userTypeVariableTableRow = $this->assertSelectorMatchesOneElementInNode(
			$htmlDoc,
			'.mw-abuselog-details-user_type',
			true
		);
		$this->assertStringContainsString( 'user_type', $userTypeVariableTableRow );

		// Verify that a protected variable access log was created as protected variable values were viewed.
		$this->assertProtectedVariableAccessLogExists(
			$this->authorityCanUseProtectedVar->getUser(), static::$userWhoHitFilter, [ 'custom_variable' ]
		);
	}
}
