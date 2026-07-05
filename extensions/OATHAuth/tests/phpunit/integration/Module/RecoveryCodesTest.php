<?php

namespace MediaWiki\Extension\OATHAuth\Tests\Integration\Module;

use Generator;
use MediaWiki\Extension\OATHAuth\Key\RecoveryCodeKeys;
use MediaWiki\Extension\OATHAuth\Module\RecoveryCodes;
use MediaWiki\Extension\OATHAuth\OATHAuthServices;
use MediaWiki\Extension\OATHAuth\OATHUser;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\Extension\OATHAuth\Tests\Integration\EncryptionTestTrait;
use MediaWiki\Request\WebRequest;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\OATHAuth\Module\RecoveryCodes
 * @group Database
 */
class RecoveryCodesTest extends MediaWikiIntegrationTestCase {
	use EncryptionTestTrait;

	private const NONCE = '7LRMXBX2AKPYWDBUBDHCN2WCFJXFX4XR2GZRV7Q=';

	public static function provideVerify(): Generator {
		yield 'Without encryption' => [ false ];
		yield 'With encryption' => [ true ];
	}

	/**
	 * @dataProvider provideVerify
	 */
	public function testVerify( bool $useEncryption ): void {
		$this->overrideConfigValue( 'OATHRecoveryCodesCount', 10 );
		$keyData = [
			'recoverycodekeys' => [ 'ABCD1234EFGH5678', 'IJKL9012MNOP3456' ]
		];

		if ( $useEncryption ) {
			$this->encryptionIntegrationTestSetup();
			$encryptionHelper = OATHAuthServices::getInstance( $this->getServiceContainer() )->getEncryptionHelper();
			$encrypted = [];
			foreach ( $keyData['recoverycodekeys'] as $key ) {
				[ 'secret' => $secret ] = $encryptionHelper->encrypt( $key, self::NONCE );
				$encrypted[] = $secret;
			}
			$keyData = [
				'recoverycodekeys' => $encrypted,
				'nonce' => self::NONCE,
			];
		}
		$key = RecoveryCodeKeys::newFromArray( $keyData );

		$mockWebRequest = $this->createMock( WebRequest::class );
		$mockOATHUser = $this->createMock( OATHUser::class );
		$mockOATHUser->method( 'getCentralId' )
			->willReturn( 12345 );
		$mockOATHUser->method( 'getUser' )
			->willReturn( $this->getTestUser()->getUser() );
		$mockOATHUser->method( 'getKeysForModule' )
			->willReturnCallback( static fn ( $moduleName ) => $moduleName === RecoveryCodes::MODULE_NAME ?
				[ $key ] : []
			);
		$this->setTemporaryHook(
			'GetSecurityLogContext',
			static function ( array $info, array &$context ) {
				$context['foo'] = 'bar';
			}
		);
		$mockWebRequest->method( 'getSecurityLogContext' )
			->willReturn( [ 'clientIp' => '1.1.1.1' ] );

		// Using a recovery code causes it to be removed
		$mockUserRepository = $this->createMock( OATHUserRepository::class );
		$module = new RecoveryCodes( $mockUserRepository );
		$mockUserRepository->expects( $this->once() )->method( 'updateKey' )
			->with( $mockOATHUser, $this->callback( function ( $recoveryCodeKey ) use ( $mockOATHUser ) {
				$this->assertSame( [ 'IJKL9012MNOP3456' ], array_values( $recoveryCodeKey->getRecoveryCodeKeys() ) );
				return true;
			} ) );

		// Recovery codes with spaces are accepted
		$this->assertTrue( $module->verify( $mockOATHUser, [ 'recoverycode' => 'ABCD 1234 EFGH 5678' ] ) );

		// Trying the same recovery code again fails
		$this->assertFalse( $module->verify( $mockOATHUser, [ 'recoverycode' => 'ABCD 1234 EFGH 5678' ] ) );

		// Using the last recovery code causes new recovery codes to be generated
		$mockUserRepository = $this->createMock( OATHUserRepository::class );
		$module = new RecoveryCodes( $mockUserRepository );
		$mockUserRepository->expects( $this->once() )->method( 'updateKey' )
			->with( $mockOATHUser, $this->callback( function ( $recoveryCodeKey ) use ( $mockOATHUser ) {
				$this->assertCount( 10, $recoveryCodeKey->getRecoveryCodeKeys() );
				$this->assertNotContains( 'IJKL9012MNOP3456', $recoveryCodeKey->getRecoveryCodeKeys() );
				return true;
			} ) );

		$this->assertTrue( $module->verify( $mockOATHUser, [ 'recoverycode' => 'IJKL9012MNOP3456' ] ) );
	}
}
