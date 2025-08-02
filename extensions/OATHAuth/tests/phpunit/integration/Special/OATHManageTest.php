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

namespace MediaWiki\Extension\OATHAuth\Tests\Integration\Special;

use MediaWiki\Extension\OATHAuth\OATHAuthServices;
use MediaWiki\Extension\OATHAuth\Special\OATHManage;
use MediaWiki\MainConfigNames;
use SpecialPageTestBase;

/**
 * @author Taavi Väänänen <hi@taavi.wtf>
 * @group Database
 * @coversDefaultClass \MediaWiki\Extension\OATHAuth\Special\OATHManage
 */
class OATHManageTest extends SpecialPageTestBase {
	protected function setUp(): void {
		parent::setUp();
		$this->overrideConfigValue( MainConfigNames::CentralIdLookupProvider, 'local' );
	}

	protected function newSpecialPage() {
		$services = OATHAuthServices::getInstance( $this->getServiceContainer() );
		return new OATHManage(
			$services->getUserRepository(),
			$services->getModuleRegistry(),
		);
	}

	/**
	 * @covers ::execute
	 */
	public function testPageLoads() {
		$this->executeSpecialPage(
			'',
			null,
			null,
			$this->getTestUser()->getAuthority(),
		);

		$this->addToAssertionCount( 1 );
	}
}
