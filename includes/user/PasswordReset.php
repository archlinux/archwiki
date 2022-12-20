<?php
/**
 * User password reset helper for MediaWiki.
 *
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

use MediaWiki\Auth\AuthManager;
use MediaWiki\Auth\TemporaryPasswordAuthenticationRequest;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\HookContainer\HookRunner;
use MediaWiki\MainConfigNames;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserNameUtils;
use MediaWiki\User\UserOptionsLookup;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Wikimedia\Rdbms\ILoadBalancer;

/**
 * Helper class for the password reset functionality shared by the web UI and the API.
 *
 * Requires the TemporaryPasswordPrimaryAuthenticationProvider and the
 * EmailNotificationSecondaryAuthenticationProvider (or something providing equivalent
 * functionality) to be enabled.
 */
class PasswordReset implements LoggerAwareInterface {
	use LoggerAwareTrait;

	/** @var ServiceOptions */
	private $config;

	/** @var AuthManager */
	private $authManager;

	/** @var HookRunner */
	private $hookRunner;

	/** @var ILoadBalancer */
	private $loadBalancer;

	/** @var UserFactory */
	private $userFactory;

	/** @var UserNameUtils */
	private $userNameUtils;

	/** @var UserOptionsLookup */
	private $userOptionsLookup;

	/**
	 * In-process cache for isAllowed lookups, by username.
	 * Contains a StatusValue object
	 * @var MapCacheLRU
	 */
	private $permissionCache;

	/**
	 * @internal For use by ServiceWiring
	 */
	public const CONSTRUCTOR_OPTIONS = [
		MainConfigNames::AllowRequiringEmailForResets,
		MainConfigNames::EnableEmail,
		MainConfigNames::PasswordResetRoutes,
	];

	/**
	 * This class is managed by MediaWikiServices, don't instantiate directly.
	 *
	 * @param ServiceOptions $config
	 * @param LoggerInterface $logger
	 * @param AuthManager $authManager
	 * @param HookContainer $hookContainer
	 * @param ILoadBalancer $loadBalancer
	 * @param UserFactory $userFactory
	 * @param UserNameUtils $userNameUtils
	 * @param UserOptionsLookup $userOptionsLookup
	 */
	public function __construct(
		ServiceOptions $config,
		LoggerInterface $logger,
		AuthManager $authManager,
		HookContainer $hookContainer,
		ILoadBalancer $loadBalancer,
		UserFactory $userFactory,
		UserNameUtils $userNameUtils,
		UserOptionsLookup $userOptionsLookup
	) {
		$config->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );

		$this->config = $config;
		$this->logger = $logger;

		$this->authManager = $authManager;
		$this->hookRunner = new HookRunner( $hookContainer );
		$this->loadBalancer = $loadBalancer;
		$this->userFactory = $userFactory;
		$this->userNameUtils = $userNameUtils;
		$this->userOptionsLookup = $userOptionsLookup;

