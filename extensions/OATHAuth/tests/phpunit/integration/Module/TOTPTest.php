<?php

namespace MediaWiki\Extension\OATHAuth\Tests\Integration\Module;

use MediaWiki\Extension\OATHAuth\Key\RecoveryCodeKeys;
use MediaWiki\Extension\OATHAuth\Key\TOTPKey;
use MediaWiki\Extension\OATHAuth\Module\RecoveryCodes;
use MediaWiki\Extension\OATHAuth\Module\TOTP;
use MediaWiki\Extension\OATHAuth\OATHUser;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWikiIntegrationTestCase;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @covers \MediaWiki\Extension\OATHAuth\Module\TOTP
 * @group Database
 */
class TOTPTest extends MediaWikiIntegrationTestCase {
	public function testVerifyTOTP() {
		$key1 = TOTPKey::newFromArray( [ 'secret' => 'BI5MNFS3MFS577GN7ALT2LY4FYLANBQXBGKNL656YQ' ] );
		$mockUser = $this->createMock( OATHUser::class );
		$mockUser
			->method( 'getKeysForModule' )
			->willReturnCallback( static fn ( $moduleName ) => $moduleName === TOTP::MODULE_NAME ?
				[ $key1 ] : [] );
		$module = new TOTP( $this->createMock( OATHUserRepository::class ) );

		$this->overrideConfigValue( 'OATHAuthWindowRadius', 1 );
		ConvertibleTimestamp::setFakeTime( '20251121225810' );

		$this->assertTrue( $module->verify( $mockUser, [ 'token' => '602401' ] ),
			'Token for this timestamp passes verification' );
		$this->assertFalse( $module->verify( $mockUser, [ 'token' => '602401' ] ),
			'Prevents replay attacks' );
		$this->assertFalse( $module->verify( $mockUser, [ 'token' => '455138' ] ),
			'Token for future window fails verification' );

		ConvertibleTimestamp::setFakeTime( '20251121230910' );
		$this->assertTrue( $module->verify( $mockUser, [ 'token' => '502636' ] ),
			'Token for next window passes verification' );

		ConvertibleTimestamp::setFakeTime( '20251121231110' );
		$this->assertTrue( $module->verify( $mockUser, [ 'token' => '683170' ] ),
			'Token for previous window passes verification' );
	}

	public function testVerifyTOTPMultiple() {
		$key1 = TOTPKey::newFromArray( [ 'secret' => 'BI5MNFS3MFS577GN7ALT2LY4FYLANBQXBGKNL656YQ' ] );
		$key2 = TOTPKey::newFromArray( [ 'secret' => '75YQX2JREHDEGJXOCRBZZRT3AM3Y5VG6CD32IWEJCI' ] );
		$mockUser = $this->createMock( OATHUser::class );
		$mockUser
			->method( 'getKeysForModule' )
			->willReturnCallback( static fn ( $moduleName ) => $moduleName === TOTP::MODULE_NAME ?
				[ $key1, $key2 ] : [] );
		$module = new TOTP( $this->createMock( OATHUserRepository::class ) );

		ConvertibleTimestamp::setFakeTime( '20251121233540' );

		$this->assertTrue( $module->verify( $mockUser, [ 'token' => '932322' ] ),
			'Token for second key passes verification' );

		ConvertibleTimestamp::setFakeTime( '20251121233640' );
		$this->assertTrue( $module->verify( $mockUser, [ 'token' => '529789' ] ),
			'Token for first key passes verification' );
	}

	public function testVerifyWithRecoveryCode() {
		$key1 = TOTPKey::newFromArray( [ 'secret' => 'BI5MNFS3MFS577GN7ALT2LY4FYLANBQXBGKNL656YQ' ] );
		$rcKey = RecoveryCodeKeys::newFromArray( [ 'recoverycodekeys' => [ '64SZLJTTPRI5XBUE' ] ] );
		$mockUser = $this->createMock( OATHUser::class );
		$mockUser->method( 'getCentralId' )
			->willReturn( 12345 );
		$mockUser->method( 'getUser' )
			->willReturn( $this->getTestUser()->getUser() );
		$mockUser
			->method( 'getKeysForModule' )
			->willReturnCallback( static fn ( $moduleName ) => match ( $moduleName ) {
				TOTP::MODULE_NAME => [ $key1 ],
				RecoveryCodes::MODULE_NAME => [ $rcKey ],
				default => []
			} );
		$mockUserRepo = $this->createMock( OATHUserRepository::class );
		$this->setService( 'OATHAuth.UserRepository', $mockUserRepo );
		$module = new TOTP( $mockUserRepo );

		$this->assertTrue( $module->verify( $mockUser, [ 'token' => '64SZLJTTPRI5XBUE' ] ) );
	}
}
