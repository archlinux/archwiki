<?php

/**
 * Modified copy of CentralAuth's CentralAuthServiceWiringTest.php
 * as it could not be included as it's in another extension.
 */

namespace MediaWiki\Extension\VisualEditor\Tests;

use MediaWikiIntegrationTestCase;

/**
 * @coversNothing
 */
class ServiceWiringTest extends MediaWikiIntegrationTestCase {

	/** @dataProvider provideService */
	public function testService( string $name ) {
		$this->getServiceContainer()->get( $name );
		$this->addToAssertionCount( 1 );
	}

	public static function provideService(): iterable {
		$wiring = require __DIR__ . '/../../../includes/ServiceWiring.php';
		foreach ( $wiring as $name => $_ ) {
			yield $name => [ $name ];
		}
	}
}
