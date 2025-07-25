<?php

namespace MediaWiki\CheckUser\Tests\Integration\HookHandler;

use MediaWiki\CheckUser\CheckUserQueryInterface;
use MediaWiki\CheckUser\Hooks;
use MediaWiki\CheckUser\Tests\Integration\CheckUserCommonTraitTest;
use MediaWiki\CheckUser\Tests\Integration\CheckUserTempUserTestTrait;
use MediaWiki\Context\RequestContext;
use MediaWiki\RecentChanges\RecentChange;
use MediaWikiIntegrationTestCase;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @group CheckUser
 * @group Database
 * @covers \MediaWiki\CheckUser\HookHandler\PerformRetroactiveAutoblockHandler
 */
class PerformRetroactiveAutoblockHandlerTest extends MediaWikiIntegrationTestCase implements CheckUserQueryInterface {

	use CheckUserTempUserTestTrait;
	use CheckUserCommonTraitTest;

	/**
	 * @dataProvider provideOnPerformRetroactiveAutoblock
	 */
	public function testOnPerformRetroactiveAutoblock(
		array $tablesWithData, int $maximumIPsToAutoblockConfigValue, array $expectedAutoBlockTargets
	) {
		$this->overrideConfigValue( 'CheckUserMaximumIPsToAutoblock', $maximumIPsToAutoblockConfigValue );
		$target = $this->getMutableTestUser()->getUserIdentity();
		// Insert the specified test data
		if ( in_array( self::CHANGES_TABLE, $tablesWithData ) ) {
			ConvertibleTimestamp::setFakeTime( '20210101000000' );
			// Set the request IP, which is the IP that should be autoblocked if an autoblock is applied.
			RequestContext::getMain()->getRequest()->setIP( '127.0.0.2' );
			// Insert a testing edit into cu_changes
			$rc = new RecentChange;
			$rc->setAttribs( array_merge(
				self::getDefaultRecentChangeAttribs(),
				[ 'rc_user' => $target->getId(), 'rc_user_text' => $target->getName() ],
			) );
			( new Hooks() )->updateCheckUserData( $rc );
		}
		if ( in_array( self::PRIVATE_LOG_EVENT_TABLE, $tablesWithData ) ) {
			ConvertibleTimestamp::setFakeTime( '20210101000001' );
			// Set the request IP, which is the IP that should be autoblocked if an autoblock is applied.
			RequestContext::getMain()->getRequest()->setIP( '127.0.0.4' );
			// Insert a RecentChanges event for a log entry that has no associated log ID (and therefore gets saved to
			// cu_private_event).
			$rc = new RecentChange;
			$rc->setAttribs( array_merge(
				self::getDefaultRecentChangeAttribs(),
				[
					'rc_type' => RC_LOG, 'rc_log_type' => '',
					'rc_user' => $target->getId(), 'rc_user_text' => $target->getName(),
				]
			) );
			( new Hooks() )->updateCheckUserData( $rc );
		}
		if ( in_array( self::LOG_EVENT_TABLE, $tablesWithData ) ) {
			ConvertibleTimestamp::setFakeTime( '20210101000002' );
			// Set the request IP, which is the IP that should be autoblocked if an autoblock is applied.
			RequestContext::getMain()->getRequest()->setIP( '127.0.0.2' );
			// Insert a RecentChanges event for a log entry that has a associated log ID (and therefore causes an
			// insert into cu_log_event).
			$logId = $this->newLogEntry();
			$rc = new RecentChange;
			$rc->setAttribs( array_merge(
				self::getDefaultRecentChangeAttribs(),
				[
					'rc_type' => RC_LOG, 'rc_logid' => $logId,
					'rc_user' => $target->getId(), 'rc_user_text' => $target->getName(),
				]
			) );
			( new Hooks() )->updateCheckUserData( $rc );
		}
		ConvertibleTimestamp::setFakeTime( '20210102000000' );
		// Block the target with autoblocking enabled. This should call the method under test.
		// We cannot call the hook handler directly, as the method will not work unless 'enableAutoblock'
		// is set. Setting 'enableAutoblock' causes the method under test to be called. Therefore,
		// calling the method under test directly would cause it to be run twice (which might cause unintended
		// consequences).
		$blockStore = $this->getServiceContainer()->getDatabaseBlockStore();
		$block = $blockStore->newUnsaved( [
			'targetUser' => $target,
			'enableAutoblock' => true,
			'by' => $this->getTestSysop()->getUserIdentity()
		] );
		$blockResult = $blockStore->insertBlock( $block );
		$this->assertIsArray( $blockResult, 'The block on the target could not be performed' );
		// Get a block associated with the IP 127.0.0.2, if any exists.
		$blockManager = $this->getServiceContainer()->getBlockManager();
		if ( count( $expectedAutoBlockTargets ) ) {
			$this->assertSameSize(
				$expectedAutoBlockTargets, $blockResult['autoIds'],
				'The number of autoblocks placed was not as expected'
			);
			foreach ( $expectedAutoBlockTargets as $expectedAutoBlockTarget ) {
				$ipBlock = $blockManager->getIpBlock( $expectedAutoBlockTarget, false );
				$this->assertNotNull(
					$ipBlock, "An autoblock should have been placed on the IP $expectedAutoBlockTarget."
				);
				$this->assertContains(
					$ipBlock->getId(), $blockResult['autoIds'], 'The autoblock ID was not as expected'
				);
			}
		} else {
			foreach ( [ '127.0.0.4', '127.0.0.2' ] as $autoBlockTarget ) {
				$ipBlock = $blockManager->getIpBlock( $autoBlockTarget, false );
				$this->assertNull( $ipBlock, 'No autoblock should have been placed on an IP.' );
			}
			$this->assertCount( 0, $blockResult['autoIds'], 'No autoblocks should have been placed.' );
		}
	}

	public static function provideOnPerformRetroactiveAutoblock() {
		return [
			'Account as the target of the block, CheckUser data exists for the account, and config set to 2' => [
				// Which CheckUser result tables have data for the target of the block
				[ self::CHANGES_TABLE, self::LOG_EVENT_TABLE, self::PRIVATE_LOG_EVENT_TABLE ],
				// The value of wgCheckUserMaximumIPsToAutoblock
				2,
				// The IPs that should be autoblocked
				[ '127.0.0.2', '127.0.0.4' ],
			],
			'Account as the target of the block, CheckUser data exists for the account, and config set to 1' => [
				[ self::CHANGES_TABLE, self::LOG_EVENT_TABLE, self::PRIVATE_LOG_EVENT_TABLE ], 1, [ '127.0.0.2' ],
			],
			'Account as the target of the block and target has only log related CheckUser data' => [
				[ self::LOG_EVENT_TABLE ], 2, [ '127.0.0.2' ],
			],
			'Account as the target of the block and target has only private log related CheckUser data' => [
				[ self::PRIVATE_LOG_EVENT_TABLE ], 2, [ '127.0.0.4' ],
			],
			'Account as the target of the block and target has only edit related CheckUser data' => [
				[ self::CHANGES_TABLE ], 3, [ '127.0.0.2' ],
			],
			'Account as the target of the block, target has no CheckUser data, and config set to 1' => [ [], 1, [] ],
		];
	}
}
