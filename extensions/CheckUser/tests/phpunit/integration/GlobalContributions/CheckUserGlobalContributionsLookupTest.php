<?php

namespace MediaWiki\CheckUser\Tests\Integration\GlobalContributions;

use MediaWiki\CheckUser\GlobalContributions\CheckUserApiRequestAggregator;
use MediaWiki\CheckUser\GlobalContributions\CheckUserGlobalContributionsLookup;
use MediaWiki\CheckUser\Jobs\UpdateUserCentralIndexJob;
use MediaWiki\CheckUser\Tests\Integration\CheckUserTempUserTestTrait;
use MediaWiki\Context\RequestContext;
use MediaWiki\MainConfigNames;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\User\CentralId\CentralIdLookup;
use MediaWiki\User\User;
use MediaWiki\WikiMap\WikiMap;
use MediaWikiIntegrationTestCase;
use Wikimedia\ObjectCache\WANObjectCache;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \MediaWiki\CheckUser\GlobalContributions\CheckUserGlobalContributionsLookup
 * @group CheckUser
 * @group Database
 */
class CheckUserGlobalContributionsLookupTest extends MediaWikiIntegrationTestCase {

	use CheckUserTempUserTestTrait;
	use MockAuthorityTrait;

	private static User $tempUser1;
	private static User $tempUser2;
	private static User $tempUser3;
	private static User $tempUser4;

	protected function setUp(): void {
		parent::setUp();

		$this->markTestSkippedIfExtensionNotLoaded( 'CentralAuth' );
		$this->enableAutoCreateTempUser();

		// Avoid holding onto stale service references
		self::$tempUser1->clearInstanceCache();
		self::$tempUser2->clearInstanceCache();
		self::$tempUser3->clearInstanceCache();

		// We don't want to test specifically the CentralAuth implementation of the CentralIdLookup. As such, force it
		// to be the local provider.
		$this->overrideConfigValue( MainConfigNames::CentralIdLookupProvider, 'local' );
	}

	public function addDBDataOnce() {
		$this->markTestSkippedIfExtensionNotLoaded( 'CentralAuth' );
		$this->enableAutoCreateTempUser();

		// The users must be created now because the actor table will
		// be altered when the edits are made, and added to the list
		// of tables that can't be altered again in $dbDataOnceTables.
		self::$tempUser1 = $this->getServiceContainer()
			->getTempUserCreator()
			->create( '~check-user-test-2024-01', new FauxRequest() )->getUser();
		self::$tempUser2 = $this->getServiceContainer()
			->getTempUserCreator()
			->create( '~check-user-test-2024-02', new FauxRequest() )->getUser();
		self::$tempUser3 = $this->getServiceContainer()
			->getTempUserCreator()
			->create( '~check-user-test-2024-03', new FauxRequest() )->getUser();
		self::$tempUser4 = $this->getServiceContainer()
			->getTempUserCreator()
			->create( '~check-user-test-2024-04', new FauxRequest() )->getUser();

		$page = $this->getNonexistingTestPage();

		// Make edits from temp accounts 1 and 2 from the same IP
		RequestContext::getMain()->getRequest()->setIP( '127.0.0.1' );
		$this->editPage(
			'Test page', 'Test Content 1', 'test', NS_MAIN, self::$tempUser1
		);
		$this->editPage(
			'Test page', 'Test Content 2', 'test', NS_MAIN, self::$tempUser2
		);

		// From a new IP, make an edit from temp account 2
		RequestContext::getMain()->getRequest()->setIP( '127.0.0.2' );
		$this->editPage(
			'Test page', 'Test Content 3', 'test', NS_MAIN, self::$tempUser2
		);

		// Make an edit from temp account 3 and the registered user on a new IP
		RequestContext::getMain()->getRequest()->setIP( '127.0.0.3' );
		$this->editPage(
			'Test page', 'Test Content 4', 'test', NS_MAIN, self::$tempUser3
		);

		// Make an edit that will have its author hidden
		$status = $this->editPage(
			'Test page', 'Test Content 5', 'test', NS_MAIN, self::$tempUser4
		);
		$revId = $status->getNewRevision()->getId();

		$this->runJobs( [ 'minJobs' => 0 ], [ 'type' => UpdateUserCentralIndexJob::TYPE ] );

		$this->getDb()->newUpdateQueryBuilder()
			->update( 'revision' )
			->set( [ 'rev_deleted' => RevisionRecord::DELETED_USER ] )
			->where( [ 'rev_id' => $revId ] )
			->execute();
	}

