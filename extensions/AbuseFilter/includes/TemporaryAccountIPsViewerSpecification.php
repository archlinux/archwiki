<?php

namespace MediaWiki\Extension\AbuseFilter;

use MediaWiki\CheckUser\Services\CheckUserPermissionManager;
use MediaWiki\Permissions\Authority;
use MediaWiki\User\TempUser\TempUserConfig;

class TemporaryAccountIPsViewerSpecification {

	public const SERVICE_NAME = ServiceNames::TemporaryAccountIPsViewerSpecification;

	public function __construct(
		private readonly TempUserConfig $tempUserConfig,
		private readonly ?CheckUserPermissionManager $checkUserPermissionManager,
	) {
	}

	/**
	 * Checks if the given Authority is allowed to see IPs associated with
	 * temporary accounts.
	 *
	 * @param Authority $performer Authority to check permissions for.
	 * @return bool Whether the Authority can see Temp Account IPs.
	 */
	public function isSatisfiedBy( Authority $performer ): bool {
		return $this->tempUserConfig->isKnown() &&
			$this->checkUserPermissionManager !== null &&
			$this->checkUserPermissionManager
				->canAccessTemporaryAccountIPAddresses( $performer )
				->isGood();
	}
}
