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
use User;
use Vector\Constants;
use Vector\FeatureManagement\Requirements\WvuiSearchTreatmentRequirement;

/**
 * @group Vector
 * @group FeatureManagement
 * @coversDefaultClass \Vector\FeatureManagement\Requirements\WvuiSearchTreatmentRequirement
 */
class WvuiSearchTreatmentRequirementTest extends \MediaWikiUnitTestCase {

	public function providerWvuiSearchTreatmentRequirement() {
		return [
			[
				// Is wvui search enabled
				false,
				// is A-B test enabled
				false,
				// note 0 = anon user
				0,
				false,
				'If nothing enabled nobody gets wvui search'
			],
			[
				// Is wvui search enabled
				true,
				// is A-B test enabled
				false,
				// note 0 = anon user
				0,
				true,
				'Anon users should get wvui search if enabled when A/B test disabled'
			],
			[
				// Is wvui search enabled
				true,
				// is A-B test enabled
				false,
				2,
				true,
				'Logged in users should get wvui search if enabled when A/B test disabled'
			],
			[
				// Is wvui search enabled
				true,
				// is A-B test enabled
				false,
				1,
				true,
				'All odd logged in users should get wvui search when A/B test disabled'
			],
			[
				// Is wvui search enabled
				true,
				// is A-B test enabled
				true,
				// note 0 = anon user
				0,
				true,
				'Anon users with a/b test enabled should see wvui search when search config enabled'
			],
			[
				// Is wvui search enabled
				true,
				// is A-B test enabled
				true,
				2,
				true,
				'Even logged in users get wvui search when A/B test enabled'
			],
			[
				// Is wvui search enabled
				true,
				// is A-B test enabled
				true,
				1,
				false,
				'Odd logged in users do not wvui search when A/B test enabled'
			],
		];
	}

	/**
	 * @covers ::isMet
	 * @dataProvider providerWvuiSearchTreatmentRequirement
	 * @param bool $wvuiSearchConfigValue
	 * @param bool $abValue
	 * @param int $userId
	 * @param bool $expected
	 * @param string $msg
	 */
	public function testWvuiSearchTreatmentRequirement(
		$wvuiSearchConfigValue, $abValue, $userId, $expected, $msg
	) {
		$config = new HashConfig( [
			Constants::CONFIG_KEY_USE_WVUI_SEARCH => $wvuiSearchConfigValue,
			Constants::CONFIG_SEARCH_TREATMENT_AB_TEST => $abValue,
		] );

		$user = $this->createMock( User::class );
		$user->method( 'isRegistered' )->willReturn( $userId !== 0 );
		$user->method( 'getID' )->willReturn( $userId );

		$requirement = new WvuiSearchTreatmentRequirement(
			$config, $user
		);

		$this->assertSame( $expected, $requirement->isMet(), $msg );
	}
}
