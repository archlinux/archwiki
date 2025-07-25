<?php

namespace MediaWiki\CheckUser\Tests\Integration\GlobalContributions;

use MediaWiki\CheckUser\CheckUserQueryInterface;
use MediaWiki\CheckUser\GlobalContributions\CheckUserApiRequestAggregator;
use MediaWiki\CheckUser\GlobalContributions\CheckUserGlobalContributionsLookup;
use MediaWiki\CheckUser\GlobalContributions\GlobalContributionsPager;
use MediaWiki\Context\RequestContext;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Title\NamespaceInfo;
use MediaWiki\Title\Title;
use MediaWiki\User\CentralId\CentralIdLookup;
use MediaWiki\User\UserIdentityValue;
use MediaWiki\WikiMap\WikiMap;
use MediaWikiIntegrationTestCase;
use Wikimedia\Rdbms\FakeResultWrapper;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\IResultWrapper;
use Wikimedia\Rdbms\SelectQueryBuilder;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \MediaWiki\CheckUser\GlobalContributions\GlobalContributionsPager
 * @group CheckUser
 * @group Database
 */
class GlobalContributionsPagerTest extends MediaWikiIntegrationTestCase {
	public function setUp(): void {
		parent::setUp();

		$this->markTestSkippedIfExtensionNotLoaded( 'GlobalPreferences' );
		$this->setUserLang( 'qqx' );
	}

	private function getPagerWithOverrides( $overrides ) {
		$services = $this->getServiceContainer();
		return new GlobalContributionsPager(
			$overrides['LinkRenderer'] ?? $services->getLinkRenderer(),
			$overrides['LinkBatchFactory'] ?? $services->getLinkBatchFactory(),
			$overrides['HookContainer'] ?? $services->getHookContainer(),
			$overrides['RevisionStore'] ?? $services->getRevisionStore(),
			$overrides['NamespaceInfo'] ?? $services->getNamespaceInfo(),
			$overrides['CommentFormatter'] ?? $services->getCommentFormatter(),
			$overrides['UserFactory'] ?? $services->getUserFactory(),
			$overrides['TempUserConfig'] ?? $services->getTempUserConfig(),
			$overrides['CheckUserLookupUtils'] ?? $services->get( 'CheckUserLookupUtils' ),
			$overrides['CentralIdLookup'] ?? $services->get( 'CentralIdLookup' ),
			$overrides['RequestAggregator'] ?? $services->get( 'CheckUserApiRequestAggregator' ),
			$overrides['GlobalContributionsLookup'] ?? $services->get( 'CheckUserGlobalContributionsLookup' ),
			$overrides['PermissionManager'] ?? $services->getPermissionManager(),
			$overrides['PreferencesFactory'] ?? $services->getPreferencesFactory(),
			$overrides['LoadBalancerFactory'] ?? $services->getConnectionProvider(),
			$overrides['JobQueueGroup'] ?? $services->getJobQueueGroup(),
			$overrides['StatsFactory'] ?? $services->getStatsFactory(),
			$overrides['Context'] ?? RequestContext::getMain(),
			$overrides['options'] ?? [ 'revisionsOnly' => true ],
			new UserIdentityValue( 0, $overrides['UserName'] ?? '127.0.0.1' )
		);
	}

	private function getPager( $userName ) {
		return $this->getServiceContainer()->get( 'CheckUserGlobalContributionsPagerFactory' )
			->createPager(
				RequestContext::getMain(),
				[ 'revisionsOnly' => true ],
				new UserIdentityValue( 0, $userName )
			);
	}

	private function getWrappedPager( $userName, $pageTitle, $pageNamespace = 0 ) {
		$pager = TestingAccessWrapper::newFromObject( $this->getPager( $userName ) );
		$pager->currentPage = Title::makeTitle( $pageNamespace, $pageTitle );
		return $pager;
	}

	private function getRow( $options = [] ) {
		return (object)( array_merge(
			[
				'rev_id' => '2',
				'rev_page' => '1',
				'rev_actor' => '1',
				'rev_user' => '1',
				'rev_user_text' => '~2024-123',
				'rev_timestamp' => '20240101000000',
				'rev_minor_edit' => '0',
				'rev_deleted' => '0',
				'rev_len' => '100',
				'rev_parent_id' => '1',
				'rev_sha1' => '',
				'rev_comment_text' => '',
				'rev_comment_data' => null,
				'rev_comment_cid' => '1',
				'page_latest' => '2',
				'page_is_new' => '0',
				'page_namespace' => '0',
				'page_title' => 'Test page',
				'cuc_timestamp' => '20240101000000',
				'ts_tags' => null,
			],
			$options
		) );
	}

