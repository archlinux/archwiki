<?php

namespace MediaWiki\Extension\OATHAuth\HTMLForm;

use MediaWiki\Extension\OATHAuth\IAuthKey;
use MediaWiki\Extension\OATHAuth\Key\RecoveryCodeKeys;
use MediaWiki\Extension\OATHAuth\Key\TOTPKey;
use MediaWiki\Request\WebRequest;

/**
 * Helper class to display and manage recovery codes within various contexts
 */
trait KeySessionStorageTrait {

	/** @return WebRequest */
	abstract public function getRequest();

	/**
	 * Helper function to generically track IAuthKeys in the user session
	 *
	 * @param string $keyType accepts current key names (TOTPKey, RecoveryCodeKeys)
	 * @return RecoveryCodeKeys|TOTPKey|null
	 */
	public function setKeyDataInSession( string $keyType, array $keyData = [] ) {
		// RecoveryCodeKeys or TOTPKey
		$key = null;
		$sessionKey = $this->getSessionKeyName( $keyType );
		if ( count( $keyData ) === 0 ) {
			$keyData = $this->getRequest()->getSession()->getSecret( $sessionKey, [] ) ?? [];
		}

		// TODO: Ideally determine key type via instanceof or ::class instead of strings
		if ( $keyType === 'TOTPKey' ) {
			$key = TOTPKey::newFromArray( $keyData );
			if ( !$key instanceof TOTPKey ) {
				$key = TOTPKey::newFromRandom();
			}
		} elseif ( $keyType === 'RecoveryCodeKeys' ) {
			$key = RecoveryCodeKeys::newFromArray( $keyData );
			if ( array_key_exists( 'recoverycodekeys', $keyData ) && count( $keyData['recoverycodekeys'] ) === 0 ) {
				$key->regenerateRecoveryCodeKeys();
			}
		}

		if ( $key instanceof IAuthKey ) {
			$this->getRequest()->getSession()->setSecret(
				$sessionKey,
				$key->jsonSerialize()
			);
		} else {
			// set the session key to empty
			$this->getRequest()->getSession()->setSecret(
				$sessionKey,
				[]
			);
		}

		return $key;
	}

	/**
	 * @return array|null
	 */
	public function getKeyDataInSession( string $keyType ) {
		return $this->getRequest()->getSession()->getSecret( $this->getSessionKeyName( $keyType ) );
	}

	public function setKeyDataInSessionToNull( string $keyType ): void {
		$this->getRequest()->getSession()->setSecret( $this->getSessionKeyName( $keyType ), null );
	}

	private function getSessionKeyName( string $keyType ): string {
		return $keyType . '_oathauth_key';
	}
}
