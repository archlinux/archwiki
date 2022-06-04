<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Unit\Watcher;

use MediaWiki\Extension\AbuseFilter\CentralDBManager;
use MediaWiki\Extension\AbuseFilter\Watcher\UpdateHitCountWatcher;
use MediaWikiIntegrationTestCase;
use Wikimedia\Rdbms\DBConnRef;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\ILoadBalancer;

/**
 * @coversDefaultClass \MediaWiki\Extension\AbuseFilter\Watcher\UpdateHitCountWatcher
 * @covers ::__construct
 * @todo Make this a unit test once DeferredUpdates uses DI (T265749)
 */
class UpdateHitCountWatcherTest extends MediaWikiIntegrationTestCase {

	/**
	 * @covers ::run
	 * @covers ::updateHitCounts
	 */
	public function testRun() {
		$localFilters = [ 1, 2, 3 ];
		$globalFilters = [ 4, 5, 6 ];

		$localDB = $this->createMock( DBConnRef::class );
		$localDB->expects( $this->once() )->method( 'update' )->with(
			'abuse_filter',
			[ 'af_hit_count=af_hit_count+1' ],
			[ 'af_id' => $localFilters ]
		);
		$lb = $this->createMock( ILoadBalancer::class );
		$lb->method( 'getConnectionRef' )->willReturn( $localDB );

		$globalDB = $this->createMock( IDatabase::class );
		$globalDB->expects( $this->once() )->method( 'update' )->with(
			'abuse_filter',
			[ 'af_hit_count=af_hit_count+1' ],
			[ 'af_id' => $globalFilters ]
		);
		$centralDBManager = $this->createMock( CentralDBManager::class );
		$centralDBManager->method( 'getConnection' )->willReturn( $globalDB );

		$watcher = new UpdateHitCountWatcher( $lb, $centralDBManager );
		$watcher->run( $localFilters, $globalFilters, 'default' );
		// Two soft assertions done above
		$this->addToAssertionCount( 2 );
	}
}
