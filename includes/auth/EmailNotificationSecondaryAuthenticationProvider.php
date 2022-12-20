<?php

namespace MediaWiki\Auth;

use MediaWiki\MainConfigNames;
use Wikimedia\Rdbms\ILoadBalancer;

/**
 * Handles email notification / email address confirmation for account creation.
 *
 * Set 'no-email' to true (via AuthManager::setAuthenticationSessionData) to skip this provider.
 * Primary providers doing so are expected to take care of email address confirmation.
 */
class EmailNotificationSecondaryAuthenticationProvider
	extends AbstractSecondaryAuthenticationProvider
{
	/** @var bool */
	protected $sendConfirmationEmail;

	/** @var ILoadBalancer */
	private $loadBalancer;

	/**
	 * @param ILoadBalancer $loadBalancer
	 * @param array $params
	 *  - sendConfirmationEmail: (bool) send an email asking the user to confirm their email
	 *    address after a successful registration
	 */
	public function __construct( ILoadBalancer $loadBalancer, $params = [] ) {
		if ( isset( $params['sendConfirmationEmail'] ) ) {
			$this->sendConfirmationEmail = (bool)$params['sendConfirmationEmail'];
		}
		$this->loadBalancer = $loadBalancer;
	}

	protected function postInitSetup() {
		if ( $this->sendConfirmationEmail === null ) {
			$this->sendConfirmationEmail = $this->config->get( MainConfigNames::EnableEmail )
				&& $this->config->get( MainConfigNames::EmailAuthentication );
		}
	}

	public function getAuthenticationRequests( $action, array $options ) {
		return [];
	}

	public function beginSecondaryAuthentication( $user, array $reqs ) {
		return AuthenticationResponse::newAbstain();
	}

	public function beginSecondaryAccountCreation( $user, $creator, array $reqs ) {
		if (
			$this->sendConfirmationEmail
			&& $user->getEmail()
			&& !$this->manager->getAuthenticationSessionData( 'no-email' )
		) {
			// TODO show 'confirmemail_oncreate'/'confirmemail_sendfailed' message
			$this->loadBalancer->getConnectionRef( DB_PRIMARY )->onTransactionCommitOrIdle(
				function () use ( $user ) {
					$user = $user->getInstanceForUpdate();
					$status = $user->sendConfirmationMail();
					$user->saveSettings();
					if ( !$status->isGood() ) {
						$this->logger->warning( 'Could not send confirmation email: ' .
							$status->getWikiText( false, false, 'en' ) );
					}
				},
				__METHOD__
			);
		}

		return AuthenticationResponse::newPass();
	}
}