	public function testPopulateAttributes() {
		$pager = $this->getPager( '127.0.0.1' );
		$row = $this->getRow( [ 'sourcewiki' => 'otherwiki' ] );

		// We can't call populateAttributes directly because TestingAccessWrapper
		// can't pass by reference: T287318
		$formatted = $pager->formatRow( $row );
		$this->assertStringNotContainsString( 'data-mw-revid', $formatted );
	}

	/**
	 * @dataProvider provideFormatArticleLink
	 */
	public function testFormatArticleLink( $namespace, $expectedPageLinkText ) {
		$row = $this->getRow( [
			'sourcewiki' => 'otherwiki',
			'page_namespace' => $namespace,
			'page_title' => 'Test',
		] );
		$pager = $this->getWrappedPager( '127.0.0.1', $row->page_title, $row->page_namespace );

		$formatted = $pager->formatArticleLink( $row );
		$this->assertStringContainsString( 'external', $formatted );
		$this->assertStringContainsString( $row->page_title, $formatted );

		$this->assertStringContainsString(
			$expectedPageLinkText,
			$formatted
		);
	}

	public static function provideFormatArticleLink() {
		return [
			'Known external namespace is shown' => [
				'namespace' => NS_TALK,
				'expectedPageLinkText' => NamespaceInfo::CANONICAL_NAMES[NS_TALK] . ':Test',
			],
			'Unknown external namespace is not shown' => [
				'namespace' => 1000,
				'expectedPageLinkText' =>
					'(checkuser-global-contributions-page-when-no-namespace-translation-available: 1,000, Test)',
			],
		];
	}

	/**
	 * @dataProvider provideFormatDiffHistLinks
	 */
	public function testFormatDiffHistLinks( $isNewPage, $isHidden, $expectDiffLink ) {
		$row = $this->getRow( [
			'sourcewiki' => 'otherwiki',
			'rev_parent_id' => $isNewPage ? '0' : '1',
			'rev_id' => '2',
			'rev_deleted' => $isHidden ? '1' : '0',
			'rev_page' => '100',
		] );
		$pager = $this->getWrappedPager( '127.0.0.1', $row->page_title );

		$formatted = $pager->formatDiffHistLinks( $row );
		$this->assertStringContainsString( 'external', $formatted );
		$this->assertStringContainsString( 'diff', $formatted );
		$this->assertStringContainsString( 'action=history', $formatted );
		$this->assertStringContainsString( 'curid=100', $formatted );
		if ( $expectDiffLink ) {
			$this->assertStringContainsString( 'oldid=2', $formatted );
		} else {
			$this->assertStringNotContainsString( 'oldid=2', $formatted );
		}
	}

	public static function provideFormatDiffHistLinks() {
		return [
			'No diff link for a new page' => [ true, false, false ],
			'No diff link for not a new page, hidden from user' => [ false, true, false ],
			'Diff link for not a new page, visible to user' => [ false, false, true ],
		];
	}

	/**
	 * @dataProvider provideFormatDateLink
	 */
	public function testFormatDateLink( $isHidden ) {
		$row = $this->getRow( [
			'sourcewiki' => 'otherwiki',
			'rev_timestamp' => '20240101000000',
			'rev_deleted' => $isHidden ? '1' : '0'
		] );
		$pager = $this->getWrappedPager( '127.0.0.1', $row->page_title );

		$formatted = $pager->formatDateLink( $row );
		$this->assertStringContainsString( '2024', $formatted );
		if ( $isHidden ) {
			$this->assertStringNotContainsString( 'external', $formatted );
		} else {
			$this->assertStringContainsString( 'external', $formatted );
		}
	}

	public static function provideFormatDateLink() {
		return [ [ true ], [ false ] ];
	}

	/**
	 * @dataProvider provideFormatTopMarkText
	 */
	public function testFormatTopMarkText( $revisionIsLatest ) {
		$row = $this->getRow( [
			'sourcewiki' => 'otherwiki',
			'rev_id' => '2',
			'page_latest' => $revisionIsLatest ? '2' : '3',
		] );
		$pager = $this->getPager( '127.0.0.1' );

		// We can't call formatTopMarkText directly because TestingAccessWrapper
		// can't pass by reference: T287318
		$formatted = $pager->formatRow( $row );
		if ( $revisionIsLatest ) {
			$this->assertStringContainsString( 'uctop', $formatted );
		} else {
			$this->assertStringNotContainsString( 'uctop', $formatted );
		}
	}

