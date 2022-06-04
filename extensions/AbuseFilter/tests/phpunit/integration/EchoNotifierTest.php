<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Integration;

use EchoEvent;
use MediaWiki\Extension\AbuseFilter\Consequences\ConsequencesRegistry;
use MediaWiki\Extension\AbuseFilter\EchoNotifier;
use MediaWiki\Extension\AbuseFilter\Filter\ExistingFilter;
use MediaWiki\Extension\AbuseFilter\FilterLookup;
use MediaWikiIntegrationTestCase;
use Title;

/**
 * @coversDefaultClass \MediaWiki\Extension\AbuseFilter\EchoNotifier
 */
class EchoNotifierTest extends MediaWikiIntegrationTestCase {

	private const USER_IDS = [
		'1' => 1,
		'2' => 42,
	];

	private function getFilterLookup(): FilterLookup {
		$lookup = $this->createMock( FilterLookup::class );
		$lookup->method( 'getFilter' )
			->willReturnCallback( function ( $filter, $global ) {
				$filterObj = $this->createMock( ExistingFilter::class );
				$filterObj->method( 'getUserID' )
					->willReturn( self::USER_IDS[ $global ? "global-$filter" : $filter ] ?? 0 );
				return $filterObj;
			} );
		return $lookup;
	}

	public function provideDataForEvent(): array {
		return [
			[ true, 1, 1 ],
			[ true, 2, 42 ],
			[ false, 1, 1 ],
			[ false, 2, 42 ],
		];
	}

	/**
	 * @dataProvider provideDataForEvent
	 * @covers ::__construct
	 * @covers ::getDataForEvent
	 * @covers ::getFilterObject
	 * @covers ::getTitleForFilter
	 */
	public function testGetDataForEvent( bool $loaded, int $filter, int $userID ) {
		$expectedThrottledActions = [];
		$notifier = new EchoNotifier(
			$this->getFilterLookup(),
			$this->createMock( ConsequencesRegistry::class ),
			$loaded
		);
		[
			'type' => $type,
			'title' => $title,
			'extra' => $extra
		] = $notifier->getDataForEvent( $filter );

		$this->assertSame( EchoNotifier::EVENT_TYPE, $type );
		$this->assertInstanceOf( Title::class, $title );
		$this->assertSame( -1, $title->getNamespace() );
		[ , $subpage ] = explode( '/', $title->getText(), 2 );
		$this->assertSame( (string)$filter, $subpage );
		$this->assertSame( [ 'user' => $userID, 'throttled-actions' => $expectedThrottledActions ], $extra );
	}

	/**
	 * @covers ::notifyForFilter
	 */
	public function testNotifyForFilter() {
		if ( !class_exists( EchoEvent::class ) ) {
			$this->markTestSkipped( 'Echo not loaded' );
		}
		$notifier = new EchoNotifier(
			$this->getFilterLookup(),
			$this->createMock( ConsequencesRegistry::class ),
			true
		);
		$this->assertInstanceOf( EchoEvent::class, $notifier->notifyForFilter( 1 ) );
	}

	/**
	 * @covers ::notifyForFilter
	 */
	public function testNotifyForFilter_EchoNotLoaded() {
		$lookup = $this->createMock( FilterLookup::class );
		$lookup->expects( $this->never() )->method( $this->anything() );
		$notifier = new EchoNotifier(
			$lookup,
			$this->createMock( ConsequencesRegistry::class ),
			false
		);
		$this->assertFalse( $notifier->notifyForFilter( 1 ) );
	}

}
