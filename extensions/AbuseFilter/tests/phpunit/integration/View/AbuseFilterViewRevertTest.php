<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Integration\View;

use DOMDocument;
use DOMElement;
use DOMXPath;
use MediaWiki\Api\ApiUsageException;
use MediaWiki\Context\RequestContext;
use MediaWiki\Exception\PermissionsError;
use MediaWiki\Exception\UserBlockedError;
use MediaWiki\Extension\AbuseFilter\AbuseFilterServices;
use MediaWiki\Extension\AbuseFilter\Filter\Flags;
use MediaWiki\Extension\AbuseFilter\Filter\MutableFilter;
use MediaWiki\Extension\AbuseFilter\ServiceNames;
use MediaWiki\Extension\AbuseFilter\Special\SpecialAbuseFilter;
use MediaWiki\Extension\AbuseFilter\Tests\Integration\FilterFromSpecsTestTrait;
use MediaWiki\Extension\AbuseFilter\Variables\VariableHolder;
use MediaWiki\Logging\DatabaseLogEntry;
use MediaWiki\MainConfigNames;
use MediaWiki\Permissions\Authority;
use MediaWiki\Permissions\UltimateAuthority;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Request\WebRequest;
use MediaWiki\Tests\Api\ApiTestCase;
use MediaWiki\Tests\Specials\SpecialPageExecutor;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentity;
use Wikimedia\Timestamp\TimestampFormat as TS;

/**
 * @group Database
 * @covers \MediaWiki\Extension\AbuseFilter\View\AbuseFilterViewRevert
 *
 * Indirectly covers:
 * @covers \MediaWiki\Extension\AbuseFilter\View\AbuseFilterView::showUnrecoverableError
 * @covers \MediaWiki\Extension\AbuseFilter\Consequences\ConsequencesFactory
 * @covers \MediaWiki\Extension\AbuseFilter\Consequences\Consequence\Block
 * @covers \MediaWiki\Extension\AbuseFilter\Consequences\Consequence\BlockAutopromote
 * @covers \MediaWiki\Extension\AbuseFilter\Consequences\Consequence\Degroup
 */
class AbuseFilterViewRevertTest extends ApiTestCase {
	use FilterFromSpecsTestTrait;
	use MockAuthorityTrait;

	private const FILTER_ID_MAP = [
		'block' => 1,
		'blockautopromote' => 2,
		'degroup' => 3,
	];

	private const SUPPRESSED_FILTER_ID = 4;

	/**
	 * @param string $subPage
	 * @param WebRequest|null $request
	 * @param string|null $language
	 * @param Authority|null $performer
	 * @param bool $fullHtml
	 * @param RequestContext|null $context
	 * @return array{0:string,1:WebRequest} `[ $html, $request ]`
	 * @see SpecialPageTestBase::executeSpecialPage
	 */
	private function executeSpecialPage(
		string $subPage = '',
		?WebRequest $request = null,
		?string $language = null,
		?Authority $performer = null,
		$fullHtml = false,
		?RequestContext $context = null
	): array {
		$services = $this->getServiceContainer();
		$sp = new SpecialAbuseFilter(
			$services->getService( ServiceNames::PermManager ),
			$services->getObjectFactory()
		);
		$sp->setLinkRenderer(
			$services->getLinkRendererFactory()->create()
		);

		return ( new SpecialPageExecutor() )->executeSpecialPage(
			$sp,
			$subPage,
			$request,
			$language ?: 'qqx',
			$performer,
			$fullHtml,
			$context
		);
	}

	private function enableAllFilterActions(): void {
		$this->overrideConfigValue(
			'AbuseFilterActions',
			[
				'throttle' => true,
				'warn' => true,
				'disallow' => true,
				'blockautopromote' => true,
				'block' => true,
				'rangeblock' => true,
				'degroup' => true,
				'tag' => true
			]
		);
	}

	private static function getDaysInSeconds( int $days ): int {
		return $days * 24 * 3600;
	}

