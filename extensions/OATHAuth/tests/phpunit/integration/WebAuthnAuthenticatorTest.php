<?php

namespace MediaWiki\Extension\OATHAuth\Tests\Integration;

use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\Extension\OATHAuth\WebAuthnAuthenticator;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\OATHAuth\WebAuthnAuthenticator
 * @covers \MediaWiki\Extension\OATHAuth\OATHAuthServices
 * @group Database
 */
class WebAuthnAuthenticatorTest extends MediaWikiIntegrationTestCase {

	private function getMockUser() {
		$user = $this->createMock( User::class );
		$user->method( 'getId' )
			->willReturn( 1 );
		$user->method( 'getName' )
			->willReturn( 'User' );
		return $user;
	}

	public function testIsEnabled() {
		/** @var WebAuthnAuthenticator $authenticator */
		$authenticator = $this->getServiceContainer()->getService( 'OATHAuth.WebAuthnAuthenticator' );
		/** @var OATHUserRepository $oathUser */
		$repo = $this->getServiceContainer()->getService( 'OATHAuth.UserRepository' );

		$this->assertFalse(
			$authenticator->isEnabled( $repo->findByUser( $this->getMockUser() ) )
		);
	}
}
