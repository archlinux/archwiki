<?php

namespace MediaWiki\CheckUser\Tests\Unit\GlobalContributions;

use InvalidArgumentException;
use LogicException;
use MediaWiki\CheckUser\CheckUserQueryInterface;
use MediaWiki\CheckUser\GlobalContributions\CheckUserGlobalContributionsLookup;
use MediaWiki\CheckUser\Services\CheckUserLookupUtils;
use MediaWiki\Config\Config;
use MediaWiki\Config\HashConfig;
use MediaWiki\Permissions\Authority;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\User\CentralId\CentralIdLookup;
use MediaWikiUnitTestCase;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IExpression;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\SelectQueryBuilder;

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
			->willReturn( 'true' );

		return new CheckUserGlobalContributionsLookup(
			$overrides['dbProvider'] ?? $this->createMock( IConnectionProvider::class ),
			$overrides['extensionRegistry'] ?? $extensionRegistry,
			$overrides['centralIdLookup'] ?? $this->createMock( CentralIdLookup::class ),
			$overrides['checkUserLookupUtils'] ?? $this->createMock( CheckUserLookupUtils::class ),
			$overrides['config'] ?? $this->createMock( Config::class )
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
		$queryBuilder->method( $this->logicalOr( 'select', 'from', 'distinct', 'where', 'join', 'caller', 'orderBy' ) )
			->willReturnSelf();
		$queryBuilder->method( 'fetchFieldValues' )
			->willReturn( $activeWikis );

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
			'config' => $config
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
}
