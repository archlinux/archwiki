<?php

namespace MediaWiki\Extension\CheckUser\Tests\Integration\Jobs;

use MediaWiki\Config\HashConfig;
use MediaWiki\Extension\CheckUser\Jobs\PruneCheckUserDataJob;
use MediaWiki\Extension\CheckUser\Services\CheckUserCentralIndexManager;
use MediaWiki\Extension\CheckUser\Services\CheckUserDataPurger;
use MediaWiki\Extension\CheckUser\Services\UserAgentClientHintsManager;
use MediaWiki\Extension\CheckUser\Tests\Integration\HookHandler\RecentChangeSaveHandlerTest;
use MediaWikiIntegrationTestCase;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IDatabase;

/**
 * @covers \MediaWiki\Extension\CheckUser\Jobs\PruneCheckUserDataJob
 * @group CheckUser
 * @see RecentChangeSaveHandlerTest::testPruneIPDataData for other tests that cover this job
 */
class PruneCheckUserDataJobTest extends MediaWikiIntegrationTestCase {
	public function testRunWhenDatabaseIsReadOnly() {
		$mockDatabase = $this->createMock( IDatabase::class );
		$mockDatabase->expects( $this->once() )
			->method( 'isReadOnly' )
			->willReturn( true );

		$mockDatabase->expects( $this->never() )
			->method( 'getScopedLockAndFlush' );

		$mockConnectionProvider = $this->createMock( IConnectionProvider::class );
		$mockConnectionProvider->method( 'getPrimaryDatabase' )
			->with( 'enwiki' )
			->willReturn( $mockDatabase );

		$job = new PruneCheckUserDataJob(
			'unused',
			[ 'domainID' => 'enwiki' ],
			$this->createNoOpMock( CheckUserCentralIndexManager::class ),
			$this->createNoOpMock( CheckUserDataPurger::class ),
			new HashConfig(),
			$mockConnectionProvider,
			$this->createNoOpMock( UserAgentClientHintsManager::class )
		);
		$job->run();
	}

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
		// Expect that no calls to the CheckUserDataPurger service occur, as the lock could not be acquired.
		// Call the code being tested.
		$job = new PruneCheckUserDataJob(
			'unused',
			[ 'domainID' => 'enwiki' ],
			$this->createNoOpMock( CheckUserCentralIndexManager::class ),
			$this->createNoOpMock( CheckUserDataPurger::class ),
			new HashConfig(),
			$mockConnectionProvider,
			$this->createNoOpMock( UserAgentClientHintsManager::class )
		);
		$job->run();
	}
}