	public static function provideFormatTopMarkText() {
		return [ [ true ], [ false ] ];
	}

	public function testFormatComment() {
		$row = $this->getRow( [ 'sourcewiki' => 'otherwiki' ] );
		$pager = $this->getWrappedPager( '127.0.0.1', $row->page_title );

		$formatted = $pager->formatComment( $row );
		$this->assertSame(
			sprintf(
				'<span class="comment mw-comment-none">(%s)</span>',
				'checkuser-global-contributions-no-summary-available'
			),
			$formatted
		);
	}

	/**
	 * @dataProvider provideFormatUserLink
	 */
	public function testFormatAccountLink(
		array $expectedStrings,
		array $unexpectedStrings,
		string $username,
		bool $isDeleted
	): void {
		$row = $this->getRow( [
			'sourcewiki' => 'otherwiki',
			'rev_user' => 123,
			'rev_user_text' => $username,
			'rev_deleted' => $isDeleted ? '4' : '8'
		] );

		$services = $this->getServiceContainer();
		$pager = $this->getMockBuilder( GlobalContributionsPager::class )
			->onlyMethods( [ 'getForeignUrl' ] )
			->setConstructorArgs( [
				$services->getLinkRenderer(),
				$services->getLinkBatchFactory(),
				$services->getHookContainer(),
				$services->getRevisionStore(),
				$services->getNamespaceInfo(),
				$services->getCommentFormatter(),
				$services->getUserFactory(),
				$services->getTempUserConfig(),
				$services->get( 'CheckUserLookupUtils' ),
				$services->get( 'CentralIdLookup' ),
				$services->get( 'CheckUserApiRequestAggregator' ),
				$services->get( 'CheckUserGlobalContributionsLookup' ),
				$services->getPermissionManager(),
				$services->getPreferencesFactory(),
				$services->getDBLoadBalancerFactory(),
				$services->getJobQueueGroup(),
				$services->getStatsFactory(),
				RequestContext::getMain(),
				[ 'revisionsOnly' => true ],
				new UserIdentityValue( 0, '127.0.0.1' )
			] )
			->getMock();
		$pager->expects( $this->any() )
			->method( 'getForeignUrl' )
			->willReturnArgument( 1 );
		$pager = TestingAccessWrapper::newFromObject( $pager );
		$pager->currentPage = Title::makeTitle( 0, $row->page_title );

		$formatted = $pager->formatUserLink( $row );

		foreach ( $expectedStrings as $value ) {
			$this->assertStringContainsString( $value, $formatted );
		}

		foreach ( $unexpectedStrings as $value ) {
			$this->assertStringNotContainsString( $value, $formatted );
		}
	}

	public static function provideFormatUserLink() {
		return [
			'Temp account, hidden' => [
				'expectedStrings' => [ 'empty-username' ],
				'unexpectedStrings' => [
					'~2024-123',
					'mw-userlink',
					'mw-extuserlink',
					'mw-tempuserlink',
				],
				'username' => '~2024-123',
				'isDeleted' => true,
			],
			'Temp account, visible' => [
				'expectedStrings' => [
					'Special:Contributions/~2024-123',
					'mw-userlink',
					'mw-extuserlink',
					'mw-tempuserlink'
				],
				'unexpectedStrings' => [],
				'username' => '~2024-123',
				'isDeleted' => false,
			],
			'Registered account, hidden' => [
				'expectedStrings' => [ 'empty-username' ],
				'unexpectedStrings' => [
					'UnregisteredUser1',
					'mw-userlink',
					'mw-extuserlink',
					'mw-tempuserlink'
				],
				'username' => 'UnregisteredUser1',
				'isDeleted' => true,
			],
			'Registered account, visible' => [
				'expectedStrings' => [
					'User talk:UnregisteredUser1',
					'mw-userlink',
					'mw-extuserlink'
				],
				'unexpectedStrings' => [
					'mw-tempuserlink'
				],
				'username' => 'UnregisteredUser1',
				'isDeleted' => false,
			],
		];
	}

