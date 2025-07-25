<?php

namespace MediaWiki\CheckUser\Tests\Integration\Jobs;

use MediaWiki\CheckUser\Jobs\PruneCheckUserDataJob;
use MediaWiki\CheckUser\Services\CheckUserDataPurger;
use MediaWiki\CheckUser\Tests\Integration\HookHandler\RecentChangeSaveHandlerTest;
use MediaWikiIntegrationTestCase;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IDatabase;

/**
 * @covers \MediaWiki\CheckUser\Jobs\PruneCheckUserDataJob
 * @group CheckUser
 * @see RecentChangeSaveHandlerTest::testPruneIPDataData for other tests that cover this job
 */
class PruneCheckUserDataJobTest extends MediaWikiIntegrationTestCase {
	public function testRunWhenUnableToAcquireLock() {
		$mockDatabase = $this->createMock( IDatabase::class );
		$mockDatabase->expects( $this->once() )
			->method( 'getScopedLockAndFlush' )
			->willReturnCallback( function ( $key ) {
				$this->assertSame( 'enwiki:PruneCheckUserData', $key, 'The lock key was not as expected' );
				// Simulate that the lock could not be acquired.
				return null;
			} );
		// Install a mock ConnectionProvider service that returns our mock IDatabase
		$mockConnectionProvider = $this->createMock( IConnectionProvider::class );
		$mockConnectionProvider->method( 'getPrimaryDatabase' )
			->with( 'enwiki' )
			->willReturn( $mockDatabase );
		$this->setService( 'ConnectionProvider', $mockConnectionProvider );
		// Expect that no calls to the CheckUserDataPurger service occur, as the lock could not be acquired.
		$this->setService( 'CheckUserDataPurger', $this->createNoOpMock( CheckUserDataPurger::class ) );
		// Call the code being tested.
		$job = new PruneCheckUserDataJob( 'unused', [ 'domainID' => 'enwiki' ] );
		$job->run();
	}
}
