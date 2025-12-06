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

namespace MediaWiki\CheckUser\Tests\Unit\GlobalContributions;

use MediaWiki\CheckUser\GlobalContributions\ExternalPermissions;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\CheckUser\GlobalContributions\ExternalPermissions
 */
class ExternalPermissionsTest extends MediaWikiUnitTestCase {

	public function testHasExternalPermission() {
		$permissions = new ExternalPermissions( [
			'wiki1' => [
				'deletedhistory' => [],
				'suppressrevision' => [
					'code' => 'permissiondenied',
					'text' => 'Error text here',
				],
			],
		] );

		$this->assertTrue( $permissions->hasPermission( 'deletedhistory', 'wiki1' ) );
		$this->assertFalse( $permissions->hasPermission( 'suppressrevision', 'wiki1' ) );
		$this->assertFalse( $permissions->hasPermission( 'deletedtext', 'wiki1' ) );
		$this->assertFalse( $permissions->hasPermission( 'deletedhistory', 'wiki2' ) );
	}

	public function testGetPermissionsOnWiki() {
		$permissions = new ExternalPermissions( [
			'wiki1' => [
				'deletedhistory' => [],
				'suppressrevision' => [
					'code' => 'permissiondenied',
					'text' => 'Error text here',
				],
			],
		] );

		$this->assertSame(
			[ 'deletedhistory' ],
			$permissions->getPermissionsOnWiki( 'wiki1' )
		);
		$this->assertSame(
			[],
			$permissions->getPermissionsOnWiki( 'wiki2' )
		);
	}

	public function testHasAnyWiki() {
		$permissions = new ExternalPermissions( [] );
		$this->assertFalse( $permissions->hasAnyWiki(), 'Should have no wikis' );

		$permissions = new ExternalPermissions( [
			'wiki1' => [
				'deletedhistory' => [],
			],
		] );
		$this->assertTrue( $permissions->hasAnyWiki(), 'Should have some wikis' );

		$permission = new ExternalPermissions( [ 'wiki1' => [] ] );
		$this->assertTrue( $permission->hasAnyWiki(), 'Should have some wikis even if there are no rights on it' );
	}

	public function testGetKnownWikis() {
		$permissions = new ExternalPermissions( [] );
		$this->assertSame( [], $permissions->getKnownWikis() );

		$permissions = new ExternalPermissions( [
			'wiki1' => [
				'deletedhistory' => [],
			],
			'wiki2' => [
				'suppressrevision' => [
					'code' => 'permissiondenied',
					'text' => 'Error text here',
				],
			],
			'wiki3' => [],
		] );
		$this->assertSame(
			[ 'wiki1', 'wiki2', 'wiki3' ],
			$permissions->getKnownWikis()
		);
	}

	public function testHasEncounteredLookupError() {
		$permissions = new ExternalPermissions( [], true );
		$this->assertTrue( $permissions->hasEncounteredLookupError() );

		$permissions = new ExternalPermissions( [], false );
		$this->assertFalse( $permissions->hasEncounteredLookupError() );
	}
}
