<?php

namespace MediaWiki\CheckUser\Tests\Unit\GlobalContributions;

use InvalidArgumentException;
use LogicException;
use MediaWiki\CheckUser\CheckUserQueryInterface;
use MediaWiki\CheckUser\GlobalContributions\CheckUserApiRequestAggregator;
use MediaWiki\CheckUser\GlobalContributions\CheckUserGlobalContributionsLookup;
use MediaWiki\CheckUser\Services\CheckUserLookupUtils;
use MediaWiki\Config\Config;
use MediaWiki\Config\HashConfig;
use MediaWiki\Permissions\Authority;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\User\CentralId\CentralIdLookup;
use MediaWikiUnitTestCase;
use Wikimedia\ObjectCache\WANObjectCache;
use Wikimedia\Rdbms\FakeResultWrapper;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IExpression;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\SelectQueryBuilder;
use Wikimedia\Stats\StatsFactory;

/**
 * @covers \MediaWiki\CheckUser\GlobalContributions\CheckUserGlobalContributionsLookup
 * @group CheckUser
 */
class CheckUserGlobalContributionsLookupTest extends MediaWikiUnitTestCase {
	/**
	 * @param array $overrides Allow tests to stub out services as necessary
	 * @return CheckUserGlobalContributionsLookup
	 */
	private function getLookupWithOverrides( $overrides ) {
		$extensionRegistry = $this->createMock( ExtensionRegistry::class );
		$extensionRegistry->method( 'isLoaded' )
			->willReturn( true );

		return new CheckUserGlobalContributionsLookup(
			$overrides['dbProvider'] ?? $this->createMock( IConnectionProvider::class ),
			$overrides['extensionRegistry'] ?? $extensionRegistry,
			$overrides['centralIdLookup'] ?? $this->createMock( CentralIdLookup::class ),
			$overrides['checkUserLookupUtils'] ?? $this->createMock( CheckUserLookupUtils::class ),
			$overrides['config'] ?? $this->createMock( Config::class ),
			$overrides['revisionStore'] ?? $this->createMock( RevisionStore::class ),
			$overrides['apiRequestAggregator'] ?? $this->createMock( CheckUserApiRequestAggregator::class ),
			$overrides['wanCache'] ?? $this->createMock( WANObjectCache::class ),
			$overrides['statsFactory'] ?? $this->createMock( StatsFactory::class )
		);
	}

	/**
	 * Convenience function to get a database with an expected query result
	 *
	 * @param string[] $activeWikis array of wikis to return as the query result
	 * @return IConnectionProvider
	 */
	private function getMockDbProviderWithActiveWikiLookupResults( $activeWikis ) {
		// Mock fetching the recently active wikis
		$queryBuilder = $this->createMock( SelectQueryBuilder::class );
		$queryBuilder
			->method( $this->logicalOr(
				'select', 'from', 'distinct', 'where', 'join', 'caller', 'orderBy', 'groupBy'
			) )
			->willReturnSelf();
		$queryBuilder->method( 'fetchResultSet' )
			->willReturn( new FakeResultWrapper( array_map(
				static fn ( $wiki ) => [ 'ciwm_wiki' => $wiki, 'timestamp' => 'unused' ],
				$activeWikis
			) ) );

		$database = $this->createMock( IReadableDatabase::class );
		$database->method( 'newSelectQueryBuilder' )
			->willreturn( $queryBuilder );

		$dbProvider = $this->createMock( IConnectionProvider::class );
		$dbProvider->method( 'getReplicaDatabase' )
			->willReturn( $database );

		$dbProvider->expects( $this->once() )
			->method( 'getReplicaDatabase' )
			->with( CheckUserQueryInterface::VIRTUAL_GLOBAL_DB_DOMAIN );

		return $dbProvider;
	}

	public function testCheckCentralAuthEnabledNotEnabled() {
		$this->expectException( LogicException::class );

		$extensionRegistry = $this->createMock( ExtensionRegistry::class );
		$extensionRegistry->method( 'isLoaded' )
			->willReturn( false );

		$lookup = $this->getLookupWithOverrides( [
			'extensionRegistry' => $extensionRegistry,
		] );
		$lookup->checkCentralAuthEnabled();
	}

	public function testGetActiveWikisForIP() {
		$activeWikis = [ 'testwiki' ];

		$checkUserLookupUtils = $this->createMock( CheckUserLookupUtils::class );
		$checkUserLookupUtils->method( 'getIPTargetExprForColumn' )
			->willReturn( $this->createMock( IExpression::class ) );

		$lookup = $this->getLookupWithOverrides( [
			'dbProvider' => $this->getMockDbProviderWithActiveWikiLookupResults( $activeWikis ),
			'checkUserLookupUtils' => $checkUserLookupUtils,
		] );
		$result = $lookup->getActiveWikis( '1.2.3.4', $this->createMock( Authority::class ) );
		$this->assertSame( array_keys( $activeWikis ), array_keys( $result ) );
	}

