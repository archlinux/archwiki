<?php

namespace MediaWiki\CheckUser\HookHandler;

use MediaWiki\CheckUser\Logging\TemporaryAccountLoggerFactory;
use MediaWiki\Config\Config;
use MediaWiki\Context\RequestContext;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\User\Hook\UserGetDefaultOptionsHook;
use MediaWiki\User\UserIdentity;

class Preferences implements GetPreferencesHook, UserGetDefaultOptionsHook {

	/** @var string */
	public const INVESTIGATE_TOUR_SEEN = 'checkuser-investigate-tour-seen';

	/** @var string */
	public const INVESTIGATE_FORM_TOUR_SEEN = 'checkuser-investigate-form-tour-seen';

	/**
	 * @var string The name for the hidden preference used to note if a user has seen the
	 *   Temporary Accounts onboarding dialog.
	 */
	public const TEMPORARY_ACCOUNTS_ONBOARDING_DIALOG_SEEN = 'checkuser-temporary-accounts-onboarding-dialog-seen';

	/** @var string Preference value used to indicate that the CheckUser helper table should collapse on load */
	public const CHECKUSER_HELPER_ALWAYS_COLLAPSE_BY_DEFAULT = 'always';

	/** @var string Preference value used to indicate that the CheckUser helper table should never collapse on load */
	public const CHECKUSER_HELPER_NEVER_COLLAPSE_BY_DEFAULT = 'never';

	/**
	 * @var string Preference value used to indicate that the site configuration value should be used to determine
	 *   if to collapse the CheckUser helper table on load
	 */
	public const CHECKUSER_HELPER_USE_CONFIG_TO_COLLAPSE_BY_DEFAULT = 'config';

	/** @var string User option indicating that IP auto-reveal mode is enabled. */
	public const ENABLE_IP_AUTO_REVEAL = 'checkuser-temporary-account-enable-auto-reveal';

	/** @var string User option indicating that a user opted-in to reveal IPs. */
	public const ENABLE_IP_REVEAL = 'checkuser-temporary-account-enable';

	public const ENABLE_USER_INFO_CARD = 'checkuser-userinfocard-enable';

	private PermissionManager $permissionManager;
	private TemporaryAccountLoggerFactory $loggerFactory;
	private Config $config;

	public function __construct(
		PermissionManager $permissionManager,
		TemporaryAccountLoggerFactory $loggerFactory,
		Config $config
	) {
		$this->permissionManager = $permissionManager;
		$this->loggerFactory = $loggerFactory;
		$this->config = $config;
	}