	private function getObjectUnderTest(): CheckUserGlobalContributionsLookup {
		$services = $this->getServiceContainer();
		return new CheckUserGlobalContributionsLookup(
			$services->getConnectionProvider(),
			$services->get( 'ExtensionRegistry' ),
			$services->get( 'CentralIdLookup' ),
			$services->get( 'CheckUserLookupUtils' ),
			$services->getMainConfig(),
			$services->getRevisionStore(),
			$services->get( 'CheckUserApiRequestAggregator' ),
			$services->getMainWANObjectCache(),
			$services->getStatsFactory()
		);
	}

	/** @dataProvider provideTestGetGlobalContributionCount */
	public function testGetGlobalContributionCount( $targetProvider, $expectedCount ) {
		$lookup = $this->getObjectUnderTest();
		$authority = RequestContext::getMain()->getAuthority();

		$this->assertSame(
			$expectedCount,
			$lookup->getGlobalContributionsCount( $targetProvider(), $authority )
		);
	}

	public static function provideTestGetGlobalContributionCount() {
		return [
			'IP used by 2 temp accounts' => [
				'target' => static fn () => '127.0.0.1', 'expectedCount' => 2,
			],
			'temp account that edited from an IP used by another temp account' => [
				'target' => static fn () => self::$tempUser1->getName(), 'expectedCount' => 1,
			],
			'temp account that edited from 2 IPs' => [
				'target' => static fn () => self::$tempUser2->getName(), 'expectedCount' => 2,
			],
			'temp account that edited from an IP used by another registered account' => [
				'target' => static fn () => self::$tempUser3->getName(), 'expectedCount' => 1,
			],
		];
	}

