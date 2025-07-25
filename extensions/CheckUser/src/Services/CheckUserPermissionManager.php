<?php
namespace MediaWiki\CheckUser\Services;

use MediaWiki\CheckUser\CheckUserPermissionStatus;
use MediaWiki\Permissions\Authority;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\User\CentralId\CentralIdLookup;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserRigorOptions;
use Wikimedia\IPUtils;

/**
 * Perform CheckUser-related permission checks.
 */
class CheckUserPermissionManager {
	private UserOptionsLookup $userOptionsLookup;

	private SpecialPageFactory $specialPageFactory;

	private CentralIdLookup $centralIdLookup;

	private UserFactory $userFactory;

	public function __construct(
		UserOptionsLookup $userOptionsLookup,
		SpecialPageFactory $specialPageFactory,
		CentralIdLookup $centralIdLookup,
		UserFactory $userFactory
	) {
		$this->userOptionsLookup = $userOptionsLookup;
		$this->specialPageFactory = $specialPageFactory;
		$this->centralIdLookup = $centralIdLookup;
		$this->userFactory = $userFactory;
	}

	/**
	 * Check whether the given Authority is allowed to view IP addresses for temporary accounts.
	 * @param Authority $authority The user attempting to view IP addresses for temporary accounts.
	 * @return CheckUserPermissionStatus
	 */
	public function canAccessTemporaryAccountIPAddresses( Authority $authority ): CheckUserPermissionStatus {
		// If the user isn't authorized to view temporary account IP data without having to accept the
		// agreement, ensure they have relevant rights and have accepted the agreement.
		if ( !$authority->isAllowed( 'checkuser-temporary-account-no-preference' ) ) {
			if ( !$authority->isAllowed( 'checkuser-temporary-account' ) ) {
				return CheckUserPermissionStatus::newPermissionError( 'checkuser-temporary-account' );
			}

			if ( !$this->userOptionsLookup->getOption( $authority->getUser(), 'checkuser-temporary-account-enable' ) ) {
				return CheckUserPermissionStatus::newFatal(
					'checkuser-tempaccount-reveal-ip-permission-error-description'
				);
			}
		}

		$block = $authority->getBlock();
		if ( $block !== null && $block->isSitewide() ) {
			return CheckUserPermissionStatus::newBlockedError( $block );
		}

		return CheckUserPermissionStatus::newGood();
	}

	/**
	 * Check whether the given Authority is allowed to automatically reveal IP addresses for temporary
	 * accounts.
	 *
	 * @param Authority $authority The user attempting to auto-reveal IP addresses for temporary accounts.
	 * @return CheckUserPermissionStatus
	 */
	public function canAutoRevealIPAddresses( Authority $authority ): CheckUserPermissionStatus {
		$revealIPStatus = $this->canAccessTemporaryAccountIPAddresses( $authority );
		if ( !$revealIPStatus->isGood() ) {
			return $revealIPStatus;
		}

		if ( !$authority->isAllowed( 'checkuser-temporary-account-auto-reveal' ) ) {
			return CheckUserPermissionStatus::newPermissionError( 'checkuser-temporary-account-auto-reveal' );
		}

		return CheckUserPermissionStatus::newGood();
	}

	/**
	 * Checks whether the given Authority is allowed to view the Global
	 * Contributions page for a given user.
	 *
	 * @see https://www.mediawiki.org/wiki/Extension:CheckUser
	 *
	 * The permission is granted if the GlobalContributions page itself is
	 * available and the accessing authority is a registered user. In case
	 * $target is not an IP range, an additional constraint is tested to check
	 * if $target contains a username having a corresponding CentralAuth user.
	 *
	 * @param Authority $authority
	 * @param string $target
	 * @return CheckUserPermissionStatus
	 */
	public function canAccessUserGlobalContributions(
		Authority $authority,
		string $target
	): CheckUserPermissionStatus {
		if ( !$this->specialPageFactory->exists( 'GlobalContributions' ) ) {
			return CheckUserPermissionStatus::newFatal(
				'nospecialpagetext'
			);
		}

		if ( !$authority->isRegistered() ) {
			return CheckUserPermissionStatus::newFatal(
				'exception-nologin-text'
			);
		}

		// There is no concept of Central Users for IP addresses or ranges, but
		// GlobalContributions can still be used to list revisions given an IP
		// address or range: Therefore, checking for a corresponding CentralAuth
		// user is skipped for IPs.
		if ( !IPUtils::isIPAddress( $target ) ) {
			$targetIdentity = $this->userFactory->newFromName(
				$target,
				UserRigorOptions::RIGOR_NONE
			);

			if ( $targetIdentity === null ) {
				return CheckUserPermissionStatus::newFatal(
					'checkuser-target-nonexistent'
				);
			}

			if ( !$this->centralAuthUserExists( $authority, $targetIdentity ) ) {
				return CheckUserPermissionStatus::newFatal(
					'checkuser-global-contributions-no-results-no-central-user'
				);
			}
		}

		return CheckUserPermissionStatus::newGood();
	}

	private function centralAuthUserExists(
		Authority $accessingAuthority,
		UserIdentity $targetIdentity
	): bool {
		if ( !$targetIdentity->isRegistered() ) {
			return false;
		}

		$centralId = $this->centralIdLookup->centralIdFromLocalUser(
			$targetIdentity,
			$accessingAuthority
		);

		return $centralId !== 0;
	}
}
