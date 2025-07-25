<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

namespace MediaWiki\Extension\OATHAuth\Tests\Integration\Api\Module;

use Base32\Base32;
use jakobo\HOTP\HOTP;
use MediaWiki\Extension\OATHAuth\Key\TOTPKey;
use MediaWiki\Extension\OATHAuth\OATHAuthServices;
use MediaWiki\MainConfigNames;
use MediaWiki\Permissions\UltimateAuthority;
use MediaWiki\Tests\Api\ApiTestCase;
use Wikimedia\TestingAccessWrapper;

/**
 * @author Taavi Väänänen <hi@taavi.wtf>
 * @group Database
 */
class ApiOATHValidateTest extends ApiTestCase {
	protected function setUp(): void {
		parent::setUp();
		$this->overrideConfigValue( MainConfigNames::CentralIdLookupProvider, 'local' );
	}

	/**
	 * @covers \MediaWiki\Extension\OATHAuth\Api\Module\ApiOATHValidate::execute
	 */
	public function testNonexistentUser() {
		[ $result, ] = $this->doApiRequestWithToken(
			[
				'action' => 'oathvalidate',
				'user' => 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAA I am fake',
				'data' => '{"token": "123456"}',
			],
			null,
			new UltimateAuthority( $this->getTestUser()->getUserIdentity() )
		);

		$this->assertArraySubmapSame(
			[
				'oathvalidate' => [
					'enabled' => false,
					'valid' => false,
				],
			],
			$result
		);
	}

	/**
	 * @covers \MediaWiki\Extension\OATHAuth\Api\Module\ApiOATHValidate::execute
	 */
	public function testDisabled() {
		$testUser = $this->getTestUser();

		[ $result, ] = $this->doApiRequestWithToken(
			[
				'action' => 'oathvalidate',
				'user' => $testUser->getUserIdentity()->getName(),
				'data' => '{"token": "123456"}',
			],
			null,
			new UltimateAuthority( $testUser->getUserIdentity() )
		);

		$this->assertArraySubmapSame(
			[
				'oathvalidate' => [
					'enabled' => false,
					'valid' => false,
				],
			],
			$result
		);
	}

	/**
	 * @covers \MediaWiki\Extension\OATHAuth\Api\Module\ApiOATHValidate::execute
	 */
	public function testCorrectToken() {
		$testUser = $this->getTestUser();

		$key = TOTPKey::newFromRandom();
		$userRepository = OATHAuthServices::getInstance( $this->getServiceContainer() )->getUserRepository();
		$userRepository->createKey(
			$userRepository->findByUser( $testUser->getUserIdentity() ),
			OATHAuthServices::getInstance( $this->getServiceContainer() )
				->getModuleRegistry()
				->getModuleByKey( 'totp' ),
			$key->jsonSerialize(),
			'127.0.0.1'
		);

		$secret = TestingAccessWrapper::newFromObject( $key )->secret;
		$correctToken = HOTP::generateByTime(
			Base32::decode( $secret['secret'] ),
			$secret['period'],
		)->toHOTP( 6 );

		[ $result, ] = $this->doApiRequestWithToken(
			[
				'action' => 'oathvalidate',
				'user' => $testUser->getUserIdentity()->getName(),
				'data' => json_encode( [ 'token' => $correctToken ] ),
			],
			null,
			new UltimateAuthority( $testUser->getUserIdentity() )
		);

		$this->assertArraySubmapSame(
			[
				'oathvalidate' => [
					'enabled' => true,
					'valid' => true,
				],
			],
			$result
		);
	}

	/**
	 * @covers \MediaWiki\Extension\OATHAuth\Api\Module\ApiOATHValidate::execute
	 */
	public function testWrongToken() {
		$testUser = $this->getTestUser();

		$userRepository = OATHAuthServices::getInstance( $this->getServiceContainer() )->getUserRepository();
		$userRepository->createKey(
			$userRepository->findByUser( $testUser->getUserIdentity() ),
			OATHAuthServices::getInstance( $this->getServiceContainer() )
				->getModuleRegistry()
				->getModuleByKey( 'totp' ),
			TOTPKey::newFromRandom()->jsonSerialize(),
			'127.0.0.1'
		);

		[ $result, ] = $this->doApiRequestWithToken(
			[
				'action' => 'oathvalidate',
				'user' => $testUser->getUserIdentity()->getName(),
				'data' => json_encode( [ 'token' => '000000' ] ),
			],
			null,
			new UltimateAuthority( $testUser->getUserIdentity() )
		);

		$this->assertArraySubmapSame(
			[
				'oathvalidate' => [
					'enabled' => true,
					'valid' => false,
				],
			],
			$result
		);
	}
}
