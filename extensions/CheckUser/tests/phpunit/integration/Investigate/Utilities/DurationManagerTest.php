<?php

namespace MediaWiki\CheckUser\Tests\Integration\Investigate\Utilities;

use MediaWiki\CheckUser\Investigate\Utilities\DurationManager;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Utils\MWTimestamp;
use MediaWikiIntegrationTestCase;

/**
 * @group CheckUser
 * @covers \MediaWiki\CheckUser\Investigate\Utilities\DurationManager
 */
class DurationManagerTest extends MediaWikiIntegrationTestCase {

	public function setUp(): void {
		parent::setUp();
		MWTimestamp::setFakeTime( 0 );
	}

	/**
	 * @dataProvider provideDuration
	 */
	public function testGetFromRequest( string $duration, string $timestamp ): void {
		$valid = ( $timestamp !== '' );
		$durationManager = new DurationManager();

		$request = new FauxRequest( [
			'duration' => $duration,
		] );

		$this->assertSame( $valid ? $duration : '', $durationManager->getFromRequest( $request ) );
	}

	/** @dataProvider provideIsValid */
	public function testIsValid( string $duration, bool $expected ): void {
		$durationManager = new DurationManager();

		$this->assertSame( $expected, $durationManager->isValid( $duration ) );
	}

	public static function provideIsValid() {
		return [
			'Valid duration' => [ 'P1W', true ],
			'Invalid duration' => [ 'fail!', false ],
			'Empty duration' => [ '', true ],
		];
	}

	/**
	 * @dataProvider provideDuration
	 */
	public function testGetTimestampFromRequest( string $duration, string $timestamp ): void {
		$durationManager = new DurationManager();

		$request = new FauxRequest( [
			'duration' => $duration,
		] );

		$this->assertSame( $timestamp, $durationManager->getTimestampFromRequest( $request ) );
	}

	/**
	 * Provides durations.
	 */
	public static function provideDuration(): array {
		return [
			'Valid duration' => [
				'P1W',
				'19691225000000',
			],
			'Invalid duration' => [
				'fail!',
				'',
			],
		];
	}
}
