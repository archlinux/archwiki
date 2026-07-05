<?php
/**
 * @license GPL-2.0-or-later
 *
 * @file
 */

namespace MediaWiki\Extension\OATHAuth\Tests\Integration;

use MediaWiki\Extension\OATHAuth\Key\TOTPKey;
use MediaWiki\Extension\OATHAuth\Key\WebAuthnKey;
use MediaWiki\Extension\OATHAuth\Module\TOTP;
use MediaWiki\Extension\OATHAuth\Module\WebAuthn;
use MediaWiki\Extension\OATHAuth\OATHAuthServices;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\User\CentralId\CentralIdLookup;
use MediaWiki\User\CentralId\CentralIdLookupFactory;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;
use Psr\Log\LoggerInterface;
use Wikimedia\ObjectCache\EmptyBagOStuff;
use Wikimedia\Rdbms\IConnectionProvider;

/**
 * @author Taavi Väänänen <hi@taavi.wtf>
 * @group Database
 * @covers \MediaWiki\Extension\OATHAuth\OATHUserRepository
 * @covers \MediaWiki\Extension\OATHAuth\OATHAuthServices
 * @covers \MediaWiki\Extension\OATHAuth\OATHUser
 */
class OATHUserRepositoryTest extends MediaWikiIntegrationTestCase {
	private function createUserRepo( User $user, $centralId = 12345 ) {
		$dbProvider = $this->createMock( IConnectionProvider::class );
		$dbProvider->method( 'getPrimaryDatabase' )->with( 'virtual-oathauth' )->willReturn( $this->getDb() );
		$dbProvider->method( 'getReplicaDatabase' )->with( 'virtual-oathauth' )->willReturn( $this->getDb() );

		$moduleRegistry = OATHAuthServices::getInstance( $this->getServiceContainer() )->getModuleRegistry();

		$lookup = $this->createMock( CentralIdLookup::class );
		$lookup->method( 'centralIdFromLocalUser' )
			->with( $user )
			->willReturn( $centralId );
		$lookup->method( 'localUserFromCentralId' )
			->with( $centralId )
			->willReturn( $user );
		$lookupFactory = $this->createMock( CentralIdLookupFactory::class );
		$lookupFactory->method( 'getLookup' )->willReturn( $lookup );

		$logger = $this->createMock( LoggerInterface::class );

		return new OATHUserRepository(
			$dbProvider,
			new EmptyBagOStuff(),
			$moduleRegistry,
			$lookupFactory,
			$logger
		);
	}

	public function testLookupCreateRemoveKey(): void {
		$user = $this->getTestUser()->getUser();
		$repository = $this->createUserRepo( $user, 12345 );
		$moduleRegistry = OATHAuthServices::getInstance( $this->getServiceContainer() )->getModuleRegistry();
		$module = $moduleRegistry->getModuleByKey( TOTP::MODULE_NAME );

		$oathUser = $repository->findByUser( $user );
		$this->assertEquals( 12345, $oathUser->getCentralId() );
		$this->assertEquals( [], $oathUser->getKeys() );

		/** @var TOTPKey $key */
		$key = $repository->createKey(
			$oathUser,
			$module,
			TOTPKey::newFromRandom()->jsonSerialize(),
			'127.0.0.1'
		);

		$this->assertNotEmpty(
			$this->getDb()->newSelectQueryBuilder()
				->select( '1' )
				->from( 'oathauth_devices' )
				->where( [ 'oad_user' => $oathUser->getCentralId() ] )
		);

		$this->assertArrayEquals( [ $key ], $oathUser->getKeys() );

		// Test looking it up again from the database
		$this->assertArrayEquals( [ $key ], $repository->findByUser( $user )->getKeys() );

		$repository->removeKey(
			$oathUser,
			$key,
			'127.0.0.1',
			true
		);

		$this->assertEquals( [], $oathUser->getKeys() );
		$this->assertEquals( [], $repository->findByUser( $user )->getKeys() );
	}

	public function testUserWithNoCentralId() {
		$user = $this->getTestUser()->getUser();
		$repository = $this->createUserRepo( $user, 0 );

		$oathUser = $repository->findByUser( $user );
		$this->assertSame( 0, $oathUser->getCentralId() );
		$this->assertEquals( [], $oathUser->getKeys() );
	}