	/**
	 * @inheritDoc
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->enableAllFilterActions();
		$this->overrideConfigValues( [
			MainConfigNames::PageCreationLog => false,
			MainConfigNames::RCMaxAge => self::getDaysInSeconds( 90 ),
			// Immediately autoconfirm registered users
			MainConfigNames::AutoConfirmAge => 0,
			MainConfigNames::AutoConfirmCount => 0,
		] );
	}

	/**
	 * @inheritDoc
	 */
	public function addDBDataOnce() {
		$this->enableAllFilterActions();
		$filterStore = AbuseFilterServices::getFilterStore();
		$performer = $this->getTestSysop()->getUserIdentity();
		$authority = new UltimateAuthority( $performer );

		$this->assertStatusGood(
			$filterStore->saveFilter(
				$authority,
				null,
				$this->getFilterFromSpecs( [
					'id' => '1',
					'rules' => 'action = "edit" & summary = "block"',
					'name' => 'Filter with a block consequence',
					'lastEditor' => $performer,
					'lastEditTimestamp' => '20260101000000',
					'actions' => [
						'disallow' => [ 'abusefilter-disallowed' ],
						'block' => [
							'noTalkBlockSet',
							'1 hour',
							'1 hour',
						],
					],
				] ),
				MutableFilter::newDefault()
			)
		);

		$this->assertStatusGood(
			$filterStore->saveFilter(
				$authority,
				null,
				$this->getFilterFromSpecs( [
					'id' => '2',
					'rules' => 'action = "edit" & summary = "blockautopromote"',
					'name' => 'Filter with a blockautopromote consequence',
					'lastEditor' => $performer,
					'lastEditTimestamp' => '20260101010000',
					'actions' => [
						'disallow' => [ 'abusefilter-disallowed' ],
						'blockautopromote' => [],
					],
				] ),
				MutableFilter::newDefault()
			)
		);

		$this->assertStatusGood(
			$filterStore->saveFilter(
				$authority,
				null,
				$this->getFilterFromSpecs( [
					'id' => '3',
					'rules' => 'action = "edit" & summary = "degroup"',
					'name' => 'Filter with a degroup consequence',
					'lastEditor' => $performer,
					'lastEditTimestamp' => '20260101020000',
					'actions' => [
						'disallow' => [ 'abusefilter-disallowed' ],
						'degroup' => [],
					],
				] ),
				MutableFilter::newDefault()
			)
		);

		$this->assertStatusGood(
			$filterStore->saveFilter(
				$authority,
				null,
				$this->getFilterFromSpecs( [
					'id' => (string)self::SUPPRESSED_FILTER_ID,
					'rules' => 'action = "edit" & summary = "suppressed"',
					'name' => 'Suppressed filter with a block consequence',
					'privacy' => Flags::FILTER_SUPPRESSED,
					'lastEditor' => $performer,
					'lastEditTimestamp' => '20260101030000',
					'actions' => [
						'disallow' => [ 'abusefilter-disallowed' ],
						'block' => [
							'noTalkBlockSet',
							'1 hour',
							'1 hour',
						],
					],
				] ),
				MutableFilter::newDefault()
			)
		);

		$this->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->table( 'abuse_filter' )
			->where( [ 'af_enabled' => 1 ] )
			->caller( __METHOD__ )
			->assertFieldValue( 4 );
	}

	protected function tearDown(): void {
		parent::tearDown();

		// The logging table is touched in addDBDataOnce(); clear entries manually
		// (except for filter creation logs generated in the method)
		$dbw = $this->getDb();
		$dbw->newDeleteQueryBuilder()
			->table( 'logging' )
			->where( $dbw->expr( 'log_type', '!=', 'abusefilter' ) )
			->caller( __METHOD__ )
			->execute();
	}

	/**
	 * @param 'block'|'blockautopromote'|'degroup' $consequence
	 * @return int
	 */
	private static function getFilterIdFromConsequence( string $consequence ): int {
		$filterId = self::FILTER_ID_MAP[$consequence] ?? null;
		if ( $filterId === null ) {
			self::fail( "No filter found with a $consequence consequence" );
		}
		return $filterId;
	}

