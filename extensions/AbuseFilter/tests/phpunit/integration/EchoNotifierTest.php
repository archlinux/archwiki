<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Integration;

use MediaWiki\Extension\AbuseFilter\Consequences\ConsequencesRegistry;
use MediaWiki\Extension\AbuseFilter\EchoNotifier;
use MediaWiki\Extension\AbuseFilter\Filter\ExistingFilter;
use MediaWiki\Extension\AbuseFilter\FilterLookup;
use MediaWiki\Extension\Notifications\Model\Event;
use MediaWiki\Title\Title;
use MediaWiki\User\UserIdentityValue;
use MediaWikiIntegrationTestCase;

/**
 * @group Database
 * @covers \MediaWiki\Extension\AbuseFilter\EchoNotifier
 */
class EchoNotifierTest extends MediaWikiIntegrationTestCase {

	private const USER_IDS = [
		'1' => 1,
		'2' => 42,
	];

	private function getFilterLookup( ?int $userID = null ): FilterLookup {
		$lookup = $this->createMock( FilterLookup::class );
		$lookup->method( 'getFilter' )
			->willReturnCallback( function ( $filter, $global ) use ( $userID ) {
				$userID ??= self::USER_IDS[ $global ? "global-$filter" : $filter ] ?? 0;
				$filterObj = $this->createMock( ExistingFilter::class );
				$filterObj->method( 'getUserIdentity' )->willReturn(
					UserIdentityValue::newRegistered( $userID, 'Test' )
				);
				$filterObj->method( 'getID' )->willReturn( $filter );
				return $filterObj;
			} );
		return $lookup;
	}

	public static function provideDataForEvent(): array {
		return [
			[ true, 1, 1 ],
			[ true, 2, 42 ]
		];
	}

	/**
	 * @dataProvider provideDataForEvent
	 */
	public function testNotifyForFilterHasCorrectData( bool $loaded, int $filter, int $userID ) {
		$this->markTestSkippedIfExtensionNotLoaded( 'Echo' );
		$expectedThrottledActions = [];
		$notifier = new EchoNotifier(
			$this->getFilterLookup(),
			$this->createMock( ConsequencesRegistry::class ),
			$loaded
		);

		$this->setTemporaryHook( 'BeforeEchoEventInsert',
			function ( Event $event ) use ( $filter, $expectedThrottledActions, $userID ) {
				$title = $event->getTitle();
				$extra = $event->getExtra();
				$this->assertSame( EchoNotifier::EVENT_TYPE, $event->getType() );
				$this->assertInstanceOf( Title::class, $title );
				$this->assertSame( -1, $title->getNamespace() );
				[ , $subpage ] = explode( '/', $title->getText(), 2 );
				$this->assertSame( (string)$filter, $subpage );
				$this->assertSame( $expectedThrottledActions, $extra['throttled-actions'] );
				$this->assertSame( [ $userID ], $extra[ Event::RECIPIENTS_IDX ] );
				// we can stop here, no need to trigger entire Echo flow
				return false;
			} );
		$notifier->notifyForFilter( $filter );
	}

	public function testNotifyReturnsEvent() {
		$this->markTestSkippedIfExtensionNotLoaded( 'Echo' );
		// Use a real user, or Echo will throw an exception.
		$user = $this->getTestUser()->getUserIdentity();
		$notifier = new EchoNotifier(
			$this->getFilterLookup( $user->getId() ),
			$this->createMock( ConsequencesRegistry::class ),
			true
		);
		$this->assertInstanceOf( Event::class, $notifier->notifyForFilter( 1 ) );
	}

	public function testNotifyForFilter_EchoNotLoaded() {
		$notifier = new EchoNotifier(
			$this->createNoOpMock( FilterLookup::class ),
			$this->createMock( ConsequencesRegistry::class ),
			false
		);
		$this->assertFalse( $notifier->notifyForFilter( 1 ) );
	}

}