	public function testExternalWikiPermissionsCache() {
		// Mock the user has a central id
		$centralIdLookup = $this->createMock( CentralIdLookup::class );
		$centralIdLookup->method( 'centralIdFromLocalUser' )
			->willReturn( 1 );

		// Mock making the permission API call
		$wikiIds = [ 'otherwiki', 'otherwiki2' ];
		$permsByWiki = array_fill_keys(
			$wikiIds,
			[
				'query' => [
					'pages' => [
						[
							'actions' => [
								'checkuser-temporary-account' => [ 'error' ],
								'checkuser-temporary-account-no-preference' => [],
							],
						],
					],
				],
			],
		);
		$apiRequestAggregator = $this->createMock( CheckUserApiRequestAggregator::class );
		$apiRequestAggregator
			->expects( $this->exactly( 3 ) )
			->method( 'execute' )
			->willReturn( $permsByWiki );

		$services = $this->getServiceContainer();
		$store = $services->getObjectCacheFactory()->getLocalClusterInstance();
		$cache = new WANObjectCache( [ 'cache' => $store ] );

		$lookup = new CheckUserGlobalContributionsLookup(
			$services->getConnectionProvider(),
			$services->get( 'ExtensionRegistry' ),
			$centralIdLookup,
			$services->get( 'CheckUserLookupUtils' ),
			$services->getMainConfig(),
			$services->getRevisionStore(),
			$apiRequestAggregator,
			$cache,
			$services->getStatsFactory()
		);
		$lookup = TestingAccessWrapper::newFromObject( $lookup );

		// Assert no hits or misses on the cache have been counted
		$this->assertMetricCount( CheckUserGlobalContributionsLookup::EXTERNAL_PERMISSIONS_CACHE_MISS_METRIC_NAME, 0 );
		$this->assertMetricCount( CheckUserGlobalContributionsLookup::EXTERNAL_PERMISSIONS_CACHE_HIT_METRIC_NAME, 0 );

		// Get and set the value in the cache
		$permissions = $lookup->getAndUpdateExternalWikiPermissions(
			1,
			[ 'otherwiki' ],
			$this->getTestUser()->getUser(),
			new FauxRequest()
		);

		$this->assertTrue( $permissions->hasPermission( 'checkuser-temporary-account-no-preference', 'otherwiki' ) );
		$this->assertFalse( $permissions->hasPermission( 'checkuser-temporary-account', 'otherwiki' ) );
		$this->assertSame( [ 'otherwiki' ], $permissions->getKnownWikis() );

		// Expect only the cache miss counter to increment
		$this->assertMetricCount( CheckUserGlobalContributionsLookup::EXTERNAL_PERMISSIONS_CACHE_MISS_METRIC_NAME, 1 );
		$this->assertMetricCount( CheckUserGlobalContributionsLookup::EXTERNAL_PERMISSIONS_CACHE_HIT_METRIC_NAME, 0 );

		// Re-run the function, expecting that the API aggregator will not execute again
		$lookup->getAndUpdateExternalWikiPermissions(
			1,
			[ 'otherwiki' ],
			$this->getTestUser()->getUser(),
			new FauxRequest()
		);

		// Expect only the cache hit counter to increment
		$this->assertMetricCount( CheckUserGlobalContributionsLookup::EXTERNAL_PERMISSIONS_CACHE_MISS_METRIC_NAME, 1 );
		$this->assertMetricCount( CheckUserGlobalContributionsLookup::EXTERNAL_PERMISSIONS_CACHE_HIT_METRIC_NAME, 1 );

		// Run the function with a different set of active wikis, expecting a cache miss
		$lookup->getAndUpdateExternalWikiPermissions(
			1,
			[ 'otherwiki2' ],
			$this->getTestUser()->getUser(),
			new FauxRequest()
		);

		$this->assertMetricCount( CheckUserGlobalContributionsLookup::EXTERNAL_PERMISSIONS_CACHE_MISS_METRIC_NAME, 2 );
		$this->assertMetricCount( CheckUserGlobalContributionsLookup::EXTERNAL_PERMISSIONS_CACHE_HIT_METRIC_NAME, 1 );

		// Invalidate the checkKey and expect cache miss
		$checkKey = $cache->makeGlobalKey(
			'globalcontributions-ext-permissions',
			1
		);
		$time = time() + 1000;
		$cache->setMockTime( $time );
		$cache->touchCheckKey( $checkKey );
		$permissions = $lookup->getAndUpdateExternalWikiPermissions(
			1,
			[ 'otherwiki' ],
			$this->getTestUser()->getUser(),
			new FauxRequest()
		);
		$this->assertMetricCount( CheckUserGlobalContributionsLookup::EXTERNAL_PERMISSIONS_CACHE_MISS_METRIC_NAME, 3 );
		$this->assertMetricCount( CheckUserGlobalContributionsLookup::EXTERNAL_PERMISSIONS_CACHE_HIT_METRIC_NAME, 1 );
	}

	public function testShouldInstrumentForeignApiLookupErrors(): void {
		// Mock the user has a central id
		$centralIdLookup = $this->createMock( CentralIdLookup::class );
		$centralIdLookup->method( 'centralIdFromLocalUser' )
			->willReturn( 1 );

			// Mock a failed permission API call
		$permsByWiki = [
			'testwiki' => [
				'error' => true,
			],
		];
		$apiRequestAggregator = $this->createMock( CheckUserApiRequestAggregator::class );
		$apiRequestAggregator->method( 'execute' )
			->willReturn( $permsByWiki );

		$services = $this->getServiceContainer();
		$lookup = new CheckUserGlobalContributionsLookup(
			$services->getConnectionProvider(),
			$services->get( 'ExtensionRegistry' ),
			$centralIdLookup,
			$services->get( 'CheckUserLookupUtils' ),
			$services->getMainConfig(),
			$services->getRevisionStore(),
			$apiRequestAggregator,
			$services->getMainWANObjectCache(),
			$services->getStatsFactory()
		);
		$lookup = TestingAccessWrapper::newFromObject( $lookup );

		// Get and set the value in the cache
		$permissions = $lookup->getAndUpdateExternalWikiPermissions(
			1,
			[ 'testwiki' ],
			$this->getTestUser()->getUser(),
			new FauxRequest()
		);

		$this->assertMetricCount( CheckUserGlobalContributionsLookup::API_LOOKUP_ERROR_METRIC_NAME, 1 );
	}

