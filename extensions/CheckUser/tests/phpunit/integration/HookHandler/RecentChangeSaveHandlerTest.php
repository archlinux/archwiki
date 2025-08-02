<?php

namespace MediaWiki\CheckUser\Tests\Integration\HookHandler;

use MediaWiki\CheckUser\HookHandler\RecentChangeSaveHandler;
use MediaWiki\CheckUser\Services\CheckUserCentralIndexManager;
use MediaWiki\CheckUser\Tests\Integration\CheckUserCommonTraitTest;
use MediaWiki\CheckUser\Tests\Integration\CheckUserTempUserTestTrait;
use MediaWiki\RecentChanges\RecentChange;
use MediaWikiIntegrationTestCase;
use Wikimedia\TestingAccessWrapper;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @covers \MediaWiki\CheckUser\HookHandler\RecentChangeSaveHandler
 * @group Database
 * @group CheckUser
 */
class RecentChangeSaveHandlerTest extends MediaWikiIntegrationTestCase {

	use CheckUserCommonTraitTest;
	use CheckUserTempUserTestTrait;

	private function getObjectUnderTest(): RecentChangeSaveHandler {
		return new RecentChangeSaveHandler(
			$this->getServiceContainer()->get( 'CheckUserInsert' ),
			$this->getServiceContainer()->getJobQueueGroup(),
			$this->getServiceContainer()->getConnectionProvider()
		);
	}

	/**
	 * Tests that the onRecentChange_save method actually causes an insert to the CheckUser data tables.
	 * More detailed testing is done through unit tests.
	 *
	 * @dataProvider provideOnRecentChangeSave
	 */
	public function testOnRecentChangeSave( $rcAttribs, $table, $fields, $expectedRow ) {
		$this->disableAutoCreateTempUser();
		$rc = new RecentChange;
		$rc->setAttribs( $rcAttribs );
		$this->getObjectUnderTest()->onRecentChange_save( $rc );
		foreach ( $fields as $index => $field ) {
			if ( in_array( $field, [ 'cuc_timestamp', 'cule_timestamp', 'cupe_timestamp' ] ) ) {
				$expectedRow[$index] = $this->getDb()->timestamp( $expectedRow[$index] );
			}
		}
		$this->newSelectQueryBuilder()
			->select( $fields )
			->from( $table )
			->assertRowValue( $expectedRow );
	}

	public static function provideOnRecentChangeSave() {
		$defaultRcAttribs = self::getDefaultRecentChangeAttribs();
		return [
			'Edit action' => [
				array_merge( $defaultRcAttribs, [
					'rc_type' => RC_EDIT,
					'rc_user' => 0,
					'rc_user_text' => '127.0.0.1',
				] ),
				'cu_changes',
				[ 'cuc_type' ],
				[ RC_EDIT ],
			],
			'Log for special title with no log ID' => [
				array_merge( $defaultRcAttribs, [
					'rc_namespace' => NS_SPECIAL,
					'rc_title' => 'Log',
					'rc_type' => RC_LOG,
					'rc_log_type' => ''
				] ),
				'cu_private_event',
				[ 'cupe_title', 'cupe_namespace' ],
				[ 'Log', NS_SPECIAL ],
			]
		];
	}

