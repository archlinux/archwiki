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
		if ( $this->extensionRegistry->isLoaded( 'VisualEditor' ) ) {
			$rl->register( [
				'ext.cite.visualEditor' => [
					'localBasePath' => dirname( __DIR__, 2 ) . '/modules/ve-cite',
					'remoteExtPath' => 'Cite/modules/ve-cite',
					'packageFiles' => [
						[
							'name' => 'init.js',
							'main' => true,
						],
						've.dm.MWDocumentReferences.js',
						've.dm.MWGroupReferences.js',
						've.dm.MWReferenceModel.js',
						've.dm.MWReferencesListNode.js',
						've.dm.MWReferenceNode.js',
						've.ce.MWReferencesListNode.js',
						've.ce.MWReferenceNode.js',
						[
							'name' => 've.ui.MWCitationTools.json',
							'callback' => 'Cite\\ResourceLoader\\CitationToolDefinition::getTools',
						],
						've.ui.MWReferenceGroupInputWidget.js',
						've.ui.MWReferenceSearchWidget.js',
						've.ui.MWReferenceResultWidget.js',
						've.ui.MWUseExistingReferenceCommand.js',
						've.ui.MWCitationDialog.js',
						've.ui.MWReferencesListCommand.js',
						've.ui.MWReferencesListDialog.js',
						've.ui.MWReferenceDialog.js',
						've.ui.MWReferenceDialogTool.js',
						've.ui.MWSubReferenceHelpDialog.js',
						've.ui.MWSubReferenceHelpDialogOptions.js',
						've.ui.MWUseExistingReferenceDialogTool.js',
						've.ui.MWReferencesListDialogTool.js',
						've.ui.MWReferenceEditPanel.js',
						've.ui.MWCitationDialogTool.js',
						've.ui.MWReferenceContextItem.js',
						've.ui.MWReferencesListContextItem.js',
						've.ui.MWCitationContextItem.js',
						've.ui.MWCitationAction.js',
						've.ui.MWReference.init.js',
						've.ui.MWCitationNeededContextItem.js',
						[
							'name' => 've.ui.contentLanguage.json',
							'callback' => 'Cite\\ResourceLoader\\ContentLanguage::getJsData'
						],
						[
							'name' => 'icons.json',
							'callback' => 'MediaWiki\\ResourceLoader\\CodexModule::getIcons',
							'callbackParam' => [
								'cdxIconNewLine'
							]
						],
					],
					'styles' => [
						've.ce.MWReferenceNode.less',
						've.ce.MWReferencesListNode.less',
						've.ui.MWReferenceDialog.less',
						've.ui.MWReferenceContextItem.less',
						've.ui.MWReferenceResultWidget.less',
						've.ui.MWCitationDialogTool.less',
					],
					'dependencies' => [
						'oojs-ui.styles.icons-alerts',
						'oojs-ui.styles.icons-editing-citation',
						'oojs-ui.styles.icons-interactions',
						'ext.visualEditor.mwcore',
						'ext.cite.parsoid.styles',
						'ext.cite.styles',
						'ext.visualEditor.mwtransclusion',
						'ext.visualEditor.base',
						'ext.visualEditor.mediawiki',
						'mediawiki.jqueryMsg',
					],
					'messages' => [
						'cite-ve-changedesc-ref-group-both',
						'cite-ve-changedesc-ref-group-from',
						'cite-ve-changedesc-ref-group-to',
						'cite-ve-changedesc-reflist-group-both',
						'cite-ve-changedesc-reflist-group-from',
						'cite-ve-changedesc-reflist-group-to',
						'cite-ve-changedesc-reflist-responsive-set',
						'cite-ve-changedesc-reflist-responsive-unset',
						'cite-ve-citationneeded-button',
						'cite-ve-citationneeded-description',
						'cite-ve-citationneeded-reason',
						'cite-ve-citationneeded-title',
						'cite-ve-dialog-reference-contextitem-extends',
						'cite-ve-dialog-reference-editing-add-details',
						'cite-ve-dialog-reference-editing-edit-details',
						'cite-ve-dialog-reference-editing-add-details-placeholder',
						'cite-ve-dialog-reference-editing-reused-short',
						'cite-ve-dialog-reference-editing-reused',
						'cite-ve-dialog-reference-editing-reused-long',
						'cite-ve-dialog-reference-editing-details-placeholder',
						'cite-ve-dialog-reference-missing-parent-ref',
						'cite-ve-dialog-reference-options-group-label',
						'cite-ve-dialog-reference-options-group-placeholder',
						'cite-ve-dialog-reference-options-responsive-label',
						'cite-ve-dialog-reference-options-section',
						'cite-ve-dialog-reference-placeholder',
						'cite-ve-dialog-reference-title',
						'cite-ve-dialog-reference-add-details-button',
						'cite-ve-dialog-reference-title-details',
						'cite-ve-dialog-subreference-help-dialog-title',
						'cite-ve-dialog-subreference-help-dialog-head',
						'cite-ve-dialog-subreference-help-dialog-content',
						'cite-ve-dialog-subreference-help-dialog-link',
						'cite-ve-dialog-subreference-help-dialog-link-ve',
						'cite-ve-dialog-subreference-help-dialog-link-label',
						'cite-ve-dialog-reference-useexisting-tool',
						'cite-ve-dialog-referenceslist-contextitem-description-general',
						'cite-ve-dialog-referenceslist-contextitem-description-named',
						'cite-ve-dialog-referenceslist-title',
						'cite-ve-dialogbutton-citation-educationpopup-title',
						'cite-ve-dialogbutton-citation-educationpopup-text',
						'cite-ve-dialogbutton-reference-full-label',
						'cite-ve-dialogbutton-reference-tooltip',
						'cite-ve-dialogbutton-reference-title',
						'cite-ve-dialogbutton-referenceslist-tooltip',
						'cite-ve-reference-input-placeholder',
						'cite-ve-referenceslist-isempty',
						'cite-ve-referenceslist-isempty-default',
						'cite-ve-referenceslist-missing-parent',
						'cite-ve-referenceslist-missingref',
						'cite-ve-referenceslist-missingref-in-list',
						'cite-ve-referenceslist-missingreflist',
						'cite-ve-toolbar-group-label',
						'cite-ve-othergroup-item',
						'parentheses',
						'word-separator',
					],
				],
			] );
		}

		if ( $this->extensionRegistry->isLoaded( 'WikiEditor' ) ) {
			$rl->register( [
				'ext.cite.wikiEditor' => [
					'localBasePath' => dirname( __DIR__, 2 ) . '/modules',
					'remoteExtPath' => 'Cite/modules',
					'scripts' => [
						'ext.cite.wikiEditor.js',
					],
					'dependencies' => [
						'ext.wikiEditor',
						'mediawiki.jqueryMsg',
						'mediawiki.language',
					],
					'messages' => [
						'cite-wikieditor-tool-reference',
						'cite-wikieditor-help-page-references',
						'cite-wikieditor-help-content-reference-example-text1',
						'cite-wikieditor-help-content-reference-example-text2',
						'cite-wikieditor-help-content-reference-example-text3',
						'cite-wikieditor-help-content-reference-example-ref-id',
						'cite-wikieditor-help-content-reference-example-extra-details',
						'cite-wikieditor-help-content-reference-example-ref-normal',
						'cite-wikieditor-help-content-reference-example-ref-named',
						'cite-wikieditor-help-content-reference-example-ref-reuse',
						'cite-wikieditor-help-content-reference-example-ref-details',
						'cite-wikieditor-help-content-reference-example-ref-result',
						'cite-wikieditor-help-content-reference-example-reflist',
						'cite-wikieditor-help-content-reference-description',
						'cite-wikieditor-help-content-named-reference-description',
						'cite-wikieditor-help-content-rereference-description',
						'cite-wikieditor-help-content-sub-reference-description',
						'cite-wikieditor-help-content-showreferences-description',
						'cite_reference_backlink_symbol',
					],
				],
			] );
		}

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
					'cite-reference-previews-web',
					'cite-reference-previews-collapsible-placeholder',
				],
				'packageFiles' => [
					'index.js',
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
			'section' => $this->extensionRegistry->isLoaded( 'Popups' ) ?
				'rendering/reading' : 'rendering/advancedrendering',
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