	/**
	 * @inheritDoc
	 */
	public function onGetPreferences( $user, &$preferences ) {
		$preferences[self::INVESTIGATE_TOUR_SEEN] = [
			'type' => 'api',
		];

		$preferences[self::INVESTIGATE_FORM_TOUR_SEEN] = [
			'type' => 'api',
		];

		$preferences[self::TEMPORARY_ACCOUNTS_ONBOARDING_DIALOG_SEEN] = [
			'type' => 'api',
		];

		$preferences[self::ENABLE_IP_AUTO_REVEAL] = [
			'type' => 'api',
		];

		$messageLocalizer = RequestContext::getMain();

		$preferences[self::ENABLE_USER_INFO_CARD] = [
			'type' => 'toggle',
			'section' => 'rendering/advancedrendering',
			'label-message' => 'checkuser-userinfocard-enable-preference-description',
			'help-message' => 'checkuser-userinfocard-enable-preference-help',
			'canglobal' => true,
		];

		if (
			$this->permissionManager->userHasRight( $user, 'checkuser-temporary-account' ) &&
			!$this->permissionManager->userHasRight( $user, 'checkuser-temporary-account-no-preference' )
		) {
			$preferences['checkuser-temporary-account-enable-description'] = [
				'type' => 'info',
				'default' => $messageLocalizer->msg( 'checkuser-tempaccount-enable-preference-description' )
					->parse(),
				// The following message is generated here:
				// * prefs-checkuser-tempaccount
				'section' => 'personal/checkuser-tempaccount',
				'raw' => true,
				// Forces the info text to be shown on Special:GlobalPreferences, as 'info' preference types are
				// excluded by default. This needs to be shown as it contains important information about
				// what checking the checkbox below this text means.
				'canglobal' => true,
			];
			$preferences[self::ENABLE_IP_REVEAL] = [
				'type' => 'toggle',
				'label-message' => 'checkuser-tempaccount-enable-preference',
				'section' => 'personal/checkuser-tempaccount',
			];
		}

		if ( $this->permissionManager->userHasRight( $user, 'checkuser' ) ) {
			$collapseByDefaultSiteConfig = $this->config->get( 'CheckUserCollapseCheckUserHelperByDefault' );

			$defaultPreferenceMessage = $messageLocalizer
				->msg( 'checkuser-helper-table-collapse-by-default-preference-default' );
			$alwaysPreferenceMessage = $messageLocalizer
				->msg( 'checkuser-helper-table-collapse-by-default-preference-always' )->escaped();
			$neverPreferenceMessage = $messageLocalizer
				->msg( 'checkuser-helper-table-collapse-by-default-preference-never' )->escaped();
			if ( is_int( $collapseByDefaultSiteConfig ) ) {
				$defaultPreferenceMessage->numParams( $collapseByDefaultSiteConfig );
			} elseif ( $collapseByDefaultSiteConfig ) {
				$defaultPreferenceMessage->rawParams( $alwaysPreferenceMessage );
			} else {
				$defaultPreferenceMessage->rawParams( $neverPreferenceMessage );
			}
			$defaultPreferenceMessage = $defaultPreferenceMessage->escaped();

			$preferences['checkuser-helper-table-collapse-by-default'] = [
				'type' => 'limitselect',
				'label-message' => 'checkuser-helper-table-collapse-by-default-preference',
				'options' => [
					$defaultPreferenceMessage => self::CHECKUSER_HELPER_USE_CONFIG_TO_COLLAPSE_BY_DEFAULT,
					$alwaysPreferenceMessage => self::CHECKUSER_HELPER_ALWAYS_COLLAPSE_BY_DEFAULT,
					$neverPreferenceMessage => self::CHECKUSER_HELPER_NEVER_COLLAPSE_BY_DEFAULT,
				],
				// The following message is generated here:
				// * prefs-checkuser
				'section' => 'rc/checkuser',
			];

			// Generate options for the list which are the same as the options for the number of results per page in
			// Special:CheckUser (see AbstractCheckUserPager::__construct).
			$maximumRowCount = $this->config->get( 'CheckUserMaximumRowCount' );
			$rowCountOptions = [
				$maximumRowCount / 25,
				$maximumRowCount / 10,
				$maximumRowCount / 5,
				$maximumRowCount / 2,
				$maximumRowCount,
			];
			$rowCountOptions = array_map( 'ceil', $rowCountOptions );
			$rowCountOptions = array_unique( $rowCountOptions );
			$rowCountOptions = array_map( 'intval', $rowCountOptions );

			$language = RequestContext::getMain()->getLanguage();
			foreach ( $rowCountOptions as $option ) {
				$preferences['checkuser-helper-table-collapse-by-default']
					['options'][$language->formatNum( $option )] = $option;
			}
		}
	}

	/**
	 * @param UserIdentity $user
	 * @param array &$modifiedOptions
	 * @param array $originalOptions
	 */
	public function onSaveUserOptions( UserIdentity $user, array &$modifiedOptions, array $originalOptions ) {
		$wasEnabled = !empty( $originalOptions[self::ENABLE_IP_REVEAL] );
		$wasDisabled = !$wasEnabled;

		// SpecialPreferences::submitReset() sets option values back to its defaults,
		// which may be NULL instead of false. When that happens, directly using isset()
		// against the array element returns false (the element is there, but it's NULL),
		// therefore we need to explicitly check if key exists instead (T382010).
		$isPresent = array_key_exists( self::ENABLE_IP_REVEAL, $modifiedOptions );
		$willEnable = !empty( $modifiedOptions[self::ENABLE_IP_REVEAL] );
		$willDisable = $isPresent && !$modifiedOptions[self::ENABLE_IP_REVEAL];

		if (
			( $wasDisabled && $willEnable ) ||
			( $wasEnabled && $willDisable )
		) {
			$logger = $this->loggerFactory->getLogger();
			if ( $willEnable ) {
				$logger->logAccessEnabled( $user );
			} else {
				$logger->logAccessDisabled( $user );
			}
		}
	}

	/** @inheritDoc */
	public function onUserGetDefaultOptions( &$defaultOptions ) {
		$defaultOptions += [
			self::ENABLE_USER_INFO_CARD => false
		];
	}
}
