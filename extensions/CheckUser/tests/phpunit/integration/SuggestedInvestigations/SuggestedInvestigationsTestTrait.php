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

namespace MediaWiki\Extension\CheckUser\Tests\Integration\SuggestedInvestigations;

/**
 * A helper trait for tests that interact with Suggested Investigations functionality.
 */
trait SuggestedInvestigationsTestTrait {

	private function enableSuggestedInvestigations(): void {
		$this->overrideConfigValue( 'CheckUserSuggestedInvestigationsEnabled', true );
	}

	private function disableSuggestedInvestigations(): void {
		$this->overrideConfigValue( 'CheckUserSuggestedInvestigationsEnabled', false );
	}

	private function hideSuggestedInvestigations(): void {
		$this->overrideConfigValue( 'CheckUserSuggestedInvestigationsHidden', true );
	}

	private function unhideSuggestedInvestigations(): void {
		$this->overrideConfigValue( 'CheckUserSuggestedInvestigationsHidden', false );
	}

	/**
	 * Defined by MediaWikiIntegrationTestCase, we declare it here so that
	 * we can be sure it exists in the classes using this trait.
	 *
	 * @param string $key config option name
	 * @param mixed $value The value to set it to
	 * {@link MediaWikiIntegrationTestCase::overrideConfigValue}
	 */
	abstract protected function overrideConfigValue( string $key, $value );
}
