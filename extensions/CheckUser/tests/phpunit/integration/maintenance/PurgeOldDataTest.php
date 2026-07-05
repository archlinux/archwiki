<?php

namespace MediaWiki\Extension\CheckUser\Tests\Integration\Maintenance;

use MediaWiki\Extension\CheckUser\Maintenance\PurgeOldData;
use MediaWiki\Extension\CheckUser\Services\UserAgentClientHintsManager;
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
 * @covers \MediaWiki\Extension\CheckUser\Maintenance\PurgeOldData
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
			->onlyMethods( [ 'createChild', 'getPrimaryDB' ] )
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
	private function installMockDatabase( bool $shouldReturnScopedLock ): void {
		// Mock ::getScopedLockAndFlush to return null, to simulate that we were unable to acquire a lock.
		$mockDatabase = $this->createMock( IDatabase::class );
		$mockDatabase->method( 'getScopedLockAndFlush' )
			->willReturn( $shouldReturnScopedLock ? $this->createMock( ScopedCallback::class ) : null );
		// Mock ::timestamp to use the real behaviour.
		$mockDatabase->method( 'timestamp' )->willReturnCallback(
			static fn ( $ts ) => ( new ConvertibleTimestamp( $ts ) )->getTimestamp( TS_MW )
		);
		$mockDatabase->method( 'getDomainID' )
			->willReturn( 'enwiki' );
		$this->maintenance->method( 'getPrimaryDB' )
			->willReturn( $mockDatabase );
	}

	public function testExecuteWhenUnableToAcquireLock() {
		$this->installMockDatabase( false );
		// Expect that UserAgentClientHintsManager::deleteOrphanedMapRows are called (as this can be run even if
		// no lock is acquired).
		$hintsManager = $this->createNoOpMock( UserAgentClientHintsManager::class, [ 'deleteOrphanedMapRows' ] );
		$hintsManager->method( 'deleteOrphanedMapRows' )
			->willReturn( 123 );
		$this->setService( 'UserAgentClientHintsManager', $hintsManager );
		$this->maintenance->method( 'createChild' )
			->with( PurgeRecentChanges::class )
			->willReturn( $this->createMock( PurgeRecentChanges::class ) );
		$this->maintenance->execute();
		$this->expectOutputRegex( "/Unable to acquire a lock to do the purging of CheckUser data./" );
	}
}
