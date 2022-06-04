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
 * @since 1.35
 */

namespace MediaWiki\Skins\Vector\Tests\Integration;

use HashConfig;
use MediaWiki\User\UserOptionsLookup;
use Vector\Constants;
use Vector\SkinVersionLookup;

/**
 * @group Vector
 * @coversDefaultClass \Vector\SkinVersionLookup
 */
class SkinVersionLookupTest extends \MediaWikiIntegrationTestCase {
	/**
	 * @covers ::isLegacy
	 * @covers ::getVersion
	 */
	public function testRequest() {
		$request = $this->getMockBuilder( \WebRequest::class )->getMock();
		$request
			->method( 'getVal' )
			->willReturnCallback( static function ( $key ) {
				if ( $key === Constants::QUERY_PARAM_SKIN ) {
					return null;
				} else {
					return 'alpha';
				}
			} );

		$user = $this->createMock( \User::class );
		$user
			->method( 'isRegistered' )
			->willReturn( false );

		$config = new HashConfig( [
			'VectorSkinMigrationMode' => false,
			'VectorDefaultSkinVersion' => '2',
			'VectorDefaultSkinVersionForExistingAccounts' => '1'
		] );

		$userOptionsLookup = $this->getUserOptionsLookupMock( $user, 'beta', [
			'skin' => Constants::SKIN_NAME_LEGACY,
		] );

		$skinVersionLookup = new SkinVersionLookup( $request, $user, $config, $userOptionsLookup );

		$this->assertSame(
			'alpha',
			$skinVersionLookup->getVersion(),
			'Query parameter is the first priority.'
		);
		$this->assertSame(
			false,
			$skinVersionLookup->isLegacy(),
			'Version is non-Legacy.'
		);
	}

	/**
	 * @covers ::getVersion
	 * @covers ::isLegacy
	 */
	public function testUserPreference() {
		$request = $this->getMockBuilder( \WebRequest::class )->getMock();
		$request
			->method( 'getVal' )
			->willReturnCallback( static function ( $key ) {
				if ( $key === Constants::QUERY_PARAM_SKIN ) {
					return null;
				} else {
					return 'beta';
				}
			} );

		$user = $this->createMock( \User::class );
		$user
			->method( 'isRegistered' )
			->willReturn( false );

		$config = new HashConfig( [
			'VectorSkinMigrationMode' => false,
			'VectorDefaultSkinVersion' => '2',
			'VectorDefaultSkinVersionForExistingAccounts' => '1'
		] );

		$userOptionsLookup = $this->getUserOptionsLookupMock( $user, 'beta', [
			'skin' => Constants::SKIN_NAME_LEGACY,
		] );

		$skinVersionLookup = new SkinVersionLookup( $request, $user, $config, $userOptionsLookup );

		$this->assertSame(
			'beta',
			$skinVersionLookup->getVersion(),
			'User preference is the second priority.'
		);
		$this->assertSame(
			false,
			$skinVersionLookup->isLegacy(),
			'Version is non-Legacy.'
		);
	}

	/**
	 * @covers ::getVersion
	 * @covers ::isLegacy
	 */
	public function testConfigRegistered() {
		$request = $this->getMockBuilder( \WebRequest::class )->getMock();
		$request
			->method( 'getVal' )
			->willReturnCallback( static function ( $key ) {
				if ( $key === Constants::QUERY_PARAM_SKIN ) {
					return null;
				} else {
					return '1';
				}
			} );

		$user = $this->createMock( \User::class );
		$user
			->method( 'isRegistered' )
			->willReturn( true );

		$config = new HashConfig( [
			'VectorSkinMigrationMode' => false,
			'VectorDefaultSkinVersion' => '2',
			'VectorDefaultSkinVersionForExistingAccounts' => '1'
		] );

		$userOptionsLookup = $this->getUserOptionsLookupMock( $user, '1', [
			'skin' => Constants::SKIN_NAME_LEGACY,
		] );

		$skinVersionLookup = new SkinVersionLookup( $request, $user, $config, $userOptionsLookup );

		$this->assertSame(
			'1',
			$skinVersionLookup->getVersion(),
			'Config is the third priority and distinguishes logged in users from anonymous users.'
		);
		$this->assertSame(
			true,
			$skinVersionLookup->isLegacy(),
			'Version is Legacy.'
		);
	}

	public function providerAnonUserMigrationMode() {
		return [
			// When no query string just return DefaultSkin version.
			[
				Constants::SKIN_NAME_LEGACY,
				null,
				Constants::SKIN_VERSION_LEGACY,
			],
			[
				Constants::SKIN_NAME_MODERN,
				null,
				Constants::SKIN_VERSION_LATEST,
			],
			// When useskin=vector return legacy Vector version.
			[
				Constants::SKIN_NAME_LEGACY,
				Constants::SKIN_NAME_LEGACY,
				Constants::SKIN_VERSION_LEGACY,
			],
			[
				Constants::SKIN_NAME_MODERN,
				Constants::SKIN_NAME_LEGACY,
				Constants::SKIN_VERSION_LEGACY,
			],
			// When useskin=vector-2022 return modern Vector.
			[
				Constants::SKIN_NAME_MODERN,
				Constants::SKIN_NAME_MODERN,
				Constants::SKIN_VERSION_LATEST,
			],
			[
				Constants::SKIN_NAME_LEGACY,
				Constants::SKIN_NAME_MODERN,
				Constants::SKIN_VERSION_LATEST,
			],
		];
	}

