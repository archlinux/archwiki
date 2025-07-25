<?php

namespace MediaWiki\CheckUser\Tests\Unit\HookHandler;

use MediaWiki\CheckUser\HookHandler\RecentChangeSaveHandler;
use MediaWiki\CheckUser\Services\CheckUserInsert;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\JobQueue\JobSpecification;
use MediaWiki\RecentChanges\RecentChange;
use MediaWikiUnitTestCase;
use Wikimedia\Rdbms\IConnectionProvider;

/**
 * @covers \MediaWiki\CheckUser\HookHandler\RecentChangeSaveHandler
 * @group CheckUser
 */
class RecentChangeSaveHandlerTest extends MediaWikiUnitTestCase {

	/** @dataProvider provideOnRecentChangeSave */
	public function testOnRecentChangeSave( $mtRandReturnValue, $shouldCreatePurgeJob ) {
		$recentChange = $this->createMock( RecentChange::class );
		// Create a mock object of CheckUserInsert service that expects a call to ::updateCheckUserData
		$mockCheckUserInsert = $this->createMock( CheckUserInsert::class );
		$mockCheckUserInsert->expects( $this->once() )
			->method( 'updateCheckUserData' )
			->with( $recentChange );
		// Create a mock object of JobQueueGroup that expects a call to ::push if $shouldCreatePurgeJob is true.
		$mockJobQueueGroup = $this->createMock( JobQueueGroup::class );
		if ( $shouldCreatePurgeJob ) {
			$mockJobQueueGroup->expects( $this->once() )
				->method( 'push' )
				->with( $this->isInstanceOf( JobSpecification::class ) );
		} else {
			$mockJobQueueGroup->expects( $this->never() )
				->method( 'push' );
		}
		// Get the RecentChangeSaveHandler object, with the mt_rand method mocked to return $mtRandReturnValue.
		$handler = $this->getMockBuilder( RecentChangeSaveHandler::class )
			->setConstructorArgs( [
				$mockCheckUserInsert,
				$mockJobQueueGroup,
				$this->createMock( IConnectionProvider::class ),
			] )
			->onlyMethods( [ 'mtRand' ] )
			->getMock();
		$handler->method( 'mtRand' )
			->willReturn( $mtRandReturnValue );
		// Call the method under test.
		$handler->onRecentChange_save( $recentChange );
	}

	public static function provideOnRecentChangeSave() {
		return [
			'No purge job queued' => [ 1, false ],
			'Purge job queued' => [ 0, true ],
		];
	}
}