	/**
	 * @dataProvider provideFormatFlags
	 */
	public function testFormatFlags( $hasFlags ) {
		$row = $this->getRow( [
			'sourcewiki' => 'otherwiki',
			'rev_minor_edit' => $hasFlags ? '1' : '0',
			'rev_parent_id' => $hasFlags ? '0' : '1',
		] );
		$pager = $this->getWrappedPager( '127.0.0.1', $row->page_title );

		$flags = $pager->formatFlags( $row );
		if ( $hasFlags ) {
			$this->assertCount( 2, $flags );
		} else {
			$this->assertCount( 0, $flags );
		}
	}

	public static function provideFormatFlags() {
		return [ [ true ], [ false ] ];
	}

	public function testFormatVisibilityLink() {
		$row = $this->getRow( [ 'sourcewiki' => 'otherwiki' ] );
		$pager = $this->getWrappedPager( '127.0.0.1', $row->page_title );

		$formatted = $pager->formatVisibilityLink( $row );
		$this->assertSame( '', $formatted );
	}

	/**
	 * @dataProvider provideFormatTags
	 */
	public function testFormatTags( $hasTags ) {
		$row = $this->getRow( [
			'sourcewiki' => 'otherwiki',
			'ts_tags' => $hasTags ? 'sometag' : null
		] );
		$pager = $this->getPager( '127.0.0.1' );

		// We can't call formatTags directly because TestingAccessWrapper
		// can't pass by reference: T287318
		$formatted = $pager->formatRow( $row );
		if ( $hasTags ) {
			$this->assertStringContainsString( 'sometag', $formatted );
		} else {
			$this->assertStringNotContainsString( 'sometag', $formatted );
		}
	}

	public static function provideFormatTags() {
		return [ [ true ], [ false ] ];
	}

	/**
	 * @dataProvider provideExternalWikiPermissions
	 */
	public function testExternalWikiPermissions( $permissions, $expectedCount ) {
		$localWiki = WikiMap::getCurrentWikiId();
		$externalWiki = 'otherwiki';

		// Mock fetching the recently active wikis
		$globalContributionsLookup = $this->createMock( CheckUserGlobalContributionsLookup::class );
		$globalContributionsLookup->method( 'getActiveWikis' )
			->willReturn( [ $localWiki, $externalWiki ] );

			// Mock making the permission API call
		$apiRequestAggregator = $this->createMock( CheckUserApiRequestAggregator::class );
		$apiRequestAggregator->method( 'execute' )
			->willReturn( [
				$externalWiki => [
					'query' => [
						'pages' => [
							[
								'actions' => $permissions,
							],
						],
					],
				],
			] );

		$pager = $this->getPagerWithOverrides( [
			'RequestAggregator' => $apiRequestAggregator,
			'GlobalContributionsLookup' => $globalContributionsLookup
		] );
		$pager = TestingAccessWrapper::newFromObject( $pager );
		$wikis = $pager->fetchWikisToQuery();

		$this->assertCount( $expectedCount, $wikis );
		$this->assertArrayHasKey( $externalWiki, $pager->permissions );
		$this->assertSame( array_keys( $permissions ), array_keys( $pager->permissions[$externalWiki] ) );
	}

	public static function provideExternalWikiPermissions() {
		return [
			'Can always reveal IP at external wiki' => [
				'actions' => [
					'checkuser-temporary-account' => [ 'error' ],
					'checkuser-temporary-account-no-preference' => [],
				],
				1,
			],
			'Can reveal IP at external wiki with preference' => [
				'actions' => [
					'checkuser-temporary-account' => [],
					'checkuser-temporary-account-no-preference' => [ 'error' ],
				],
				0,
			],
			'Can not reveal IP at external wiki' => [
				'actions' => [
					'checkuser-temporary-account' => [ 'error' ],
					'checkuser-temporary-account-no-preference' => [ 'error' ],
				],
				0,
			]
		];
	}

