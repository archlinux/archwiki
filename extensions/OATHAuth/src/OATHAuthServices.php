<?php
/**
 * @license GPL-2.0-or-later
 *
 * @file
 */

namespace MediaWiki\Extension\OATHAuth;

use MediaWiki\Extension\OATHAuth\Enforce2FA\Mandatory2FAChecker;
use MediaWiki\Extension\OATHAuth\Key\EncryptionHelper;
use MediaWiki\MediaWikiServices;

/**
 * Type-safe wrapper for accessing OATHAuth services.
 *
 * @author Taavi Väänänen <hi@taavi.wtf>
 */
class OATHAuthServices {
	public function __construct( private readonly MediaWikiServices $services ) {
	}

	public static function getInstance( ?MediaWikiServices $services = null ): self {
		return new self(
			$services ?? MediaWikiServices::getInstance(),
		);
	}

	public function getEncryptionHelper(): EncryptionHelper {
		return $this->services->getService( 'OATHAuth.EncryptionHelper' );
	}

	public function getLogger(): OATHAuthLogger {
		return $this->services->getService( 'OATHAuth.Logger' );
	}

	public function getMandatory2FAChecker(): Mandatory2FAChecker {
		return $this->services->getService( 'OATHAuth.Mandatory2FAChecker' );
	}

	public function getModuleRegistry(): OATHAuthModuleRegistry {
		return $this->services->getService( 'OATHAuth.ModuleRegistry' );
	}

	public function getUserRepository(): OATHUserRepository {
		return $this->services->getService( 'OATHAuth.UserRepository' );
	}

	public function getWebAuthnAuthenticator(): WebAuthnAuthenticator {
		return $this->services->getService( 'OATHAuth.WebAuthnAuthenticator' );
	}
}
