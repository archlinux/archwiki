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

use MediaWiki\Extension\OATHAuth\OATHAuthDatabase;
use MediaWiki\Extension\OATHAuth\OATHAuthModuleRegistry;

/**
 * @author Taavi Väänänen <hi@taavi.wtf>
 * @group Database
 */
class OATHAuthModuleRegistryTest extends MediaWikiIntegrationTestCase {
	/** @var string[] */
	protected $tablesUsed = [ 'oathauth_types' ];

	/**
	 * @covers \MediaWiki\Extension\OATHAuth\OATHAuthModuleRegistry::getModuleIds
	 */
	public function testGetModuleIds() {
		$this->db->insert(
			'oathauth_types',
			[ 'oat_name' => 'first' ],
			__METHOD__
		);

		$database = $this->createMock( OATHAuthDatabase::class );
		$database->method( 'getDB' )->willReturn( $this->db );

		$registry = new OATHAuthModuleRegistry(
			$database,
			[
				'first'  => 'does not matter',
				'second' => 'does not matter',
				'third'  => 'does not matter',
			]
		);

		$this->assertEquals(
			[ 'first', 'second', 'third' ],
			array_keys( $registry->getModuleIds() )
		);
	}
}
