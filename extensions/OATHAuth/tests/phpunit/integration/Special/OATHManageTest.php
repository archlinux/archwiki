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

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\OATHAuth\OATHAuthServices;
use MediaWiki\Extension\OATHAuth\Special\OATHManage;
use MediaWiki\MainConfigNames;
use SpecialPageTestBase;

/**
 * @author Taavi Väänänen <hi@taavi.wtf>
 * @group Database
 * @covers \MediaWiki\Extension\OATHAuth\Special\OATHManage
 */
class OATHManageTest extends SpecialPageTestBase {
	use BypassReauthTrait;

	protected function setUp(): void {
		parent::setUp();

		$this->overrideConfigValue( MainConfigNames::CentralIdLookupProvider, 'local' );
		$this->bypassReauthentication();
	}

	protected function newSpecialPage() {
		$services = OATHAuthServices::getInstance( $this->getServiceContainer() );
		return new OATHManage(
			$services->getUserRepository(),
			$services->getModuleRegistry(),
			$this->getServiceContainer()->getAuthManager(),
		);
	}

	public function testPageLoads() {
		$user = $this->getTestUser()->getUser();
		RequestContext::getMain()->getRequest()->getSession()->setUser( $user );

		[ $html ] = $this->executeSpecialPage(
			'',
			null,
			null,
			$user,
		);
		$this->assertStringContainsString( '(oathmanage-summary)', $html );
	}

}