		$this->permissionCache = new MapCacheLRU( 1 );
	}

	/**
	 * Check if a given user has permission to use this functionality.
	 * @param User $user
	 * @since 1.29 Second argument for displayPassword removed.
	 * @return StatusValue
	 */
	public function isAllowed( User $user ) {
		return $this->permissionCache->getWithSetCallback(
			$user->getName(),
			function () use ( $user ) {
				return $this->computeIsAllowed( $user );
			}
		);
	}

	/**
	 * @param User $user
	 * @return StatusValue
	 */
	private function computeIsAllowed( User $user ): StatusValue {
		$resetRoutes = $this->config->get( MainConfigNames::PasswordResetRoutes );
		$status = StatusValue::newGood();

		if ( !is_array( $resetRoutes ) || !in_array( true, $resetRoutes, true ) ) {
			// Maybe password resets are disabled, or there are no allowable routes
			$status = StatusValue::newFatal( 'passwordreset-disabled' );
		} elseif (
			( $providerStatus = $this->authManager->allowsAuthenticationDataChange(
				new TemporaryPasswordAuthenticationRequest(), false ) )
			&& !$providerStatus->isGood()
		) {
			// Maybe the external auth plugin won't allow local password changes
			$status = StatusValue::newFatal( 'resetpass_forbidden-reason',
				$providerStatus->getMessage() );
		} elseif ( !$this->config->get( MainConfigNames::EnableEmail ) ) {
			// Maybe email features have been disabled
			$status = StatusValue::newFatal( 'passwordreset-emaildisabled' );
		} elseif ( !$user->isAllowed( 'editmyprivateinfo' ) ) {
			// Maybe not all users have permission to change private data
			$status = StatusValue::newFatal( 'badaccess' );
		} elseif ( $this->isBlocked( $user ) ) {
			// Maybe the user is blocked (check this here rather than relying on the parent
			// method as we have a more specific error message to use here and we want to
			// ignore some types of blocks)
			$status = StatusValue::newFatal( 'blocked-mailpassword' );
		}
		return $status;
	}

	/**
	 * Do a password reset. Authorization is the caller's responsibility.
	 *
	 * Process the form.  At this point we know that the user passes all the criteria in
	 * userCanExecute(), and if the data array contains 'Username', etc, then Username
	 * resets are allowed.
	 *
	 * @since 1.29 Fourth argument for displayPassword removed.
	 * @param User $performingUser The user that does the password reset
	 * @param string|null $username The user whose password is reset
	 * @param string|null $email Alternative way to specify the user
	 * @return StatusValue
	 * @throws LogicException When the user is not allowed to perform the action
	 * @throws MWException On unexpected DB errors
	 */
	public function execute(
		User $performingUser,
		$username = null,
		$email = null
	) {
		if ( !$this->isAllowed( $performingUser )->isGood() ) {
			throw new LogicException(
				'User ' . $performingUser->getName() . ' is not allowed to reset passwords'
			);
		}

		// Check against the rate limiter. If the $wgRateLimit is reached, we want to pretend
		// that the request was good to avoid displaying an error message.
		if ( $performingUser->pingLimiter( 'mailpassword' ) ) {
			return StatusValue::newGood();
		}

		// We need to have a valid IP address for the hook 'User::mailPasswordInternal', but per T20347,
		// we should send the user's name if they're logged in.
		$ip = $performingUser->getRequest()->getIP();
		if ( !$ip ) {
			return StatusValue::newFatal( 'badipaddress' );
		}

		$username = $username ?? '';
		$email = $email ?? '';

		$resetRoutes = $this->config->get( MainConfigNames::PasswordResetRoutes )
			+ [ 'username' => false, 'email' => false ];
		if ( $resetRoutes['username'] && $username ) {
			$method = 'username';
			$users = [ $this->userFactory->newFromName( $username ) ];
		} elseif ( $resetRoutes['email'] && $email ) {
			if ( !Sanitizer::validateEmail( $email ) ) {
				// Only email was supplied but not valid: pretend everything's fine.
				return StatusValue::newGood();
			}
			// Only email was provided
			$method = 'email';
			$users = $this->getUsersByEmail( $email );
			$username = null;
			// Remove users whose preference 'requireemail' is on since username was not submitted
			if ( $this->config->get( MainConfigNames::AllowRequiringEmailForResets ) ) {
				$optionsLookup = $this->userOptionsLookup;
				foreach ( $users as $index => $user ) {
					if ( $optionsLookup->getBoolOption( $user, 'requireemail' ) ) {
						unset( $users[$index] );
					}
				}
			}
		} else {
			// The user didn't supply any data
			return StatusValue::newFatal( 'passwordreset-nodata' );
		}

		// If the username is not valid, tell the user.
		if ( $username && !$this->userNameUtils->getCanonical( $username ) ) {
			return StatusValue::newFatal( 'noname' );
		}

		// Check for hooks (captcha etc), and allow them to modify the users list
		$error = [];
		$data = [
			'Username' => $username,
			// Email gets set to null for backward compatibility
			'Email' => $method === 'email' ? $email : null,
		];

		// Recreate the $users array with its values so that we reset the numeric keys since
		// the key '0' might have been unset from $users array. 'SpecialPasswordResetOnSubmit'
		// hook assumes that index '0' is defined if $users is not empty.
		$users = array_values( $users );

		if ( !$this->hookRunner->onSpecialPasswordResetOnSubmit( $users, $data, $error ) ) {
			return StatusValue::newFatal( Message::newFromSpecifier( $error ) );
		}

		// Get the first element in $users by using `reset` function just in case $users is changed
		// in 'SpecialPasswordResetOnSubmit' hook.
		$firstUser = reset( $users );

		$requireEmail = $this->config->get( MainConfigNames::AllowRequiringEmailForResets )
			&& $method === 'username'
			&& $firstUser
			&& $this->userOptionsLookup->getBoolOption( $firstUser, 'requireemail' );
		if ( $requireEmail && ( $email === '' || !Sanitizer::validateEmail( $email ) ) ) {
			// Email is required, and not supplied or not valid: pretend everything's fine.
			return StatusValue::newGood();
		}

		if ( !$users ) {
			if ( $method === 'email' ) {
				// Don't reveal whether or not an email address is in use
				return StatusValue::newGood();
			} else {
				return StatusValue::newFatal( 'noname' );
			}
		}

		// If the user doesn't exist, or if the user doesn't have an email address,
		// don't disclose the information. We want to pretend everything is ok per T238961.
		// Note that all the users will have the same email address (or none),
		// so there's no need to check more than the first.
		if ( !$firstUser instanceof User || !$firstUser->getId() || !$firstUser->getEmail() ) {
			return StatusValue::newGood();
		}

		// Email is required but the email doesn't match: pretend everything's fine.
		if ( $requireEmail && $firstUser->getEmail() !== $email ) {
			return StatusValue::newGood();
		}

		$this->hookRunner->onUser__mailPasswordInternal( $performingUser, $ip, $firstUser );

		$result = StatusValue::newGood();
		$reqs = [];
		foreach ( $users as $user ) {
			$req = TemporaryPasswordAuthenticationRequest::newRandom();
			$req->username = $user->getName();
			$req->mailpassword = true;
			$req->caller = $performingUser->getName();

			$status = $this->authManager->allowsAuthenticationDataChange( $req, true );
			// If status is good and the value is 'throttled-mailpassword', we want to pretend
			// that the request was good to avoid displaying an error message and disclose
			// if a reset password was previously sent.
			if ( $status->isGood() && $status->getValue() === 'throttled-mailpassword' ) {
				return StatusValue::newGood();
			}

			if ( $status->isGood() && $status->getValue() !== 'ignored' ) {
				$reqs[] = $req;
			} elseif ( $result->isGood() ) {
				// only record the first error, to avoid exposing the number of users having the
				// same email address
				if ( $status->getValue() === 'ignored' ) {
					$status = StatusValue::newFatal( 'passwordreset-ignored' );
				}
				$result->merge( $status );
			}
		}

		$logContext = [
			'requestingIp' => $ip,
			'requestingUser' => $performingUser->getName(),
			'targetUsername' => $username,
			'targetEmail' => $email,
		];

		if ( !$result->isGood() ) {
			$this->logger->info(
				"{requestingUser} attempted password reset of {actualUser} but failed",
				$logContext + [ 'errors' => $result->getErrors() ]
			);
			return $result;
		}

		DeferredUpdates::addUpdate(
			new SendPasswordResetEmailUpdate( $this->authManager, $reqs, $logContext ),
			DeferredUpdates::POSTSEND
		);

		return StatusValue::newGood();
	}

	/**
	 * Check whether the user is blocked.
	 * Ignores certain types of system blocks that are only meant to force users to log in.
	 * @param User $user
	 * @return bool
	 * @since 1.30
	 */
	private function isBlocked( User $user ) {
		$block = $user->getBlock() ?: $user->getGlobalBlock();
		if ( !$block ) {
			return false;
		}
		return $block->appliesToPasswordReset();
	}

	/**
	 * @note This is protected to allow configuring in tests. This class is not stable to extend.
	 *
	 * @param string $email
	 * @return User[]
	 * @throws MWException On unexpected database errors
	 */
	protected function getUsersByEmail( $email ) {
		$userQuery = User::getQueryInfo();
		$res = $this->loadBalancer->getConnectionRef( DB_REPLICA )->select(
			$userQuery['tables'],
			$userQuery['fields'],
			[ 'user_email' => $email ],
			__METHOD__,
			[],
			$userQuery['joins']
		);

		if ( !$res ) {
			// Some sort of database error, probably unreachable
			throw new MWException( 'Unknown database error in ' . __METHOD__ );
		}

		$users = [];
		foreach ( $res as $row ) {
			$users[] = $this->userFactory->newFromRow( $row );
		}
		return $users;
	}

}
