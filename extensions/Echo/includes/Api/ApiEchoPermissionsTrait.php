<?php

namespace MediaWiki\Extension\Notifications\Api;

use MediaWiki\Api\ApiBase;
use MediaWiki\Context\ContextSource;
use MediaWiki\Message\Message;
use MediaWiki\Permissions\Authority;
use Wikimedia\Message\MessageSpecifier;

trait ApiEchoPermissionsTrait {

	/**
	 * @see ContextSource::getAuthority()
	 */
	abstract public function getAuthority(): Authority;

	/**
	 * @param string|array|MessageSpecifier $msg See ApiErrorFormatter::addError()
	 * @param string|null $code See ApiErrorFormatter::addError()
	 * @param array|null $data See ApiErrorFormatter::addError()
	 * @param int $httpCode HTTP error code to use
	 * @see ApiBase::dieWithError()
	 */
	abstract public function dieWithError( $msg, $code = null, $data = null, $httpCode = 0 ): never;

	/**
	 * @param string $key
	 * @return Message
	 */
	abstract public function msg( $key );

	/**
	 * Ensure the user has read access to their own notifications
	 *
	 * Calls dieWithError() with appropriate messaging if omitted.
	 *
	 * @return void
	 */
	private function checkReadNotificationsPermissions(): void {
		if ( !$this->getAuthority()->isRegistered() ) {
			$this->dieWithError( 'apierror-mustbeloggedin-generic', 'login-required' );
		}
		if ( !$this->getAuthority()->isAllowed( 'echo-read-notifications' ) ) {
			$this->dieWithError( [
				'apierror-permissiondenied',
				$this->msg( 'action-echo-read-notifications' ),
			] );
		}
	}
}