	private function randomWebauthnKey( $userHandle ) {
		return [
			'userHandle' => base64_encode( $userHandle ),
			'publicKeyCredentialId' => base64_encode( random_bytes( 16 ) ),
			'credentialPublicKey' => base64_encode( random_bytes( 77 ) ),
			'aaguid' => 'ea9b8d66-4d01-1d21-3ce4-b6b48cb575d4',
			'friendlyName' => 'Google Password Manager',
			'counter' => 0,
			'type' => 'public-key',
			'transports' => [ 'hybrid', 'internal' ],
			'attestationType' => '',
			'trustPath' => [ 'type' => 'Webauthn\\TrustPath\\EmptyTrustPath' ],
			'supportsPasswordless' => true
		];
	}

	public function testUserHandle() {
		$user = $this->getTestUser()->getUser();
		$repository = $this->createUserRepo( $user, 12345 );

		$oathUser = $repository->findByUser( $user );
		$this->assertNull( $oathUser->getUserHandle() );

		// Adding a non-WebAuthn key does not cause a User Handle to be created
		$moduleRegistry = OATHAuthServices::getInstance( $this->getServiceContainer() )->getModuleRegistry();
		$totpModule = $moduleRegistry->getModuleByKey( TOTP::MODULE_NAME );
		$repository->createKey(
			$oathUser,
			$totpModule,
			TOTPKey::newFromRandom()->jsonSerialize(),
			'127.0.0.1'
		);
		$this->assertNull( $oathUser->getUserHandle() );
		$this->assertNull( $repository->findByUser( $user )->getUserHandle() );

		// Adding a WebAuthn key generates a new User Handle and sets it
		$webauthnModule = $moduleRegistry->getModuleByKey( WebAuthn::MODULE_ID );
		$generatedUserHandle = random_bytes( 64 );
		$keyData = $this->randomWebauthnKey( $generatedUserHandle );
		/** @var WebAuthnKey $key */
		$key = $repository->createKey(
			$oathUser,
			$webauthnModule,
			$keyData,
			'127.0.0.1'
		);
		$this->assertSame( $generatedUserHandle, $key->getUserHandle() );
		$this->assertSame( $generatedUserHandle, $oathUser->getUserHandle() );
		$this->assertSame( $generatedUserHandle, $repository->findByUser( $user )->getUserHandle() );

		// Looking up the User Handle finds the user
		$lookedupUser = $repository->findByUserHandle( $generatedUserHandle );
		$this->assertNotNull( $lookedupUser );
		$this->assertSame( 12345, $lookedupUser->getCentralId() );

		// Add another WebAuthn key with the same User Handle value
		$key2 = $repository->createKey(
			$oathUser,
			$webauthnModule,
			$this->randomWebauthnKey( $generatedUserHandle ),
			'127.0.0.1'
		);

		// Deleting the first WebAuthn key does not cause the User Handle to be deleted
		$repository->removeKey( $oathUser, $key, '127.0.0.1', true );
		$this->assertSame( $generatedUserHandle, $oathUser->getUserHandle() );
		$this->assertSame( $generatedUserHandle, $repository->findByUser( $user )->getUserHandle() );

		// Deleting the last WebAuthn key deletes the User Handle
		$repository->removeKey( $oathUser, $key2, '127.0.0.1', true );
		$this->assertNull( $oathUser->getUserHandle() );
		$this->assertNull( $repository->findByUser( $user )->getUserHandle() );

		// Looking up the User Handle no longer finds the user
		$this->assertNull( $repository->findByUserHandle( $generatedUserHandle ) );
	}

	public function testUserHas2FAEnabled() {
		$user = $this->getTestUser()->getUser();
		$repository = $this->createUserRepo( $user, 12345 );

		$this->assertFalse( $repository->userHas2FAEnabled( $user ) );

		$moduleRegistry = OATHAuthServices::getInstance( $this->getServiceContainer() )->getModuleRegistry();
		$totpModule = $moduleRegistry->getModuleByKey( TOTP::MODULE_NAME );

		$oathUser = $repository->findByUser( $user );
		$repository->createKey(
			$oathUser,
			$totpModule,
			TOTPKey::newFromRandom()->jsonSerialize(),
			'127.0.0.1'
		);

		$this->assertTrue( $repository->userHas2FAEnabled( $user ) );
	}
}
