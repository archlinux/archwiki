<?php

/**
 * Copy of CentralAuth's CentralAuthServiceWiringTest.php
 * used to test the ServiceWiring.php file.
 */

namespace MediaWiki\Extension\Notifications\Test;

use MediaWiki\MediaWikiServices;
use MediaWikiIntegrationTestCase;

/**
 * Tests ServiceWiring.php
 *
 * @coversNothing PHPUnit does not support covering annotations for files
 * @group Echo
 */
class ServiceWiringTest extends MediaWikiIntegrationTestCase {
	/**
	 * @dataProvider provideService
	 */
	public function testService( string $name ) {
		MediaWikiServices::getInstance()->get( $name );
		$this->addToAssertionCount( 1 );
	}

	public function provideService() {
		$wiring = require __DIR__ . '/../../ServiceWiring.php';
		foreach ( $wiring as $name => $_ ) {
			yield $name => [ $name ];
		}
	}
}
