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

namespace Vector\FeatureManagement\Tests;

use HashConfig;
use MediaWiki\User\UserOptionsLookup;
use User;
use Vector\FeatureManagement\Requirements\LatestSkinVersionRequirement;
use Vector\SkinVersionLookup;
use WebRequest;

/**
 * @group Vector
 * @group FeatureManagement
 * @coversDefaultClass \Vector\FeatureManagement\Requirements\LatestSkinVersionRequirement
 */
class LatestSkinVersionRequirementTest extends \MediaWikiUnitTestCase {

	public function provideIsMet() {
		// $version, $expected, $msg
		yield 'not met' => [ '1', false, '"1" isn\'t considered latest.' ];
		yield 'met' => [ '2', true, '"2" is considered latest.' ];
	}

	/**
	 * @dataProvider provideIsMet
	 * @covers ::isMet
	 */
	public function testIsMet( $version, $expected, $msg ) {
		$config = new HashConfig( [
			'VectorSkinMigrationMode' => false,
			'VectorDefaultSkinVersionForExistingAccounts' => $version
		] );

		$user = $this->createMock( User::class );
		$user->method( 'isRegistered' )->willReturn( true );

		$userOptionsLookup = $this->createMock( UserOptionsLookup::class );
		$userOptionsLookup->method( 'getOption' )
			->will( $this->returnArgument( 2 ) );

		$request = $this->createMock( WebRequest::class );
		$request->method( 'getVal' )
			->will( $this->returnArgument( 1 ) );

		$requirement = new LatestSkinVersionRequirement(
			new SkinVersionLookup(
				$request,
				$user,
				$config,
				$userOptionsLookup
			)
		);

		$this->assertSame( $expected, $requirement->isMet(), $msg );
	}

}
