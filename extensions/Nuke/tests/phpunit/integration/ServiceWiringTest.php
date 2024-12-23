<?php

/**
 * Copy of CentralAuth's CentralAuthServiceWiringTest.php that tests
 * the ServiceWiring.php file for the Nuke extension.
 */

namespace MediaWiki\Extension\Nuke\Test;

use MediaWiki\MediaWikiServices;
use MediaWikiIntegrationTestCase;

/**
 * @coversNothing
 * @group Database
 */
class ServiceWiringTest extends MediaWikiIntegrationTestCase {
	/** @dataProvider provideService */
	public function testService( string $name ) {
		MediaWikiServices::getInstance()->get( $name );
		$this->addToAssertionCount( 1 );
	}

	public static function provideService() {
		$wiring = require __DIR__ . '/../../../includes/ServiceWiring.php';
		foreach ( $wiring as $name => $_ ) {
			yield $name => [ $name ];
		}
	}
}
