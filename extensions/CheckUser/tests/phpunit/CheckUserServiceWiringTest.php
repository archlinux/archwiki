<?php

/**
 * Copy of CentralAuth's CentralAuthServiceWiringTest.php
 * as it could not be included as it's in another extension.
 */

/**
 * @coversNothing
 * @group Database
 */
class CheckUserServiceWiringTest extends MediaWikiIntegrationTestCase {
	/**
	 * @dataProvider provideService
	 */
	public function testService( string $name ) {
		// The CheckUserGlobalContributionsPagerFactory service needs a GlobalPreferences service, so the test
		// fails if it's not installed.
		if ( $name === 'CheckUserGlobalContributionsPagerFactory' ) {
			$this->markTestSkippedIfExtensionNotLoaded( 'GlobalPreferences' );
		}

		// CheckUserUserInfoCardService has dependencies provided by the GrowthExperiments extension.
		if ( $name === 'CheckUserUserInfoCardService' ) {
			$this->markTestSkippedIfExtensionNotLoaded( 'GrowthExperiments' );
		}

		$this->getServiceContainer()->get( $name );
		$this->addToAssertionCount( 1 );
	}

	public static function provideService() {
		$wiring = require __DIR__ . '/../../src/ServiceWiring.php';
		foreach ( $wiring as $name => $_ ) {
			yield $name => [ $name ];
		}
	}
}
