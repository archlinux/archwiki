<?php
/**
 * DiscussionTools preference hooks
 *
 * @file
 * @ingroup Extensions
 * @license MIT
 */

namespace MediaWiki\Extension\DiscussionTools\Hooks;

use MediaWiki\Config\Config;
use MediaWiki\Config\ConfigFactory;
use MediaWiki\Html\Html;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\User\User;

class PreferenceHooks implements
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
					// The following messages are used here:
					// * discussiontools-preference-autotopicsub
					// * discussiontools-preference-newtopictool
					// * discussiontools-preference-replytool
					// * discussiontools-preference-sourcemodetoolbar
					// * discussiontools-preference-topicsubscription
					// * discussiontools-preference-visualenhancements
					'label-message' => "discussiontools-preference-$feature",
					// The following messages are used here:
					// * discussiontools-preference-autotopicsub-help
					// * discussiontools-preference-newtopictool-help
					// * discussiontools-preference-replytool-help
					// * discussiontools-preference-sourcemodetoolbar-help
					// * discussiontools-preference-topicsubscription-help
					// * discussiontools-preference-visualenhancements-help
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

		foreach ( HookUtils::FEATURES_DEPENDENCIES as $feature => $dependencies ) {
			if ( !isset( $preferences["discussiontools-$feature"] ) ) {
				continue;
			}
			$fields = array_filter( $dependencies,
				static fn ( $dep ) => isset( $preferences["discussiontools-$dep"] ) &&
					// GlobalPreferences would remove disabled fields, avoid referencing them
					!( $preferences["discussiontools-$dep"]['disabled'] ?? false )
			);
			// Disable the option when it would have no effect: its dependencies
			// are all disabled by the user or by the conflicting Gadget
			if ( count( $fields ) ) {
				$preferences["discussiontools-$feature"]['disable-if'] = [ 'AND' ];
				foreach ( $fields as $field ) {
					$preferences["discussiontools-$feature"]['disable-if'][] = [
						'===', "discussiontools-$field", ''
					];
				}
			} else {
				$preferences["discussiontools-$feature"]['disabled'] = true;
			}
		}

		$preferences['discussiontools-showadvanced'] = [
			'type' => 'api',
		];
		$preferences['discussiontools-seenautotopicsubpopup'] = [
			'type' => 'api',
		];

		if ( !$this->config->get( 'DiscussionToolsBeta' ) ) {
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

}