	/**
	 * @covers ::getVersion
	 * @dataProvider providerAnonUserMigrationMode
	 */
	public function testVectorAnonUserMigrationModeWithUseSkinVector(
		string $defaultSkin,
		$useSkin,
		$expectedVersion
	) {
		$request = $this->getMockBuilder( \WebRequest::class )->getMock();
		$request
			->method( 'getVal' )
			->with( 'useskin' )
			->willReturn( $useSkin );
		$user = $this->createMock( \User::class );
		$user
			->method( 'isRegistered' )
			->willReturn( false );

		$config = new HashConfig( [
			'DefaultSkin' => $defaultSkin,
			'VectorSkinMigrationMode' => true,
			'VectorDefaultSkinVersion' => '2',
			'VectorDefaultSkinVersionForExistingAccounts' => '2'
		] );
		$userOptionsLookup = $this->getUserOptionsLookupMock( $user, '2', [
			'skin' => $defaultSkin,
		] );

		$skinVersionLookup = new SkinVersionLookup( $request, $user, $config, $userOptionsLookup );

		$this->assertSame(
			$expectedVersion,
			$skinVersionLookup->getVersion(),
			'useskin=vector query string yields legacy skin in migration mode'
		);
	}

	/**
	 * @covers ::getVersion
	 */
	public function testVectorRegisteredUserMigrationMode() {
		$request = $this->getMockBuilder( \WebRequest::class )->getMock();
		$request
			->method( 'getVal' )
			->willReturn( null );
		$user = $this->createMock( \User::class );
		$user
			->method( 'isRegistered' )
			->willReturn( true );

		$config = new HashConfig( [
			'DefaultSkin' => 'vector',
			'VectorSkinMigrationMode' => true,
			'VectorDefaultSkinVersion' => '1',
			'VectorDefaultSkinVersionForExistingAccounts' => '1'
		] );
		$userOptionsLookup = $this->getUserOptionsLookupMock( $user, '2', [
			'skin' => Constants::SKIN_NAME_LEGACY
		] );

		$skinVersionLookup = new SkinVersionLookup( $request, $user, $config, $userOptionsLookup );

		$this->assertSame(
			'2',
			$skinVersionLookup->getVersion(),
			'If legacy skin is set with skin version modern, then the user gets modern skin still'
		);
	}

	/**
	 * @covers ::getVersion
	 */
	public function testSkin22() {
		$request = $this->getMockBuilder( \WebRequest::class )->getMock();
		$request
			->method( 'getVal' )
			->willReturn( '1' );
		$user = $this->createMock( \User::class );
		$user
			->method( 'isRegistered' )
			->willReturn( true );

		$config = new HashConfig( [
			'VectorSkinMigrationMode' => false,
			'VectorDefaultSkinVersion' => '1',
			'VectorDefaultSkinVersionForExistingAccounts' => '1'
		] );

		$userOptionsLookup = $this->getUserOptionsLookupMock( $user, '1', [
			'skin' => Constants::SKIN_NAME_MODERN
		] );

		$skinVersionLookup = new SkinVersionLookup( $request, $user, $config, $userOptionsLookup );

		$this->assertSame(
			'2',
			$skinVersionLookup->getVersion(),
			'Using the modern skin always returns 2. Ignores skinversion query string.'
		);
	}

	/**
	 * @covers ::getVersion
	 * @covers ::isLegacy
	 */
	public function testConfigAnon() {
		$request = $this->getMockBuilder( \WebRequest::class )->getMock();
		$request
			->method( 'getVal' )
			->willReturnCallback( static function ( $key ) {
				if ( $key === Constants::QUERY_PARAM_SKIN ) {
					return null;
				} else {
					return '2';
				}
			} );

		$user = $this->createMock( \User::class );
		$user
			->method( 'isRegistered' )
			->willReturn( false );

		$config = new HashConfig( [
			'VectorSkinMigrationMode' => false,
			'VectorDefaultSkinVersion' => '2',
			'VectorDefaultSkinVersionForExistingAccounts' => '1'
		] );

		$userOptionsLookup = $this->getUserOptionsLookupMock( $user, '2', [
			'skin' => Constants::SKIN_NAME_LEGACY,
		] );

		$skinVersionLookup = new SkinVersionLookup( $request, $user, $config, $userOptionsLookup );

		$this->assertSame(
			'2',
			$skinVersionLookup->getVersion(),
			'Config is the third priority and distinguishes anonymous users from logged in users.'
		);
		$this->assertSame(
			false,
			$skinVersionLookup->isLegacy(),
			'Version is non-Legacy.'
		);
	}

	/**
	 * @param User $user
	 * @param array $returnVal
	 * @param array $lookup values
	 * @return UserOptionsLookup
	 */
	private function getUserOptionsLookupMock( $user, $returnVal, $lookup = [] ) {
		$mock = $this->createMock( UserOptionsLookup::class );
		$mock->method( 'getOption' )
			->willReturnCallback( static function ( $user, $key ) use ( $returnVal, $lookup ) {
				return $lookup[ $key ] ?? $returnVal;
			} );
		return $mock;
	}
}