	public function testShowWithUnauthorizedPerformer() {
		$performer = $this->mockRegisteredAuthorityWithoutPermissions( [ 'abusefilter-revert' ] );

		$this->expectException( PermissionsError::class );
		$this->executeSpecialPage( 'revert/1', performer: $performer );
	}

	public function testShowWithSitewideBlockedPerformer() {
		$performer = $this->getTestUser( 'sysop' )->getAuthority();

		$status = $this->getServiceContainer()->getBlockUserFactory()
			->newBlockUser(
				$performer->getUser(),
				$this->getTestSysop()->getAuthority(),
				'infinity'
			)
			->placeBlock();
		$this->assertStatusGood( $status, 'Block was not placed' );

		$this->expectException( UserBlockedError::class );
		$this->executeSpecialPage( 'revert/1', performer: $performer );
	}

	public function testShowForPageTitle() {
		$performer = $this->getTestSysop()->getAuthority();

		[ $html ] = $this->executeSpecialPage( 'revert/1', performer: $performer, fullHtml: true );

		$this->assertStringContainsString( '(abusefilter-revert-title: 1)', $html );
	}

	public function testShowForNonexistingFilter() {
		$performer = $this->getTestSysop()->getAuthority();

		[ $html ] = $this->executeSpecialPage( 'revert/999', performer: $performer );

		$this->assertStringContainsString( '(abusefilter-edit-badfilter)', $html );
		$this->assertStringContainsString( '(abusefilter-return)', $html );
	}

	public function testShowForSuppressedFilterWithoutPermission() {
		$filterId = self::SUPPRESSED_FILTER_ID;
		$this->addAbuseLog( 'block', $this->getTestUser()->getUser(), null, $filterId );

		[ $html ] = $this->executeSpecialPage(
			"revert/$filterId",
			self::createSearchRequest( $filterId ),
			null,
			$this->getTestSysop()->getAuthority()
		);

		$this->assertStringContainsString( '(abusefilter-edit-denied-suppressed)', $html );
		$this->assertStringNotContainsString( '(abusefilter-revert-intro', $html );
		$this->assertStringNotContainsString( '(abusefilter-revert-preview-item', $html );
		$this->assertStringNotContainsString( $this->getTestUser()->getUser()->getName(), $html );
	}

	public function testShowForSuppressedFilterWithPermission() {
		$this->setGroupPermissions( 'sysop', 'viewsuppressed', true );
		$filterId = self::SUPPRESSED_FILTER_ID;
		$this->addAbuseLog( 'block', $this->getTestUser()->getUser(), null, $filterId );

		[ $html ] = $this->executeSpecialPage(
			"revert/$filterId",
			self::createSearchRequest( $filterId ),
			null,
			$this->getTestSysop()->getAuthority()
		);

		$this->assertStringNotContainsString( '(abusefilter-edit-denied-suppressed)', $html );
		$this->assertStringContainsString( '(abusefilter-revert-preview-item', $html );
		$this->assertStringContainsString( $this->getTestUser()->getUser()->getName(), $html );
	}

	public function testShowForSearchForm() {
		$performer = $this->getTestSysop()->getAuthority();

		[ $html ] = $this->executeSpecialPage( 'revert/1', performer: $performer );

		$this->assertStringContainsString(
			'(abusefilter-revert-intro: 1)',
			$html,
			'Missing "abusefilter-revert-intro"'
		);

		$dom = new DOMDocument();
		// phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
		@$dom->loadHTML( $html );
		$xpath = new DOMXPath( $dom );

		// Fieldset and legend
		$this->assertSame(
			1,
			$xpath->query(
				'//fieldset/legend[contains(normalize-space(.), "(abusefilter-revert-search-legend)")]'
			)->length
		);

		// Filter label
		$this->assertSame(
			1,
			$xpath->query(
				'//label[contains(normalize-space(.), "(abusefilter-revert-filter)")]'
			)->length
		);

		// Filter link
		$this->assertSame(
			1,
			$xpath->query(
				'//a[@href="/wiki/Special:AbuseFilter/1" and normalize-space(.)="1"]'
			)->length
		);

		// Period start label
		$this->assertSame(
			1,
			$xpath->query(
				'//label[contains(normalize-space(.), "(abusefilter-revert-periodstart)")]'
			)->length
		);

		// Period start input
		$this->assertSame(
			1,
			$xpath->query(
				'//input[@type="datetime"][@name="wpPeriodStart"]'
			)->length
		);

		// Period end label
		$this->assertSame(
			1,
			$xpath->query(
				'//label[contains(normalize-space(.), "(abusefilter-revert-periodend)")]'
			)->length
		);

		// Period end input
		$this->assertSame(
			1,
			$xpath->query(
				'//input[@type="datetime"][@name="wpPeriodEnd"]'
			)->length
		);

		// Hidden form identifier
		$this->assertSame(
			1,
			$xpath->query(
				'//input[@type="hidden"][@name="wpFormIdentifier"][@value="revert-select-date"]'
			)->length
		);

		// Submit button
		$this->assertSame(
			1,
			$xpath->query(
				'//button[@type="submit"]'
			)->length
		);
		$this->assertSame(
			1,
			$xpath->query(
				'//button[contains(normalize-space(.), "(abusefilter-revert-search)")]'
			)->length
		);
	}

