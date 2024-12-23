<?php

/**
 * Copy of CentralAuth's CentralAuthServiceWiringTest.php
 * used to test the ServiceWiring.php file.
 */

namespace MediaWiki\Extension\DiscussionTools\Tests;

/**
 * Tests ServiceWiring.php
 *
 * @coversNothing PHPUnit does not support covering annotations for files
 * @group DiscussionTools
 */
class ServiceWiringTest extends IntegrationTestCase {
	/**
	 * @dataProvider provideService
	 */
	public function testService( string $name ) {
		$this->getServiceContainer()->get( $name );
		$this->addToAssertionCount( 1 );
	}

	public static function provideService() {
		$wiring = require __DIR__ . '/../../includes/ServiceWiring.php';
		foreach ( $wiring as $name => $_ ) {
			yield $name => [ $name ];
		}
	}
}
