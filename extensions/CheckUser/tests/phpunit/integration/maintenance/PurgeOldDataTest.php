<?php

namespace MediaWiki\CheckUser\Tests\Integration\Maintenance;

use MediaWiki\CheckUser\CheckUserQueryInterface;
use MediaWiki\CheckUser\Maintenance\PurgeOldData;
use MediaWiki\CheckUser\Services\CheckUserCentralIndexManager;
use MediaWiki\CheckUser\Services\UserAgentClientHintsManager;
use MediaWiki\CheckUser\Tests\Integration\Maintenance\Mocks\SemiMockedCheckUserDataPurger;
use MediaWiki\MainConfigNames;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use PurgeRecentChanges;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\ScopedCallback;
use Wikimedia\TestingAccessWrapper;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @group CheckUser
 * @covers \MediaWiki\CheckUser\Maintenance\PurgeOldData
 */
class PurgeOldDataTest extends MaintenanceBaseTestCase {

	/** @var MockObject|Maintenance */
	protected $maintenance;

	/** @inheritDoc */
	protected function getMaintenanceClass() {
		return PurgeOldData::class;
	}

	protected function setUp(): void {
		parent::setUp();
		// Fix the current time and CUDMaxAge so that we can assert against pre-defined timestamp values
		ConvertibleTimestamp::setFakeTime( '20230405060708' );
		$this->overrideConfigValue( 'CUDMaxAge', 30 );
	}

	protected function createMaintenance() {
		$obj = $this->getMockBuilder( $this->getMaintenanceClass() )
			->onlyMethods( [ 'runChild', 'getPrimaryDB' ] )
			->getMock();
		return TestingAccessWrapper::newFromObject( $obj );
	}

	/**
	 * Installs a mock IDatabase instance to the maintenance script that will be returned on calls to
	 * ::getPrimaryDB
	 *
	 * @param bool $shouldReturnScopedLock Whether IDatabase::getScopedLockAndFlush should return a ScopedCallback (
	 *   otherwise it returns null).
	 */
	private function installMockDatabase( bool $shouldReturnScopedLock ) {
		// Mock ::getScopedLockAndFlush to return null, to simulate that we were unable to acquire a lock.
		$mockDatabase = $this->createMock( IDatabase::class );
		$mockDatabase->method( 'getScopedLockAndFlush' )
			->willReturn( $shouldReturnScopedLock ? $this->createMock( ScopedCallback::class ) : null );
		// Mock ::timestamp to use the real behaviour.
		$mockDatabase->method( 'timestamp' )
			->willReturnCallback( static function ( $ts ) {
				$t = new ConvertibleTimestamp( $ts );
				return $t->getTimestamp( TS_MW );
			} );
		$mockDatabase->method( 'getDomainID' )
			->willReturn( 'enwiki' );
		$this->maintenance->method( 'getPrimaryDB' )
			->willReturn( $mockDatabase );
	}

	public function testExecuteWhenUnableToAcquireLock() {
		$this->installMockDatabase( false );
		// Expect that UserAgentClientHintsManager::deleteOrphanedMapRows are called (as this can be run even if
		// no lock is acquired).
		$mockUserAgentClientHintsManager = $this->createMock( UserAgentClientHintsManager::class );
		$mockUserAgentClientHintsManager->method( 'deleteOrphanedMapRows' )
			->willReturn( 123 );
		$mockUserAgentClientHintsManager->expects( $this->never() )
			->method( 'deleteMappingRows' );
		$this->setService( 'UserAgentClientHintsManager', $mockUserAgentClientHintsManager );
		$this->maintenance->method( 'runChild' )
			->with( PurgeRecentChanges::class )
			->willReturn( $this->createMock( PurgeRecentChanges::class ) );
		$this->maintenance->execute();
		$this->expectOutputRegex( "/Unable to acquire a lock to do the purging of CheckUser data./" );
	}

	/**
	 * @param bool $shouldPurgeRecentChanges Whether the maintenance script should purge data from recentchanges
	 * @return string The expected output regex
	 */
	private function generateExpectedOutputRegex( bool $shouldPurgeRecentChanges ): string {
		$expectedOutputRegex = '/';
		foreach ( CheckUserQueryInterface::RESULT_TABLES as $table ) {
			$expectedOutputRegex .= "Purging data from $table.*Purged " .
				SemiMockedCheckUserDataPurger::MOCKED_PURGED_ROW_COUNTS_PER_TABLE[$table] .
				" rows and 2 client hint mapping rows.\n";
		}
		$expectedOutputRegex .= "Purged 12 central index rows.\n";
		$expectedOutputRegex .= "Purged 123 orphaned client hint mapping rows.\n";
		if ( $shouldPurgeRecentChanges ) {
			$expectedOutputRegex .= "Purging data from recentchanges[\s\S]*";
		}
		$expectedOutputRegex .= 'Done/';
		return $expectedOutputRegex;
	}

	/** @dataProvider provideExecute */
	public function testExecute( $config, $shouldPurgeRecentChanges ) {
		$this->installMockDatabase( true );
		// Expect that the PurgeRecentChanges script is run if $shouldPurgeRecentChanges is true.
		$this->overrideConfigValues( $config );
		$this->maintenance->expects( $this->exactly( (int)$shouldPurgeRecentChanges ) )
			->method( 'runChild' )
			->with( PurgeRecentChanges::class )
			->willReturn( $this->createMock( PurgeRecentChanges::class ) );
		// Expect that UserAgentClientHintsManager::deleteOrphanedMapRows and ::deleteMappingRows are called,
		// and give them fake return values.
		$mockUserAgentClientHintsManager = $this->createMock( UserAgentClientHintsManager::class );
		$mockUserAgentClientHintsManager->method( 'deleteOrphanedMapRows' )
			->willReturn( 123 );
		$mockUserAgentClientHintsManager->expects( $this->exactly( 3 ) )
			->method( 'deleteMappingRows' )
			->willReturn( 2 );
		$this->setService( 'UserAgentClientHintsManager', $mockUserAgentClientHintsManager );
		// Install the mock CheckUserDataPurger service that will assert for us.
		$mockCheckUserDataPurger = new SemiMockedCheckUserDataPurger();
		$this->setService( 'CheckUserDataPurger', $mockCheckUserDataPurger );
		// Mock the CheckUserCentralIndexManager service to expect a call. The expected cutoff and domain ID are
		// generated by the fixed timestamp in ::setUp and the ::installMockDatabase call respectively.
		$mockCheckUserCentralIndexManager = $this->createMock( CheckUserCentralIndexManager::class );
		$mockCheckUserCentralIndexManager->expects( $this->exactly( 2 ) )
			->method( 'purgeExpiredRows' )
			->with( '20230405060638', 'enwiki' )
			->willReturnOnConsecutiveCalls( 12, 0 );
		$this->setService( 'CheckUserCentralIndexManager', $mockCheckUserCentralIndexManager );
		// Run the maintenance script
		$this->maintenance->execute();
		// Verify the output of the maintenance script is as expected
		$this->expectOutputRegex( $this->generateExpectedOutputRegex( $shouldPurgeRecentChanges ) );
		$mockCheckUserDataPurger->checkThatExpectedCallsHaveBeenMade();
	}

	public static function provideExecute() {
		return [
			'wgPutIPinRC is false' => [ [ MainConfigNames::PutIPinRC => false ], false ],
			'wgPutIPinRC is true' => [ [ MainConfigNames::PutIPinRC => true ], true ],
		];
	}
}