	/** @dataProvider provideGetActiveWikis */
	public function testGetActiveWikis( $target ) {
		$lookup = $this->getObjectUnderTest();

		$activeWikisAllTime = $lookup->getActiveWikis(
			$target(),
			$this->mockAnonUltimateAuthority()
		);
		$this->assertArrayEquals( [ WikiMap::getCurrentWikiId() ], $activeWikisAllTime );

		$activeWikisRecent = $lookup->getActiveWikis(
			$target(),
			$this->mockAnonUltimateAuthority(),
			// addDBDataOnce makes edits with current date, so we need a reliable future date here
			// to ensure we don't capture anything
			'99990101000000'
		);
		$this->assertArrayEquals( [], $activeWikisRecent );
	}

	public static function provideGetActiveWikis() {
		return [
			'Account' => [
				'target' => static fn () => self::$tempUser1->getName(),
			],
			'IP address' => [
				'target' => static fn () => '127.0.0.3',
			],
		];
	}

	public function testGetActiveWikisVisibleToUser() {
		$lookup = $this->getObjectUnderTest();

		$activeWikisAllTime = $lookup->getActiveWikisVisibleToUser(
			self::$tempUser1->getName(),
			$this->mockAnonNullAuthority(),
			new FauxRequest()
		);
		$this->assertArrayEquals( [ WikiMap::getCurrentWikiId() ], $activeWikisAllTime );

		$activeWikisRecent = $lookup->getActiveWikisVisibleToUser(
			self::$tempUser1->getName(),
			$this->mockAnonNullAuthority(),
			new FauxRequest(),
			// addDBDataOnce makes edits with current date, so we need a reliable future date here
			// to ensure we don't capture anything
			'99990101000000'
		);
		$this->assertArrayEquals(
			[], $activeWikisRecent,
			false, false,
			'No wikis should be active for future cutoff'
		);

		$activeWikisForHiddenAuthor = $lookup->getActiveWikisVisibleToUser(
			self::$tempUser4->getName(),
			$this->mockAnonNullAuthority(),
			new FauxRequest()
		);
		$this->assertArrayEquals(
			[], $activeWikisForHiddenAuthor,
			false, false,
			'Wiki with only rev-deleted author should not be included'
		);

		$activeWikisForHiddenAuthorElevated = $lookup->getActiveWikisVisibleToUser(
			self::$tempUser4->getName(),
			$this->mockAnonUltimateAuthority(),
			new FauxRequest()
		);
		$this->assertArrayEquals(
			[ WikiMap::getCurrentWikiId() ], $activeWikisForHiddenAuthorElevated,
			false, false,
			'Wiki with only rev-deleted author should not be included'
		);
	}

	/**
	 * Convenience function to assert that a stats counter metric has a given count.
	 *
	 * @param string $metricName
	 * @param int $expectedCount
	 * @return void
	 */
	private function assertMetricCount( string $metricName, int $expectedCount ): void {
		$counter = $this->getServiceContainer()
			->getStatsFactory()
			->getCounter( $metricName );

		$sampleValues = array_map( static fn ( $sample ) => $sample->getValue(), $counter->getSamples() );

		$this->assertSame( $expectedCount, $counter->getSampleCount() );
		$this->assertSame(
			(float)$expectedCount,
			(float)array_sum( $sampleValues )
		);
	}
}