	public function testGetActiveWikisForIPRangeOutOfBounds() {
		$this->expectException( LogicException::class );

		// Mock the return from misconfiguration between RangeContributionsCIDRLimit and CheckUserCIDRLimit
		$checkUserLookupUtils = $this->createMock( CheckUserLookupUtils::class );
		$checkUserLookupUtils->method( 'getIPTargetExprForColumn' )
			->willReturn( null );
		$activeWikis = [ 'testwiki' ];

		$config = new HashConfig( [
			'RangeContributionsCIDRLimit' => [
				'IPv4' => 16,
				'IPv6' => 16,
			],
		] );

		$lookup = $this->getLookupWithOverrides( [
			'checkUserLookupUtils' => $checkUserLookupUtils,
			'config' => $config,
		] );
		$result = $lookup->getActiveWikis( '1.2.3.4/24', $this->createMock( Authority::class ) );
	}

	public function testGetActiveWikisForUserWithCentralId() {
		$activeWikis = [ 'testwiki' ];

		// Mock a return for the central id lookup
		$centralIdLookup = $this->createMock( CentralIdLookup::class );
		$centralIdLookup->method( 'centralIdFromName' )
			->willReturn( 1 );

		$lookup = $this->getLookupWithOverrides( [
			'dbProvider' => $this->getMockDbProviderWithActiveWikiLookupResults( $activeWikis ),
			'centralIdLookup' => $centralIdLookup,
		] );
		$result = $lookup->getActiveWikis( 'User', $this->createMock( Authority::class ) );
		$this->assertSame( array_keys( $activeWikis ), array_keys( $result ) );
	}

	public function testGetActiveWikisNoCentralId() {
		$this->expectException( InvalidArgumentException::class );
		$result = $this->getLookupWithOverrides( [] )
			->getActiveWikis( 'Unknown User', $this->createMock( Authority::class ) );
	}

	public function testGgetAnonymousUserGlobalContributionCountForIPRangeOutOfBounds() {
		$this->expectException( LogicException::class );

		// Mock the return from misconfiguration between RangeContributionsCIDRLimit and CheckUserCIDRLimit
		$checkUserLookupUtils = $this->createMock( CheckUserLookupUtils::class );
		$checkUserLookupUtils->method( 'getIPTargetExprForColumn' )
			->willReturn( null );

		$lookup = $this->getLookupWithOverrides( [
			'checkUserLookupUtils' => $checkUserLookupUtils,
		] );
		$result = $lookup->getAnonymousUserGlobalContributionCount( '1.2.3.4/24', [ 'testwiki' ] );
	}

	public function testGetRevisionSizesShouldDoNothingForEmptyList(): void {
		$dbProvider = $this->createNoOpMock( IConnectionProvider::class );
		$lookup = $this->getLookupWithOverrides( [ 'dbProvider' => $dbProvider ] );

		$lookup->getRevisionSizes( 'testwiki', [] );
	}

	public function testGetRevisionSizesShouldReturnMapOfRevisionSizes(): void {
		$parentIds = [ 8, 9, 10 ];

		$selectQueryBuilder = $this->createMock( SelectQueryBuilder::class );
		$selectQueryBuilder->method( 'select' )
			->with( [ 'rev_id', 'rev_len' ] )
			->willReturnSelf();
		$selectQueryBuilder->method( 'from' )
			->with( 'revision' )
			->willReturnSelf();
		$selectQueryBuilder->method( 'where' )
			->with( [ 'rev_id' => $parentIds ] )
			->willReturnSelf();
		$selectQueryBuilder->method( 'caller' )
			->willReturnSelf();
		$selectQueryBuilder->method( 'fetchResultSet' )
			->willReturn( new FakeResultWrapper( [
				(object)[ 'rev_id' => 8, 'rev_len' => 1000 ],
				(object)[ 'rev_id' => 9, 'rev_len' => 2000 ],
			] ) );

		$dbr = $this->createMock( IReadableDatabase::class );
		$dbr->method( 'newSelectQueryBuilder' )
			->willReturn( $selectQueryBuilder );

		$dbProvider = $this->createMock( IConnectionProvider::class );
		$dbProvider->method( 'getReplicaDatabase' )
			->with( 'testwiki' )
			->willReturn( $dbr );

		$lookup = $this->getLookupWithOverrides( [ 'dbProvider' => $dbProvider ] );

		$parentSizes = $lookup->getRevisionSizes( 'testwiki', $parentIds );

		$this->assertSame( [ 8 => 1000, 9 => 2000, 10 => 0 ], $parentSizes );
	}
}