	/**
	 * Creates a request where the revert search form is submitted.
	 *
	 * @param int $filterId
	 * @param array{wpPeriodStart?:string,wpPeriodEnd?:string} $options
	 * @return FauxRequest
	 */
	private static function createSearchRequest( int $filterId, array $options = [] ): FauxRequest {
		return new FauxRequest( $options + [
			'wpPeriodStart' => '',
			'wpPeriodEnd' => '',
			'wpFormIdentifier' => 'revert-select-date',
			'title' => "Special:AbuseFilter/revert/$filterId",
		] );
	}

	/**
	 * @param 'block'|'blockautopromote'|'degroup' $consequence
	 * @param User $performer
	 * @param string[]|null $consequenceOverride
	 * @param int|null $filterId Use this filter instead of the one with the given consequence
	 * @return int The triggered filter's ID.
	 */
	private function addAbuseLog(
		string $consequence,
		User $performer,
		?array $consequenceOverride = null,
		?int $filterId = null
	): int {
		$filterId ??= self::getFilterIdFromConsequence( $consequence );
		$actions = $consequenceOverride ?? [ 'disallow', $consequence ];

		AbuseFilterServices::getAbuseLoggerFactory()
			->newLogger(
				$this->getExistingTestPage()->getTitle(),
				$performer,
				VariableHolder::newFromArray( [
					'action' => 'edit',
					'summary' => $consequence,
					'user_name' => $performer->getName(),
				] )
			)
			->addLogEntries( [ $filterId => $actions ] );

		return $filterId;
	}

	public function testShowRevertableActionsWithNoResults() {
		$filterId = self::getFilterIdFromConsequence( 'block' );

		[ $html ] = $this->executeSpecialPage(
			"revert/$filterId",
			self::createSearchRequest( $filterId ),
			null,
			$this->getTestSysop()->getAuthority()
		);

		$this->assertStringContainsString( '(abusefilter-revert-preview-no-results)', $html );
	}

	public function testShowRevertableActionsWithResults() {
		$filterId = self::getFilterIdFromConsequence( 'block' );
		$performer = $this->getTestSysop()->getAuthority();

		$this->addAbuseLog( 'block', $performer );
		$this->addAbuseLog( 'block', $performer, [ 'disallow', 'blockautopromote' ] );
		$this->addAbuseLog( 'block', $performer, [ 'disallow', 'degroup' ] );
		$this->addAbuseLog( 'block', $performer, [ 'disallow', 'non-revertable' ] );

		[ $html ] = $this->executeSpecialPage(
			"revert/$filterId",
			self::createSearchRequest( $filterId ),
			null,
			$performer
		);

		$dom = new DOMDocument();
		// phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
		@$dom->loadHTML( $html );
		$xpath = new DOMXPath( $dom );

		// Preview intro
		$this->assertSame(
			1,
			$xpath->query(
				'//p[contains(normalize-space(.), "(abusefilter-revert-preview-intro)")]'
			)->length
		);

		// Three revertable results only (non-revertable should be filtered out)
		$this->assertSame(
			3,
			$xpath->query( '//ul/li' )->length
		);

		// Each consequence is displayed
		$this->assertSame(
			1,
			$xpath->query(
				'//li[contains(., "(abusefilter-action-block)")]'
			)->length
		);
		$this->assertSame(
			1,
			$xpath->query(
				'//li[contains(., "(abusefilter-action-blockautopromote)")]'
			)->length
		);
		$this->assertSame(
			1,
			$xpath->query(
				'//li[contains(., "(abusefilter-action-degroup)")]'
			)->length
		);

		// Non-revertable consequence must not appear
		$this->assertSame(
			0,
			$xpath->query(
				'//li[contains(., "non-revertable")]'
			)->length
		);

		// AbuseLog links
		$this->assertSame(
			3,
			$xpath->query(
				'//a[contains(@href, "/wiki/Special:AbuseLog/")]'
			)->length
		);
	}

