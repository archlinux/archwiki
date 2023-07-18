<?php
/**
 * DiscussionTools preference hooks
 *
 * @file
 * @ingroup Extensions
 * @license MIT
 */

namespace MediaWiki\Extension\DiscussionTools\Hooks;

use Config;
use ConfigFactory;
use Html;
use MediaWiki\Auth\Hook\LocalUserCreatedHook;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\MediaWikiServices;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use RequestContext;
use SpecialPage;
use User;

class PreferenceHooks implements
	LocalUserCreatedHook,
	GetPreferencesHook
{

	private Config $config;
	private LinkRenderer $linkRenderer;

	public function __construct(
		ConfigFactory $configFactory,
		LinkRenderer $linkRenderer
	) {
		$this->config = $configFactory->makeConfig( 'discussiontools' );
		$this->linkRenderer = $linkRenderer;
	}

	/**
	 * Rename a key in an array while preserving the order of associative array keys.
	 *
	 * @param array $array
	 * @param string $from
	 * @param string $to
	 * @return array Modified array
	 */
	private static function arrayRenameKey( array $array, string $from, string $to ): array {
		$out = [];
		foreach ( $array as $key => $value ) {
			if ( $key === $from ) {
				$key = $to;
			}
			$out[$key] = $value;
		}
		return $out;
	}

	/**
	 * Handler for the GetPreferences hook, to add and hide user preferences as configured
	 *
	 * @param User $user
	 * @param array &$preferences
	 */
	public function onGetPreferences( $user, &$preferences ) {
		if ( HookUtils::isFeatureAvailableToUser( $user ) ) {
			$preferences['discussiontools-summary'] = [
				'type' => 'info',
				'default' => wfMessage( 'discussiontools-preference-summary' )->parse(),
				'raw' => true,
				'section' => 'editing/discussion',
			];
		}
		foreach ( HookUtils::FEATURES as $feature ) {
			if (
				$feature === HookUtils::VISUALENHANCEMENTS_REPLY ||
				$feature === HookUtils::VISUALENHANCEMENTS_PAGEFRAME
			) {
				// Feature is never user-configurable
				continue;
			}
			if ( HookUtils::isFeatureAvailableToUser( $user, $feature ) ) {
				$preferences["discussiontools-$feature"] = [
					'type' => 'toggle',
					'label-message' => "discussiontools-preference-$feature",
					'help-message' => "discussiontools-preference-$feature-help",
					'section' => 'editing/discussion',
				];

				// Option to enable/disable new topic tool on pages that haven't been created
				// (it's inside this loop to place the options in a nice order)
				if ( $feature === HookUtils::NEWTOPICTOOL ) {
					$preferences["discussiontools-newtopictool-createpage"] = [
						'type' => 'radio',
						'cssclass' => 'mw-htmlform-checkradio-indent',
						'label-message' => 'discussiontools-preference-newtopictool-createpage',
						'options-messages' => [
							'discussiontools-preference-newtopictool-createpage-newtopictool' => 1,
							'discussiontools-preference-newtopictool-createpage-editor' => 0,
						],
						'disable-if' => [ '===', 'discussiontools-' . HookUtils::NEWTOPICTOOL, '' ],
						'section' => 'editing/discussion',
					];
				}

				// Make this option unavailable when a conflicting Convenient Discussions gadget exists
				// (we can't use 'disable-if' or 'hide-if', because they don't let us change the labels).
				if ( HookUtils::featureConflictsWithGadget( $user, $feature ) ) {
					$preferences["discussiontools-$feature"]['disabled'] = true;
					$preferences["discussiontools-$feature"]['help-message'] =
						[ 'discussiontools-preference-gadget-conflict', 'Special:Preferences#mw-prefsection-gadgets' ];
				}
			}
		}

		if ( isset( $preferences['discussiontools-' . HookUtils::SOURCEMODETOOLBAR] ) && (
			isset( $preferences['discussiontools-' . HookUtils::REPLYTOOL] ) ||
			isset( $preferences['discussiontools-' . HookUtils::NEWTOPICTOOL] )
		) ) {
			// Disable this option when it would have no effect
			// (both reply tool and new topic tool are disabled)
			$preferences['discussiontools-' . HookUtils::SOURCEMODETOOLBAR]['disable-if'] = [ 'AND' ];

			if ( isset( $preferences['discussiontools-' . HookUtils::REPLYTOOL] ) &&
				// GlobalPreferences extension would delete disabled fields, avoid referring to it.
				!( $preferences['discussiontools-' . HookUtils::REPLYTOOL]['disabled'] ?? false )
			) {
				$preferences['discussiontools-' . HookUtils::SOURCEMODETOOLBAR]['disable-if'][] = [
					'===', 'discussiontools-' . HookUtils::REPLYTOOL, ''
				];
			}
			if ( isset( $preferences['discussiontools-' . HookUtils::NEWTOPICTOOL] ) ) {
				$preferences['discussiontools-' . HookUtils::SOURCEMODETOOLBAR]['disable-if'][] = [
					'===', 'discussiontools-' . HookUtils::NEWTOPICTOOL, ''
				];
			}
		}

		if ( isset( $preferences['discussiontools-' . HookUtils::AUTOTOPICSUB] ) &&
			isset( $preferences['discussiontools-' . HookUtils::TOPICSUBSCRIPTION] )
		) {
			// Disable automatic subscriptions when subscriptions are disabled
			$preferences['discussiontools-' . HookUtils::AUTOTOPICSUB]['disable-if'] = [
				'===', 'discussiontools-' . HookUtils::TOPICSUBSCRIPTION, ''
			];
		}

		$preferences['discussiontools-showadvanced'] = [
			'type' => 'api',
		];
		$preferences['discussiontools-newtopictool-opened'] = [
			'type' => 'api',
		];
		$preferences['discussiontools-newtopictool-hint-shown'] = [
			'type' => 'api',
		];
		$preferences['discussiontools-seenautotopicsubpopup'] = [
			'type' => 'api',
		];

		if (
			!$this->config->get( 'DiscussionToolsEnable' ) ||
			!$this->config->get( 'DiscussionToolsBeta' )
		) {
			// When out of beta, preserve the user preference in case we
			// bring back the beta feature for a new sub-feature. (T272071)
			$preferences['discussiontools-betaenable'] = [
				'type' => 'api'
			];
		}

		$preferences['discussiontools-editmode'] = [
			'type' => 'api',
			'validation-callback' => static function ( $value ) {
				return in_array( $value, [ '', 'source', 'visual' ], true );
			},
		];

		// Add a link to Special:TopicSubscriptions to the Echo preferences matrix
		$categoryMessage = wfMessage( 'echo-category-title-dt-subscription' )->numParams( 1 )->escaped();
		$categoryMessageExtra = $categoryMessage .
			Html::element( 'br' ) .
			wfMessage( 'parentheses' )->rawParams(
				$this->linkRenderer->makeLink(
					SpecialPage::getTitleFor( 'TopicSubscriptions' ),
					wfMessage( 'discussiontools-topicsubscription-preferences-editsubscriptions' )->text()
				)
			)->escaped();
		if ( isset( $preferences['echo-subscriptions']['rows'] ) ) {
			$preferences['echo-subscriptions']['rows'] = static::arrayRenameKey(
				$preferences['echo-subscriptions']['rows'],
				$categoryMessage,
				$categoryMessageExtra
			);
		}
		if ( isset( $preferences['echo-subscriptions']['tooltips'] ) ) {
			$preferences['echo-subscriptions']['tooltips'] = static::arrayRenameKey(
				// Phan insists that this key doesn't exist, even though we just checked with isset()
				// @phan-suppress-next-line PhanTypeInvalidDimOffset, PhanTypeMismatchArgument
				$preferences['echo-subscriptions']['tooltips'],
				$categoryMessage,
				$categoryMessageExtra
			);
		}
	}

	/**
	 * Handler for the GetBetaFeaturePreferences hook, to add and hide user beta preferences as configured
	 *
	 * @param User $user
	 * @param array &$preferences
	 */
	public static function onGetBetaFeaturePreferences( User $user, array &$preferences ): void {
		$coreConfig = RequestContext::getMain()->getConfig();
		$iconpath = $coreConfig->get( 'ExtensionAssetsPath' ) . '/DiscussionTools/images';

		$dtConfig = MediaWikiServices::getInstance()->getConfigFactory()
			->makeConfig( 'discussiontools' );

		if (
			$dtConfig->get( 'DiscussionToolsEnable' ) &&
			$dtConfig->get( 'DiscussionToolsBeta' )
		) {
			$preferences['discussiontools-betaenable'] = [
				'version' => '1.0',
				'label-message' => 'discussiontools-preference-label',
				'desc-message' => 'discussiontools-preference-description',
				'screenshot' => [
					'ltr' => "$iconpath/betafeatures-icon-DiscussionTools-ltr.svg",
					'rtl' => "$iconpath/betafeatures-icon-DiscussionTools-rtl.svg",
				],
				'info-message' => 'discussiontools-preference-info-link',
				'discussion-message' => 'discussiontools-preference-discussion-link',
				'requirements' => [
					'javascript' => true
				]
			];
		}
	}

	/**
	 * Handler for LocalUserCreated hook.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/LocalUserCreated
	 * @param User $user User object for the created user
	 * @param bool $autocreated Whether this was an auto-creation
	 * @return bool|void True or no return value to continue or false to abort
	 */
	public function onLocalUserCreated( $user, $autocreated ) {
		if ( !$autocreated ) {
			$userOptionsManager = MediaWikiServices::getInstance()->getUserOptionsManager();
			// We want new users to be created with email-subscriptions to our notifications enabled
			$userOptionsManager->setOption( $user, 'echo-subscriptions-email-dt-subscription', true );
			// The auto topic subscription feature is disabled by default for existing users, but
			// we enable it for new users (T294398).
			// This can only occur when the feature is available for everyone; when it's in beta,
			// the new user won't have the beta enabled, so it'll never be available here.
			if ( HookUtils::isFeatureAvailableToUser( $user, HookUtils::AUTOTOPICSUB ) ) {
				$userOptionsManager->setOption( $user, 'discussiontools-' . HookUtils::AUTOTOPICSUB, 1 );
			}
		}
	}

}
