<?php

namespace MediaWiki\Extension\OATHAuth\Tests\Integration;

use MediaWiki\Extension\OATHAuth\OATHAuthServices;

trait EncryptionTestTrait {

	// Generated once using `MWCryptRand::generateHex( 64 );`
	private const SECRET_KEY = 'f901c7d7ecc25c90229c01cec0efec1b521a5e2eb6761d29007dde9566c4536a';

	public function encryptionUnitTestSetup() {
		if ( !extension_loaded( 'sodium' ) ) {
			$this->markTestSkipped( 'sodium extension not installed, skipping' );
		}
	}

	public function encryptionIntegrationTestSetup() {
		$this->encryptionUnitTestSetup();

		$this->setMwGlobals( 'wgOATHSecretKey', self::SECRET_KEY );
		$this->getServiceContainer()->resetServiceForTesting( 'OATHAuth.EncryptionHelper' );
		$this->assertTrue(
			OATHAuthServices::getInstance( $this->getServiceContainer() )
				->getEncryptionHelper()
				->isEnabled()
		);
	}
}
