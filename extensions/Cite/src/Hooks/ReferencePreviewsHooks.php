<?php

namespace Cite\Hooks;

use Cite\ReferencePreviews\ReferencePreviewsContext;
use Cite\ReferencePreviews\ReferencePreviewsGadgetsIntegration;
use MediaWiki\Output\Hook\MakeGlobalVariablesScriptHook;
use MediaWiki\Output\OutputPage;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\ResourceLoader\Hook\ResourceLoaderRegisterModulesHook;
use MediaWiki\ResourceLoader\ResourceLoader;
use MediaWiki\User\Hook\UserGetDefaultOptionsHook;
use MediaWiki\User\User;

/**
 * Hook handlers for the ReferencePreviews feature, a "PluginModules" for the Popups extension.
 *
 * @license GPL-2.0-or-later
 */
class ReferencePreviewsHooks implements
	GetPreferencesHook,
	MakeGlobalVariablesScriptHook,
	ResourceLoaderRegisterModulesHook,
	UserGetDefaultOptionsHook
{

	public function __construct(
		private readonly ExtensionRegistry $extensionRegistry,
		private readonly ReferencePreviewsContext $referencePreviewsContext,
		private readonly ReferencePreviewsGadgetsIntegration $gadgetsIntegration,
	) {
	}

	/**
	 * @param array &$vars
	 * @param OutputPage $out
	 */
	public function onMakeGlobalVariablesScript( &$vars, $out ): void {
		// The reference previews feature is a "PluginModules" and cannot work without Popups
		if ( $this->extensionRegistry->isLoaded( 'Popups' ) &&
			$this->referencePreviewsContext->isReferencePreviewsEnabled(
				$out->getUser(),
				$out->getSkin()
			)
		) {
			// No need to expose this when it's false, the default null does the same job
			$vars['wgCiteReferencePreviewsActive'] = true;
		}
	}

	/**
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ResourceLoaderRegisterModules
	 */
	public function onResourceLoaderRegisterModules( ResourceLoader $rl ): void {
		if ( !$rl->getConfig()->get( 'CiteReferencePreviews' ) ||
			!$this->extensionRegistry->isLoaded( 'Popups' )
		) {
			return;
		}

		$rl->register( [
			'ext.cite.referencePreviews' => [
				'localBasePath' => dirname( __DIR__, 2 ) . '/modules/ext.cite.referencePreviews',
				'remoteExtPath' => 'Cite/modules/ext.cite.referencePreviews',
				'dependencies' => [
					'ext.popups.main',
				],
				'styles' => [
					'referencePreview.less',
				],
				'messages' => [
					'cite-reference-previews-reference',
					'cite-reference-previews-book',
					'cite-reference-previews-journal',
					'cite-reference-previews-news',
					'cite-reference-previews-note',
					'cite-reference-previews-map',
					'cite-reference-previews-web',
					'cite-reference-previews-collapsible-placeholder',
				],
				'packageFiles' => [
					'init.js',
					'createReferenceGateway.js',
					'createReferencePreview.js'
				]
			]
		] );
	}

	/**
	 * Add options to user Preferences page
	 *
	 * @param User $user User whose preferences are being modified
	 * @param array[] &$preferences Preferences description array, to be fed to a HTMLForm object
	 * @return void
	 */
	public function onGetPreferences( $user, &$preferences ) {
		// The reference previews feature is a "PluginModules" and cannot work without Popups
		if ( !$this->extensionRegistry->isLoaded( 'Popups' ) ) {
			return;
		}

		$option = [
			'type' => 'toggle',
			'label-message' => 'cite-reference-previews-preference-label',
			// FIXME: This message is unnecessary and unactionable since we already
			// detect specific gadget conflicts.
			'help-message' => 'popups-prefs-conflicting-gadgets-info',
			'section' => 'rendering/reading',
		];
		$isNavPopupsGadgetEnabled = $this->gadgetsIntegration->isNavPopupsGadgetEnabled( $user );
		$isRefTooltipsGadgetEnabled = $this->gadgetsIntegration->isRefTooltipsGadgetEnabled( $user );
		if ( $isNavPopupsGadgetEnabled && $isRefTooltipsGadgetEnabled ) {
			$option[ 'disabled' ] = true;
			$option[ 'help-message' ] = [ 'cite-reference-previews-gadget-conflict-info-navpopups-reftooltips',
				'Special:Preferences#mw-prefsection-gadgets' ];
		} elseif ( $isNavPopupsGadgetEnabled ) {
			$option[ 'disabled' ] = true;
			$option[ 'help-message' ] = [ 'cite-reference-previews-gadget-conflict-info-navpopups',
				'Special:Preferences#mw-prefsection-gadgets' ];
		} elseif ( $isRefTooltipsGadgetEnabled ) {
			$option[ 'disabled' ] = true;
			$option[ 'help-message' ] = [ 'cite-reference-previews-gadget-conflict-info-reftooltips',
				'Special:Preferences#mw-prefsection-gadgets' ];
		}

		$preferences += [
			ReferencePreviewsContext::REFERENCE_PREVIEWS_PREFERENCE_NAME => $option
		];
	}

	/**
	 * See https://www.mediawiki.org/wiki/Manual:Hooks/UserGetDefaultOptions
	 * @param array &$defaultOptions Array of preference keys and their default values.
	 * @return void
	 */
	public function onUserGetDefaultOptions( &$defaultOptions ) {
		// FIXME: Move to extension.json once migration is complete.  See T363162
		if ( !isset( $defaultOptions[ ReferencePreviewsContext::REFERENCE_PREVIEWS_PREFERENCE_NAME ] ) ) {
			$defaultOptions[ ReferencePreviewsContext::REFERENCE_PREVIEWS_PREFERENCE_NAME ] = '1';
		}
	}

}