	public function testShowRevertableActionsForConfirmationForm() {
		$filterId = self::getFilterIdFromConsequence( 'block' );
		$performer = $this->getTestSysop()->getAuthority();

		$this->addAbuseLog( 'block', $performer );

		[ $html ] = $this->executeSpecialPage(
			"revert/$filterId",
			self::createSearchRequest( $filterId ),
			null,
			$performer
		);

		$dom = new DOMDocument();
		// phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
		@$dom->loadHTML( $html );
		$xpath = new DOMXPath( $dom );

		// Legend
		$this->assertSame(
			1,
			$xpath->query(
				'//legend[contains(., "(abusefilter-revert-confirm-legend)")]'
			)->length
		);

		// Reason field
		$this->assertSame(
			1,
			$xpath->query(
				'//input[@type="text"][@name="wpReason"]'
			)->length
		);
		$this->assertSame(
			1,
			$xpath->query(
				'//label[contains(., "(abusefilter-revert-reasonfield)")]'
			)->length
		);

		// Hidden fields
		$this->assertSame(
			1,
			$xpath->query(
				'//input[@type="hidden"][@name="wpPeriodStart"]'
			)->length
		);
		$this->assertSame(
			1,
			$xpath->query(
				'//input[@type="hidden"][@name="wpPeriodEnd"]'
			)->length
		);
		$this->assertSame(
			1,
			$xpath->query(
				'//input[@type="hidden"][@name="wpEditToken"]'
			)->length
		);

		// Submit button
		$this->assertSame(
			1,
			$xpath->query(
				'//button[@type="submit"][contains(., "(abusefilter-revert-confirm)")]'
			)->length
		);
	}

	public function testShowRevertableActionsWithDateRanges() {
		$filterId = self::getFilterIdFromConsequence( 'block' );
		$performer = $this->getTestSysop()->getAuthority();

		$now = time();
		$this->addAbuseLog( 'block', $performer );

		[ $html ] = $this->executeSpecialPage(
			"revert/$filterId",
			self::createSearchRequest( $filterId, [
				'wpPeriodStart' => wfTimestamp( TS::MW, $now - self::getDaysInSeconds( 2 ) ),
				'wpPeriodEnd' => wfTimestamp( TS::MW, $now - self::getDaysInSeconds( 1 ) ),
			] ),
			null,
			$performer
		);

		$this->assertStringContainsString( '(abusefilter-revert-preview-no-results)', $html );
	}

	/**
	 * Triggers a filter with the given consequence via `action=edit`.
	 *
	 * @param 'block'|'blockautopromote'|'degroup' $consequence
	 * @param Authority $performer The associated user must already exist in the database.
	 * If testing the `degroup` consequence, the user must initially belong to the `dummy-group`.
	 */
	private function triggerFilter( string $consequence, Authority $performer ): void {
		$this->assertNotAffectedByConsequence( $consequence, $performer );

		// Trigger the filter by attempting a blocked edit
		try {
			$this->doApiRequestWithToken( [
				'action' => 'edit',
				'title' => $this->getExistingTestPage()->getTitle()->getText(),
				'summary' => $consequence,
				'appendtext' => '+',
			], null, $performer );
			$this->fail( 'Expected the API request to fail' );
		} catch ( ApiUsageException $e ) {
			$this->assertApiErrorCode( 'abusefilter-disallowed', $e );
		}
		$this->apiContext->resetMain();

		$this->assertAffectedByConsequence( $consequence, $performer );
	}