	public function testExternalWikiPermissionsNotCheckedForUser() {
		$localWiki = WikiMap::getCurrentWikiId();
		$externalWiki = 'otherwiki';

		// Mock fetching the recently active wikis
		$globalContributionsLookup = $this->createMock( CheckUserGlobalContributionsLookup::class );
		$globalContributionsLookup->method( 'getActiveWikis' )
			->willReturn( [ $localWiki, $externalWiki ] );

		// Ensure the permission API call is not made
		$apiRequestAggregator = $this->createNoOpMock( CheckUserApiRequestAggregator::class );

		// Mock the central user exists
		$centralIdLookup = $this->createMock( CentralIdLookup::class );
		$centralIdLookup->method( 'centralIdFromName' )
			->willReturn( 45678 );

		$pager = $this->getPagerWithOverrides( [
			'CentralIdLookup' => $centralIdLookup,
			'RequestAggregator' => $apiRequestAggregator,
			'GlobalContributionsLookup' => $globalContributionsLookup,
			'UserName' => 'SomeUser',
		] );
		$pager = TestingAccessWrapper::newFromObject( $pager );
		$wikis = $pager->fetchWikisToQuery();

		$this->assertCount( 2, $wikis );
		$this->assertSame( [], $pager->permissions );
	}

	/**
	 * @dataProvider provideQueryData
	 *
	 * @param IResultWrapper[] $resultsByWiki Map of result sets keyed by wiki ID
	 * @param string[] $paginationParams The pagination parameters to set on the pager
	 * @param int $expectedCount The expected number of rows in the result set
	 * @param array|false $expectedPrevQuery The expected query parameters for the 'prev' page,
	 * or `false` if there is no previous page
	 * @param array|false $expectedNextQuery The expected query parameters for the 'next' page,
	 * or `false` if there is no next page
	 */
	public function testQuery(
		array $resultsByWiki,
		array $paginationParams,
		int $expectedCount,
		$expectedPrevQuery,
		$expectedNextQuery
	): void {
		$wikiIds = array_keys( $resultsByWiki );

		// Mock fetching the recently active wikis
		$globalContributionsLookup = $this->createMock( CheckUserGlobalContributionsLookup::class );
		$globalContributionsLookup->method( 'getActiveWikis' )
			->willReturn( $wikiIds );

		$checkUserDb = $this->createMock( IReadableDatabase::class );
		$dbMap = [
			[ CheckUserQueryInterface::VIRTUAL_GLOBAL_DB_DOMAIN, null, $checkUserDb ],
		];

		foreach ( $resultsByWiki as $wikiId => $result ) {
			$localQueryBuilder = $this->createMock( SelectQueryBuilder::class );
			$localQueryBuilder->method( $this->logicalNot( $this->equalTo( 'fetchResultSet' ) ) )
				->willReturnSelf();
			$localQueryBuilder->method( 'fetchResultSet' )
				->willReturn( $result );

			$localDb = $this->createMock( IReadableDatabase::class );
			$localDb->method( 'newSelectQueryBuilder' )
				->willReturn( $localQueryBuilder );

			$dbMap[] = [ $wikiId, null, $localDb ];
		}

		$dbProvider = $this->createMock( IConnectionProvider::class );
		$dbProvider->method( 'getReplicaDatabase' )
			->willReturnMap( $dbMap );

		// Mock making the permission API call
		$permsByWiki = array_fill_keys(
			$wikiIds,
			[
				'query' => [
					'pages' => [
						[
							'actions' => [
								'checkuser-temporary-account' => [ 'error' ],
								'checkuser-temporary-account-no-preference' => [],
							]
						],
					],
				],
			],
		);
		$apiRequestAggregator = $this->createMock( CheckUserApiRequestAggregator::class );
		$apiRequestAggregator->method( 'execute' )
			->willReturn( $permsByWiki );

		// Since this pager calls out to other wikis, extension hooks should not be run
		// because the extension may not be loaded on the external wiki (T385092).
		$hookContainer = $this->createMock( HookContainer::class );
		$hookContainer->expects( $this->never() )
			->method( 'run' );

		$pager = $this->getPagerWithOverrides( [
			'HookContainer' => $hookContainer,
			'RequestAggregator' => $apiRequestAggregator,
			'LoadBalancerFactory' => $dbProvider,
			'GlobalContributionsLookup' => $globalContributionsLookup,
		] );
		$pager->mIsBackwards = ( $paginationParams['dir'] ?? '' ) === 'prev';
		$pager->setLimit( $paginationParams['limit'] );
		$pager->setOffset( $paginationParams['offset'] ?? '' );

		$pager->doQuery();

		$pagingQueries = $pager->getPagingQueries();
		$result = $pager->getResult();

		$this->assertSame( $expectedCount, $result->numRows() );
		$this->assertSame( $expectedPrevQuery, $pagingQueries['prev'] );
		$this->assertSame( $expectedNextQuery, $pagingQueries['next'] );
		$this->assertApiLookupErrorCount( 0 );
	}

