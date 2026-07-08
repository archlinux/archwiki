<?php
/**
 * @copyright 2011-2018 VisualEditor Team's Cite sub-team and others; see AUTHORS.txt
 * @license MIT
 */

namespace Cite\Hooks;

use MediaWiki\Config\Config;
use MediaWiki\EditPage\EditPage;
use MediaWiki\Hook\EditPage__showEditForm_initialHook;
use MediaWiki\Output\OutputPage;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\ResourceLoader\Hook\ResourceLoaderGetConfigVarsHook;
use MediaWiki\ResourceLoader\Hook\ResourceLoaderRegisterModulesHook;
use MediaWiki\ResourceLoader\ResourceLoader;
use MediaWiki\Revision\Hook\ContentHandlerDefaultModelForHook;
use MediaWiki\Title\Title;
use MediaWiki\User\Options\UserOptionsLookup;

/**
 * Hook handlers for Cite's integration with editors like VisualEditor and the WikiEditor extension.
 *
 * @license GPL-2.0-or-later
 */
class CiteHooks implements
	ContentHandlerDefaultModelForHook,
	ResourceLoaderGetConfigVarsHook,
	ResourceLoaderRegisterModulesHook,
	EditPage__showEditForm_initialHook
{

	public function __construct(
		private readonly ExtensionRegistry $extensionRegistry,
		private readonly UserOptionsLookup $userOptionsLookup,
	) {
	}

	/**
	 * Convert the content model of a message that is actually JSON to JSON. This
	 * only affects validation and UI when saving and editing, not loading the
	 * content.
	 *
	 * @param Title $title
	 * @param string &$model
	 * @return void
	 */
	public function onContentHandlerDefaultModelFor( $title, &$model ) {
		if (
			$title->inNamespace( NS_MEDIAWIKI ) &&
			$title->getText() == 'Cite-tool-definition.json'
		) {
			$model = CONTENT_MODEL_JSON;
		}
	}

	/**
	 * Adds extra variables to the global config
	 * @param array &$vars `[ variable name => value ]`
	 * @param string $skin
	 * @param Config $config
	 */
	public function onResourceLoaderGetConfigVars( array &$vars, $skin, Config $config ): void {
		$vars['wgCiteVisualEditorOtherGroup'] = (bool)$config->get( 'CiteVisualEditorOtherGroup' );
		$vars['wgCiteResponsiveReferences'] = (bool)$config->get( 'CiteResponsiveReferences' );
		$vars['wgCiteSubReferencing'] = (bool)$config->get( 'CiteSubReferencing' );
		$vars['wgCiteRemoveSyntheticRefsUnsafe'] = (bool)$config->get( 'CiteRemoveSyntheticRefsUnsafe' );
	}

	/**
	 * Hook: EditPage::showEditForm:initial
	 *
	 * Add the module for WikiEditor
	 *
	 * @param EditPage $editPage
	 * @param OutputPage $outputPage
	 * @return void
	 */
	public function onEditPage__showEditForm_initial( $editPage, $outputPage ) {
		if ( !$this->extensionRegistry->isLoaded( 'WikiEditor' ) ) {
			return;
		}

		// Wikitext is always allowed
		if ( $editPage->contentModel !== CONTENT_MODEL_WIKITEXT ) {
			// To support compatible namespaces from extensions like ProofreadPage, see T348403
			$wikitextContentModels = $this->extensionRegistry->getAttribute( 'CiteAllowedContentModels' );
			if ( !in_array( $editPage->contentModel, $wikitextContentModels ) ) {
				return;
			}
		}

		$user = $editPage->getContext()->getUser();
		if ( $this->userOptionsLookup->getBoolOption( $user, 'usebetatoolbar' ) ) {
			$outputPage->addModules( 'ext.cite.wikiEditor' );
		}
	}

	public function onResourceLoaderRegisterModules( ResourceLoader $rl ): void {
		if ( $this->extensionRegistry->isLoaded( 'VisualEditor' ) ) {

			$veConfig = [
				'ext.cite.visualEditor' => [
					'localBasePath' => dirname( __DIR__, 2 ) . '/modules/ve-cite',
					'remoteExtPath' => 'Cite/modules/ve-cite',
					'packageFiles' => [
						'init.js',
						've.dm.MWDataTransitionHelper.js',
						've.dm.MWDocumentReferences.js',
						've.dm.MWGroupReferences.js',
						've.dm.MWReferenceModel.js',
						've.dm.MWReferenceKeyGenerator.js',
						've.dm.MWReferencesListNode.js',
						've.dm.MWReferenceNode.js',
						've.ce.MWReferencesListNode.js',
						've.ce.MWReferenceNode.js',
						[
							'name' => 've.ui.MWCitationTools.json',
							'callback' => 'Cite\\ResourceLoader\\MWCitationToolsDefinition::getTools',
						],
						've.ui.MWReferenceGroupInputWidget.js',
						've.ui.MWReferenceSearchWidget.js',
						've.ui.MWReferenceResultWidget.js',
						've.ui.MWEditReferenceNodeAction.js',
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
						've.ui.MWCitationTools.init.js',
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
						'oojs-ui.styles.icons-location',
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
						'cite-ve-dialog-subreference-change-all-checkbox-label',
						'cite-ve-dialog-reference-convert-all-checkbox-label',
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
			];
			if ( $this->extensionRegistry->isLoaded( 'TestKitchen' ) ) {
				$veConfig[ 'ext.cite.visualEditor' ][ 'dependencies' ][] = 'ext.testKitchen';
			}

			$rl->register( $veConfig );
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
						'ext.cite.styles',
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
	}

}
