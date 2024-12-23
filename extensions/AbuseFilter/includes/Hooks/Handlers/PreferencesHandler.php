<?php

namespace MediaWiki\Extension\AbuseFilter\Hooks\Handlers;

use MediaWiki\Extension\AbuseFilter\AbuseLoggerFactory;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\User\Options\Hook\SaveUserOptionsHook;
use MediaWiki\User\UserIdentity;

class PreferencesHandler implements GetPreferencesHook, SaveUserOptionsHook {
	private PermissionManager $permissionManager;

	private AbuseLoggerFactory $abuseLoggerFactory;

	public function __construct(
		PermissionManager $permissionManager,
		AbuseLoggerFactory $abuseLoggerFactory
	) {
		$this->permissionManager = $permissionManager;
		$this->abuseLoggerFactory = $abuseLoggerFactory;
	}

	/** @inheritDoc */
	public function onGetPreferences( $user, &$preferences ): void {
		if ( !$this->permissionManager->userHasRight( $user, 'abusefilter-access-protected-vars' ) ) {
			return;
		}

		$preferences['abusefilter-protected-vars-view-agreement'] = [
			'type' => 'toggle',
			'label-message' => 'abusefilter-preference-protected-vars-view-agreement',
			'section' => 'personal/abusefilter',
			'noglobal' => true,
		];
	}

	/**
	 * @param UserIdentity $user
	 * @param array &$modifiedOptions
	 * @param array $originalOptions
	 */
	public function onSaveUserOptions( UserIdentity $user, array &$modifiedOptions, array $originalOptions ) {
		$wasEnabled = !empty( $originalOptions['abusefilter-protected-vars-view-agreement'] );
		$wasDisabled = !$wasEnabled;

		$willEnable = !empty( $modifiedOptions['abusefilter-protected-vars-view-agreement'] );
		$willDisable = isset( $modifiedOptions['abusefilter-protected-vars-view-agreement'] ) &&
			!$modifiedOptions['abusefilter-protected-vars-view-agreement'];

		if (
			( $wasEnabled && $willDisable ) ||
			( $wasDisabled && $willEnable )
		) {
			$logger = $this->abuseLoggerFactory->getProtectedVarsAccessLogger();
			if ( $willEnable ) {
				$logger->logAccessEnabled( $user );
			} else {
				$logger->logAccessDisabled( $user );
			}
		}
	}
}