	/**
	 * @dataProvider providePruneIPDataData
	 * @covers \MediaWiki\CheckUser\Jobs\PruneCheckUserDataJob
	 * @covers \MediaWiki\CheckUser\Services\CheckUserDataPurger
	 */
	public function testPruneIPDataData( int $currentTime, int $maxCUDataAge, array $timestamps, int $afterCount ) {
		// Check that PruneCheckUserDataJob::run will call CheckUserCentralIndexManager::purgeExpiredRows
		$mockCheckUserCentralIndexManager = $this->createMock( CheckUserCentralIndexManager::class );
		$mockCheckUserCentralIndexManager->expects( $this->once() )
			->method( 'purgeExpiredRows' )
			->willReturn( 12 );
		$this->setService( 'CheckUserCentralIndexManager', $mockCheckUserCentralIndexManager );
		// Set wgCUDMaxAge to ensure that any changes to the default does not affect this test.
		$this->overrideConfigValue( 'CUDMaxAge', $maxCUDataAge );
		$logEntryCutoff = $this->getDb()->timestamp( $currentTime - $maxCUDataAge );
		foreach ( $timestamps as $timestamp ) {
			ConvertibleTimestamp::setFakeTime( $timestamp );
			$expectedRow = [];
			// Insertion into cu_changes
			$this->commonTestsUpdateCheckUserData( self::getDefaultRecentChangeAttribs(), [], $expectedRow );
			// Insertion into cu_private_event
			$this->commonTestsUpdateCheckUserData(
				array_merge( self::getDefaultRecentChangeAttribs(), [ 'rc_type' => RC_LOG, 'rc_log_type' => '' ] ),
				[],
				$expectedRow
			);
			// Insertion into cu_log_event
			$logId = $this->newLogEntry();
			$this->commonTestsUpdateCheckUserData(
				array_merge( self::getDefaultRecentChangeAttribs(), [ 'rc_type' => RC_LOG, 'rc_logid' => $logId ] ),
				[],
				$expectedRow
			);
		}
		$this->assertRowCount( count( $timestamps ), 'cu_changes', 'cuc_id',
			'cu_changes was not set up correctly for the test.' );
		$this->assertRowCount( count( $timestamps ), 'cu_private_event', 'cupe_id',
			'cu_private_event was not set up correctly for the test.' );
		$this->assertRowCount( count( $timestamps ), 'cu_log_event', 'cule_id',
			'cu_log_event was not set up correctly for the test.' );
		ConvertibleTimestamp::setFakeTime( $currentTime );
		$object = TestingAccessWrapper::newFromObject( $this->getObjectUnderTest() );
		$object->pruneIPData();
		$this->getServiceContainer()->getJobRunner()->run( [ 'type' => 'checkuserPruneCheckUserDataJob' ] );
		// Check that all the old entries are gone
		$this->assertRowCount( 0, 'cu_changes', 'cuc_id',
			'cu_changes has stale entries after calling pruneIPData.',
			[ $this->getDb()->expr( 'cuc_timestamp', '<', $logEntryCutoff ) ] );
		$this->assertRowCount( 0, 'cu_private_event', 'cupe_id',
			'cu_private_event has stale entries after calling pruneIPData.',
			[ $this->getDb()->expr( 'cupe_timestamp ', '<', $logEntryCutoff ) ] );
		$this->assertRowCount( 0, 'cu_log_event', 'cule_id',
			'cu_log_event has stale entries after calling pruneIPData.',
			[ $this->getDb()->expr( 'cule_timestamp', '<', $logEntryCutoff ) ] );
		// Assert that no still in date entries were removed
		$this->assertRowCount( $afterCount, 'cu_changes', 'cuc_id',
			'cu_changes is missing rows that were not stale after calling pruneIPData.' );
		$this->assertRowCount( $afterCount, 'cu_private_event', 'cupe_id',
			'cu_private_event is missing rows that were not stale after calling pruneIPData.' );
		$this->assertRowCount( $afterCount, 'cu_log_event', 'cule_id',
			'cu_log_event is missing rows that were not stale after calling pruneIPData.' );
	}

	public static function providePruneIPDataData() {
		$currentTime = time();
		$defaultMaxAge = 7776000;
		return [
			'No entries to prune' => [
				$currentTime,
				$defaultMaxAge,
				[
					$currentTime - 2,
					$currentTime - $defaultMaxAge + 100,
					$currentTime,
					$currentTime + 10
				],
				4
			],
			'Two entries to prune with two to be left' => [
				$currentTime,
				$defaultMaxAge,
				[
					$currentTime - $defaultMaxAge - 20000,
					$currentTime - $defaultMaxAge - 100,
					$currentTime,
					$currentTime + 10
				],
				2
			],
			'Four entries to prune with no left' => [
				$currentTime,
				$defaultMaxAge,
				[
					$currentTime - $defaultMaxAge - 20000,
					$currentTime - $defaultMaxAge - 100,
					$currentTime - $defaultMaxAge - 1,
					$currentTime - $defaultMaxAge - 100000
				],
				0
			]
		];
	}
}
