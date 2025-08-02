<?php

namespace MediaWiki\CheckUser\HookHandler;

use GlobalPreferences\Hook\GlobalPreferencesSetGlobalPreferencesHook;
use MediaWiki\CheckUser\Logging\TemporaryAccountLoggerFactory;
use MediaWiki\User\UserIdentity;
use Wikimedia\Timestamp\ConvertibleTimestamp;

class GlobalPreferencesHandler implements GlobalPreferencesSetGlobalPreferencesHook {
	private TemporaryAccountLoggerFactory $loggerFactory;

	public function __construct(
		TemporaryAccountLoggerFactory $loggerFactory
	) {
		$this->loggerFactory = $loggerFactory;
	}

	/**
	 * @inheritDoc
	 */
	public function onGlobalPreferencesSetGlobalPreferences(
		UserIdentity $user,
		array $oldPreferences,
		array $newPreferences
	): void {
		// IP reveal access
		$wasEnabled = (bool)( $oldPreferences['checkuser-temporary-account-enable'] ?? false );
		$wasDisabled = !$wasEnabled;

		$willEnable = (bool)( $newPreferences['checkuser-temporary-account-enable'] ?? false );
		$willDisable = !$willEnable;

		if (
			( $wasDisabled && $willEnable ) ||
			( $wasEnabled && $willDisable )
		) {
			$logger = $this->loggerFactory->getLogger();
			if ( $willEnable ) {
				$logger->logGlobalAccessEnabled( $user );
			} else {
				$logger->logGlobalAccessDisabled( $user );
			}
		}

		// IP auto-reveal mode
		$timeNow = ConvertibleTimestamp::time();
		$oldExpiry = $oldPreferences[Preferences::ENABLE_IP_AUTO_REVEAL] ?? 0;
		$newExpiry = $newPreferences[Preferences::ENABLE_IP_AUTO_REVEAL] ?? 0;

		$needToLog = $oldExpiry !== $newExpiry;
		$willEnableAutoReveal = $newExpiry > $timeNow;
		$willDisableAutoReveal = !$willEnableAutoReveal;

		if ( $needToLog ) {
			$logger = $this->loggerFactory->getLogger();
			if ( $willEnableAutoReveal ) {
				$expiry = $newPreferences[Preferences::ENABLE_IP_AUTO_REVEAL];
				$logger->logAutoRevealAccessEnabled( $user, $expiry );
			} else {
				$logger->logAutoRevealAccessDisabled( $user );
			}
		}
	}

}