	public static function provideQueryData(): iterable {
		$testResults = [
			'testwiki' => self::makeMockResult( [
				'20250110000000',
				'20250107000000',
				'20250108000000',
			] ),
			'otherwiki' => self::makeMockResult( [
				'20250109000000',
				'20250108000000',
			] )
		];

		yield '5 rows, limit=4, first page' => [
			$testResults,
			[ 'limit' => 4 ],
			// 4 rows shown + 1 row for the next page link
			5,
			false,
			[ 'offset' => '20250108000000|-1|1', 'limit' => 4 ],
		];

		yield '5 rows, limit=4, second page' => [
			$testResults,
			[ 'offset' => '20250108000000|-1|1', 'limit' => 4 ],
			1,
			[ 'dir' => 'prev', 'offset' => '20250107000000|0|1', 'limit' => 4 ],
			false,
		];

		yield '5 rows, limit=4, backwards from second page' => [
			$testResults,
			[ 'dir' => 'prev', 'offset' => '20250107000000|0|1', 'limit' => 4 ],
			4,
			false,
			[ 'offset' => '20250108000000|-1|1', 'limit' => 4 ],
		];

		$resultsWithIdenticalTimestamps = [
			'testwiki' => self::makeMockResult( [
				'20250108000000',
				'20250108000000',
			] ),
			'otherwiki' => self::makeMockResult( [
				'20250108000000',
			] )
		];

		yield '3 rows, identical timestamps, limit=2, first page' => [
			$resultsWithIdenticalTimestamps,
			[ 'limit' => 2 ],
			// 2 rows shown + 1 row for the next page link
			3,
			false,
			[ 'offset' => '20250108000000|0|1', 'limit' => 2 ],
		];

		yield '3 rows, identical timestamps, limit=2, second page' => [
			$resultsWithIdenticalTimestamps,
			[ 'offset' => '20250108000000|0|1', 'limit' => 2 ],
			1,
			[ 'dir' => 'prev', 'offset' => '20250108000000|-1|1', 'limit' => 2 ],
			false,
		];

		yield '3 rows, identical timestamps, limit=2, backwards from second page' => [
			$resultsWithIdenticalTimestamps,
			[ 'dir' => 'prev', 'offset' => '20250108000000|-1|1', 'limit' => 2 ],
			2,
			false,
			[ 'offset' => '20250108000000|0|1', 'limit' => 2 ],
		];
	}

	/**
	 * Convenience function to create an ordered result set of mock revision data
	 * with the specified timestamps.
	 *
	 * @param string[] $timestamps The MW timestamps of the revisions.
	 * @return IResultWrapper
	 */
	private static function makeMockResult( array $timestamps ): IResultWrapper {
		$rows = [];
		$revId = count( $timestamps );

		// Sort the timestamps in descending order, since the DB would sort the revisions in the same way.
		usort( $timestamps, static fn ( string $ts, string $other ): int => $other <=> $ts );

		foreach ( $timestamps as $timestamp ) {
			$rows[] = (object)[
				'rev_id' => $revId--,
				'rev_page' => '1',
				'rev_actor' => '1',
				'rev_user' => '1',
				'rev_user_text' => '~2024-123',
				'rev_timestamp' => $timestamp,
				'rev_minor_edit' => '0',
				'rev_deleted' => '0',
				'rev_len' => '100',
				'rev_parent_id' => '1',
				'rev_sha1' => '',
				'rev_comment_text' => '',
				'rev_comment_data' => null,
				'rev_comment_cid' => '1',
				'page_latest' => '2',
				'page_is_new' => '0',
				'page_namespace' => '0',
				'page_title' => 'Test page',
				'cuc_timestamp' => $timestamp,
				'ts_tags' => null,
			];
		}

		return new FakeResultWrapper( $rows );
	}