	/**
	 * @param 'block'|'blockautopromote'|'degroup' $consequence
	 * @param Authority $performer The associated user must already exist in the database.
	 * If testing the `degroup` consequence, the user must initially belong to the `dummy-group`.
	 */
	private function assertAffectedByConsequence( string $consequence, Authority $performer ): void {
		$services = $this->getServiceContainer();

		switch ( $consequence ) {
			case 'block':
				$block = $services->getBlockManager()->getBlock( $performer->getUser(), null );
				$this->assertNotNull( $block );
				$this->assertSame( $block->getBy(), AbuseFilterServices::getFilterUser()->getUserIdentity()->getId() );
				break;
			case 'blockautopromote':
				$blockAutopromoteStore = AbuseFilterServices::getBlockAutopromoteStore( $services );
				$this->assertTrue(
					(bool)$blockAutopromoteStore->getAutoPromoteBlockStatus( $performer->getUser() )
				);
				break;
			case 'degroup':
				$this->assertArrayNotHasKey(
					'dummy-group',
					$services->getUserGroupManager()->getUserGroupMemberships( $performer->getUser() ),
				);
				break;
			default:
				$this->fail( "Unknown consequence: $consequence" );
		}
	}

	/**
	 * @param 'block'|'blockautopromote'|'degroup' $consequence
	 * @param Authority $performer The associated user must already exist in the database.
	 * If testing the `degroup` consequence, the user must initially belong to the `dummy-group`.
	 */
	private function assertNotAffectedByConsequence( string $consequence, Authority $performer ): void {
		$services = $this->getServiceContainer();

		switch ( $consequence ) {
			case 'block':
				$block = $services->getBlockManager()->getBlock( $performer->getUser(), null );
				$this->assertNull( $block );
				break;
			case 'blockautopromote':
				$blockAutopromoteStore = AbuseFilterServices::getBlockAutopromoteStore( $services );
				$this->assertFalse(
					(bool)$blockAutopromoteStore->getAutoPromoteBlockStatus( $performer->getUser() )
				);
				break;
			case 'degroup':
				$this->assertArrayHasKey(
					'dummy-group',
					$services->getUserGroupManager()->getUserGroupMemberships( $performer->getUser() ),
				);
				break;
			default:
				$this->fail( "Unknown consequence: $consequence" );
		}
	}

	/**
	 * Creates a context where the revert confirmation form is submitted.
	 *
	 * @param int $filterId
	 * @param array{wpPeriodStart?:string,wpPeriodEnd?:string,wpReason?:string} $options
	 * @return RequestContext
	 */
	private function createRevertRequestContext( int $filterId, array $options = [] ): RequestContext {
		$context = RequestContext::getMain();

		$performer = $this->getTestSysop()->getAuthority();
		$context->setAuthority( $performer );

		$token = $context->getCsrfTokenSet()->getToken( "abusefilter-revert-$filterId" );
		$request = new FauxRequest( [
			...$options,
			'wpEditToken' => $token->toString(),
		], true );
		$context->setRequest( $request );
		$context->setLanguage( 'qqx' );

		return $context;
	}

