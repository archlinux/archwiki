<?php
/**
 * @license GPL-2.0-or-later
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