	public function testBodyIsWrappedWithPlainlinksClass(): void {
		$localWiki = WikiMap::getCurrentWikiId();
		$externalWiki = 'otherwiki';

		// Mock fetching the recently active wikis
		$queryBuilder = $this->createMock( SelectQueryBuilder::class );
		$queryBuilder
			->method(
				$this->logicalOr(
					'select', 'from', 'distinct', 'where', 'andWhere',
					'join', 'orderBy', 'limit', 'queryInfo', 'caller'
				)
			)->willReturnSelf();
		$queryBuilder
			->method( 'fetchFieldValues' )
			->willReturn( [ $localWiki, $externalWiki ] );

		$database = $this->createMock( IReadableDatabase::class );
		$database
			->method( 'newSelectQueryBuilder' )
			->willreturn( $queryBuilder );

		$dbProvider = $this->createMock( IConnectionProvider::class );
		$dbProvider
			->method( 'getReplicaDatabase' )
			->willReturn( $database );

		// Since this pager calls out to other wikis, extension hooks should not be run
		// because the extension may not be loaded on the external wiki (T385092).
		$hookContainer = $this->createMock( HookContainer::class );
		$hookContainer
			->expects( $this->never() )
			->method( 'run' );

		$pager = $this->getPagerWithOverrides( [
			'HookContainer' => $hookContainer,
			'RequestAggregator' => $this->createMock( CheckUserApiRequestAggregator::class ),
			'LoadBalancerFactory' => $dbProvider,
		] );

		$pager = TestingAccessWrapper::newFromObject( $pager );
		$pager->currentPage = Title::makeTitle( 0, 'Test page' );
		$pager->currentRevRecord = null;
		$pager->needsToEnableGlobalPreferenceAtWiki = false;
		$pager->externalApiLookupError = false;

		$this->assertSame(
			"<section class=\"mw-pager-body plainlinks\">\n",
			$pager->getStartBody()
		);
		$this->assertSame(
			"</section>\n",
			$pager->getEndBody()
		);
	}

	public function testShouldInstrumentForeignApiLookupErrors(): void {
		// Mock fetching the recently active wikis
		$globalContributionsLookup = $this->createMock( CheckUserGlobalContributionsLookup::class );
		$globalContributionsLookup->method( 'getActiveWikis' )
			->willReturn( [ 'testwiki' ] );

		$checkUserDb = $this->createMock( IReadableDatabase::class );

		$localQueryBuilder = $this->createMock( SelectQueryBuilder::class );
		$localQueryBuilder->method( $this->logicalNot( $this->equalTo( 'fetchResultSet' ) ) )
			->willReturnSelf();
		$localQueryBuilder->method( 'fetchResultSet' )
			->willReturn( new FakeResultWrapper( [] ) );

		$localDb = $this->createMock( IReadableDatabase::class );
		$localDb->method( 'newSelectQueryBuilder' )
			->willReturn( $localQueryBuilder );

		$dbProvider = $this->createMock( IConnectionProvider::class );
		$dbProvider->method( 'getReplicaDatabase' )
			->willReturnMap( [
				[ CheckUserQueryInterface::VIRTUAL_GLOBAL_DB_DOMAIN, null, $checkUserDb ],
				[ 'testwiki', null, $localDb ]
			] );

		// Mock a failed permission API call
		$permsByWiki = [
			'testwiki' => [
				'error' => true,
			],
		];

		$apiRequestAggregator = $this->createMock( CheckUserApiRequestAggregator::class );
		$apiRequestAggregator->method( 'execute' )
			->willReturn( $permsByWiki );

		// Since this pager calls out to other wikis, extension hooks should not be run
		// because the extension may not be loaded on the external wiki (T385092).
		$hookContainer = $this->createNoOpMock( HookContainer::class );

		$pager = $this->getPagerWithOverrides( [
			'HookContainer' => $hookContainer,
			'RequestAggregator' => $apiRequestAggregator,
			'LoadBalancerFactory' => $dbProvider,
			'GlobalContributionsLookup' => $globalContributionsLookup,
		] );
		$pager->doQuery();

		$this->assertApiLookupErrorCount( 1 );
	}

	/**
	 * Convenience function to assert that the API lookup error counter metric has a given count.
	 *
	 * @param int $expectedCount
	 * @return void
	 */
	private function assertApiLookupErrorCount( int $expectedCount ): void {
		$counter = $this->getServiceContainer()
			->getStatsFactory()
			->getCounter( GlobalContributionsPager::API_LOOKUP_ERROR_METRIC_NAME );

		$sampleValues = array_map( static fn ( $sample ) => $sample->getValue(), $counter->getSamples() );

		$this->assertSame( $expectedCount, $counter->getSampleCount() );
		$this->assertSame(
			(float)$expectedCount,
			(float)array_sum( $sampleValues )
		);
	}
}