	/**
	 * @dataProvider provideAttemptRevert
	 * @param 'block'|'blockautopromote'|'degroup' $consequence
	 */
	public function testAttemptRevert( string $consequence ) {
		$filterId = self::getFilterIdFromConsequence( $consequence );
		$triggerer = $this->getMutableTestUser( 'dummy-group', 'TestUser ' . __FUNCTION__ );
		$triggerer->getUser()->addToDatabase();

		$this->triggerFilter( $consequence, $triggerer->getAuthority() );

		$reason = 'A very good revert reason';
		$context = $this->createRevertRequestContext( $filterId, [
			'wpReason' => $reason,
		] );
		[ $html ] = $this->executeSpecialPage( "revert/$filterId", context: $context );

		$this->assertNotAffectedByConsequence( $consequence, $triggerer->getAuthority() );
		$this->assertStringContainsString( "(abusefilter-revert-success: $filterId, $filterId)", $html );

		if ( $consequence === 'blockautopromote' ) {
			// TODO: ManualLogEntry::publish() is disabled in BlockAutopromoteStore::unblockAutopromote()
			return;
		}

		$performer = $context->getAuthority();
		$rows = DatabaseLogEntry::newSelectQueryBuilder( $this->getDb() )
			->where( [
				'actor_user' => $performer->getUser()->getId(),
				$this->getDb()->expr( 'log_type', '!=', 'abusefilter' ),
			] )
			->caller( __METHOD__ )
			->fetchResultSet();
		$this->assertCount( 1, $rows );

		$entry = DatabaseLogEntry::newFromRow( $rows->fetchObject() );

		$this->assertSame(
			$performer->getUser()->getId(),
			$entry->getPerformerIdentity()->getId()
		);
		$this->assertSame(
			$triggerer->getUser()->getName(),
			$entry->getTarget()->getText()
		);
		$this->assertSame(
			wfMessage( 'abusefilter-revert-reason', $filterId, $reason )->inContentLanguage()->text(),
			$entry->getComment()
		);
	}

	public static function provideAttemptRevert(): array {
		return [
			'Revert a block consequence' => [ 'block' ],
			'Revert a blockautopromote consequence' => [ 'blockautopromote' ],
			'Revert a degroup consequence' => [ 'degroup' ],
		];
	}

	public function testFormValuesPassedDown() {
		// FIXME: The confirmation form does not preserve the selected period. `showRevertableActions()`
		// creates hidden `PeriodStart` and `PeriodEnd` fields without propagating the submitted values,
		// so the confirmation request loses the selected date range. Remove this skip after fixing
		// the confirmation form to preserve those values.
		$this->markTestSkipped();

		$consequence = 'block';
		$filterId = self::getFilterIdFromConsequence( $consequence );

		// Trigger the block filter and rewrite the timestamp to 2 days ago
		$firstTriggerer = $this->getMutableTestUser( 'dummy-group', 'TestUser1 ' . __FUNCTION__ );
		$firstTriggerer->getUser()->addToDatabase();
		$now = time();
		$this->triggerFilter( $consequence, $firstTriggerer->getAuthority() );

		$this->getDb()->newUpdateQueryBuilder()
			->table( 'abuse_filter_log' )
			->where( [
				'afl_filter_id' => $filterId,
				'afl_user' => $firstTriggerer->getUser()->getId(),
			] )
			->set( [
				'afl_timestamp' => wfTimestamp( TS::MW, $now - self::getDaysInSeconds( 2 ) ),
			] )
			->caller( __METHOD__ )
			->execute();
		$this->assertSame( 1, $this->getDb()->affectedRows() );

		// Trigger the block filter again with a different user
		$secondTriggerer = $this->getMutableTestUser( 'dummy-group', 'TestUser2 ' . __FUNCTION__ );
		$secondTriggerer->getUser()->addToDatabase();
		$this->triggerFilter( $consequence, $secondTriggerer->getAuthority() );

		// Search for revertable entries using a specific date range
		$performer = $this->getTestSysop()->getAuthority();
		$context = RequestContext::getMain();
		$context->setAuthority( $performer );
		$context->setRequest(
			$this->createSearchRequest( $filterId, [
				'wpPeriodStart' => wfTimestamp( TS::ISO_8601, $now - $this->getDaysInSeconds( 3 ) ),
				'wpPeriodEnd' => wfTimestamp( TS::ISO_8601, $now - $this->getDaysInSeconds( 1 ) ),
			] )
		);
		$context->setLanguage( 'qqx' );

		[ $html ] = $this->executeSpecialPage( "revert/$filterId", context: $context );

		$dom = new DOMDocument();
		// phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
		@$dom->loadHTML( $html );
		$xpath = new DOMXPath( $dom );

		$items = $xpath->query( '//ul/li' );
		$this->assertCount( 1, $items );
		$this->assertStringContainsString(
			$firstTriggerer->getUser()->getName(),
			$items->item( 0 )->textContent
		);
		$this->assertStringNotContainsString(
			$secondTriggerer->getUser()->getName(),
			$html
		);

		// Retrieve the values propagated to the confirmation form
		$periodStartNode = $xpath->query(
			'//input[@type="hidden"][@name="wpPeriodStart"]'
		)->item( 0 );
		$this->assertInstanceOf( DOMElement::class, $periodStartNode );
		$periodStart = $periodStartNode->getAttribute( 'value' );

		$periodEndNode = $xpath->query(
			'//input[@type="hidden"][@name="wpPeriodEnd"]'
		)->item( 0 );
		$this->assertInstanceOf( DOMElement::class, $periodEndNode );
		$periodEnd = $periodEndNode->getAttribute( 'value' );

		$searchRequest = $context->getRequest();

		$this->assertSame(
			$searchRequest->getText( 'wpPeriodStart' ),
			$periodStart
		);
		$this->assertSame(
			$searchRequest->getText( 'wpPeriodEnd' ),
			$periodEnd
		);

		// Submit the confirmation form using the preserved date range
		$context = $this->createRevertRequestContext( $filterId, [
			'wpPeriodStart' => $periodStart,
			'wpPeriodEnd' => $periodEnd,
			'wpReason' => 'Testing period inheritance',
		] );
		$this->executeSpecialPage( "revert/$filterId", context: $context );

		// Only the first (older) consequence should have been reverted
		$this->assertNotAffectedByConsequence( $consequence, $firstTriggerer->getAuthority() );
		$this->assertAffectedByConsequence( $consequence, $secondTriggerer->getAuthority() );
	}

