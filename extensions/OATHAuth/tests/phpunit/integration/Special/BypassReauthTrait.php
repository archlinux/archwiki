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
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 * @file
 */

namespace MediaWiki\Extension\OATHAuth\Tests\Integration\Special;

use MediaWiki\Auth\AuthManager;

/**
 * @author Taavi Väänänen
 */
trait BypassReauthTrait {
	// TODO: this could maybe even go in core?

	/**
	 * Registers the given hook handler for the duration of the current test case.
	 * @see {@link \MediaWikiIntegrationTestCase::setTemporaryHook()}
	 *
	 * @param string $hookName
	 * @param mixed $handler Value suitable for a hook handler
	 * @param bool $replace (optional) Default is to replace all existing handlers for the given hook.
	 *         Set false to add to the existing handler list.
	 */
	abstract protected function setTemporaryHook( $hookName, $handler, $replace = true );

	/**
	 * Ensure requests made within this special page test suite do not get caught
	 * by the AuthManager security re-auth mechanism.
	 */
	protected function bypassReauthentication() {
		$this->setTemporaryHook(
			'SecuritySensitiveOperationStatus',
			static function ( &$status, $operation, $session, $timeSinceAuth ) {
				// Bypass re-authentication prompts
				$status = AuthManager::SEC_OK;
			},
		);
	}
}
