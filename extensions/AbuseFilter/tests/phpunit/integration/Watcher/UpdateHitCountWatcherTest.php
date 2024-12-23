<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Integration\Watcher;

use MediaWiki\Extension\AbuseFilter\CentralDBManager;
use MediaWiki\Extension\AbuseFilter\Watcher\UpdateHitCountWatcher;
use MediaWikiIntegrationTestCase;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\LBFactory;
use Wikimedia\Rdbms\UpdateQueryBuilder;

/**
 * @covers \MediaWiki\Extension\AbuseFilter\Watcher\UpdateHitCountWatcher
 */
class UpdateHitCountWatcherTest extends MediaWikiIntegrationTestCase {

	public function testRun() {
		$localFilters = [ 1, 2, 3 ];
		$globalFilters = [ 4, 5, 6 ];

		$localDB = $this->createMock( IDatabase::class );
		$localDB->expects( $this->once() )->method( 'update' )->with(
			'abuse_filter',
			[ 'af_hit_count=af_hit_count+1' ],
			[ 'af_id' => $localFilters ]
		);
		$localDB->method( 'newUpdateQueryBuilder' )
			->willReturnCallback( static function () use ( $localDB ) {
				return new UpdateQueryBuilder( $localDB );
			} );
		$lb = $this->createMock( LBFactory::class );
		$lb->method( 'getPrimaryDatabase' )->willReturn( $localDB );

		$globalDB = $this->createMock( IDatabase::class );
		$globalDB->expects( $this->once() )->method( 'update' )->with(
			'abuse_filter',
			[ 'af_hit_count=af_hit_count+1' ],
			[ 'af_id' => $globalFilters ]
		);
		$globalDB->method( 'newUpdateQueryBuilder' )
			->willReturnCallback( static function () use ( $globalDB ) {
				return new UpdateQueryBuilder( $globalDB );
			} );
		$centralDBManager = $this->createMock( CentralDBManager::class );
		$centralDBManager->method( 'getConnection' )->willReturn( $globalDB );

		$watcher = new UpdateHitCountWatcher( $lb, $centralDBManager );
		$watcher->run( $localFilters, $globalFilters, 'default' );
		// Two soft assertions done above
		$this->addToAssertionCount( 2 );
	}
}