	private function hideAbuseLogEntries( UserIdentity $user ): void {
		$this->getDb()->newUpdateQueryBuilder()
			->table( 'abuse_filter_log' )
			->set( [ 'afl_deleted' => 1 ] )
			->where( [ 'afl_user' => $user->getId() ] )
			->caller( __METHOD__ )
			->execute();
		$this->assertSame( 1, $this->getDb()->affectedRows() );
	}

	/**
	 * @dataProvider provideShowRevertableActionsWithHiddenEntry
	 */
	public function testShowRevertableActionsWithHiddenEntry( bool $canSeeHidden ) {
		$this->setGroupPermissions( 'sysop', 'abusefilter-hidden-log', $canSeeHidden );
		$filterId = self::getFilterIdFromConsequence( 'block' );
		$triggerer = $this->getMutableTestUser()->getUser();

		$this->addAbuseLog( 'block', $triggerer );
		$this->hideAbuseLogEntries( $triggerer );

		[ $html ] = $this->executeSpecialPage(
			"revert/$filterId",
			self::createSearchRequest( $filterId ),
			null,
			$this->getTestSysop()->getAuthority()
		);

		if ( $canSeeHidden ) {
			$this->assertStringContainsString( '(abusefilter-revert-preview-item', $html );
			$this->assertStringContainsString( $triggerer->getName(), $html );
		} else {
			$this->assertStringContainsString( '(abusefilter-revert-preview-no-results)', $html );
			$this->assertStringNotContainsString( $triggerer->getName(), $html );
		}
	}

	public static function provideShowRevertableActionsWithHiddenEntry(): array {
		return [
			'User can see hidden entries' => [ true ],
			'User cannot see hidden entries' => [ false ],
		];
	}

	public function testAttemptRevertSkipsHiddenEntryWithoutPermission() {
		$this->setGroupPermissions( 'sysop', 'abusefilter-hidden-log', false );
		$consequence = 'block';
		$filterId = self::getFilterIdFromConsequence( $consequence );
		$triggerer = $this->getMutableTestUser( [], 'TestUser ' . __FUNCTION__ );
		$triggerer->getUser()->addToDatabase();

		$this->triggerFilter( $consequence, $triggerer->getAuthority() );
		$this->hideAbuseLogEntries( $triggerer->getUser() );

		$context = $this->createRevertRequestContext( $filterId );
		$this->executeSpecialPage( "revert/$filterId", context: $context );

		$this->assertAffectedByConsequence( $consequence, $triggerer->getAuthority() );
	}
}
