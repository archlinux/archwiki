<?php

namespace MediaWiki\Extension\Gadgets;

/**
 * Copyright © 2007 Daniel Kinzler
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

use Content;
use Exception;
use HTMLForm;
use IContextSource;
use InvalidArgumentException;
use ManualLogEntry;
use MediaWiki\Extension\Gadgets\Content\GadgetDefinitionContent;
use MediaWiki\Hook\BeforePageDisplayHook;
use MediaWiki\Hook\DeleteUnknownPreferencesHook;
use MediaWiki\Hook\EditFilterMergedContentHook;
use MediaWiki\Hook\PreferencesGetIconHook;
use MediaWiki\Hook\PreferencesGetLegendHook;
use MediaWiki\Page\Hook\PageDeleteCompleteHook;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\Permissions\Authority;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\ResourceLoader\Hook\ResourceLoaderRegisterModulesHook;
use MediaWiki\ResourceLoader\ResourceLoader;
use MediaWiki\Revision\Hook\ContentHandlerDefaultModelForHook;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\SpecialPage\Hook\WgQueryPagesHook;
use MediaWiki\Storage\Hook\PageSaveCompleteHook;
use MediaWiki\User\Hook\UserGetDefaultOptionsHook;
use OOUI\HtmlSnippet;
use OutputPage;
use RequestContext;
use Skin;
use SpecialPage;
use Status;
use Title;
use TitleValue;
use User;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\WrappedString;
use WikiPage;
use Xml;

class Hooks implements
	PageDeleteCompleteHook,
	PageSaveCompleteHook,
	UserGetDefaultOptionsHook,
	GetPreferencesHook,
	PreferencesGetIconHook,
	PreferencesGetLegendHook,
	ResourceLoaderRegisterModulesHook,
	BeforePageDisplayHook,
	EditFilterMergedContentHook,
	ContentHandlerDefaultModelForHook,
	WgQueryPagesHook,
	DeleteUnknownPreferencesHook
{
	/**
	 * Handle MediaWiki\Page\Hook\PageSaveCompleteHook
	 *
	 * @param WikiPage $wikiPage
	 * @param mixed $userIdentity unused
	 * @param string $summary
	 * @param int $flags
	 * @param mixed $revisionRecord unused
	 * @param mixed $editResult unused
	 */
	public function onPageSaveComplete(
		$wikiPage,
		$userIdentity,
		$summary,
		$flags,
		$revisionRecord,
		$editResult
	): void {
		$title = $wikiPage->getTitle();
		$repo = GadgetRepo::singleton();
		$repo->handlePageUpdate( $title );
	}

	/**
	 * Handle MediaWiki\Page\Hook\PageDeleteCompleteHook
	 *
	 * @param ProperPageIdentity $page
	 * @param Authority $deleter
	 * @param string $reason
	 * @param int $pageID
	 * @param RevisionRecord $deletedRev Last revision
	 * @param ManualLogEntry $logEntry
	 * @param int $archivedRevisionCount Number of revisions deleted
	 */
	public function onPageDeleteComplete(
		ProperPageIdentity $page,
		Authority $deleter,
		string $reason,
		int $pageID,
		RevisionRecord $deletedRev,
		ManualLogEntry $logEntry,
		int $archivedRevisionCount
	): void {
		$title = TitleValue::newFromPage( $page );
		$repo = GadgetRepo::singleton();
		$repo->handlePageUpdate( $title );
	}

	/**
	 * UserGetDefaultOptions hook handler
	 * @param array &$defaultOptions Array of default preference keys and values
	 */
	public function onUserGetDefaultOptions( &$defaultOptions ) {
		$gadgets = GadgetRepo::singleton()->getStructuredList();
		if ( !$gadgets ) {
			return;
		}

		/**
		 * @var $gadget Gadget
		 */
		foreach ( $gadgets as $thisSection ) {
			foreach ( $thisSection as $gadgetId => $gadget ) {
				// Hidden gadgets don't need to be added here, T299071
				if ( !$gadget->isHidden() ) {
					$defaultOptions['gadget-' . $gadgetId] = $gadget->isOnByDefault() ? 1 : 0;
				}
			}
		}
	}

	/**
	 * GetPreferences hook handler.
	 * @param User $user
	 * @param array &$preferences Preference descriptions
	 */
	public function onGetPreferences( $user, &$preferences ) {
		$gadgets = GadgetRepo::singleton()->getStructuredList();
		if ( !$gadgets ) {
			return;
		}

		$preferences['gadgets-intro'] = [
			'type' => 'info',
			'default' => wfMessage( 'gadgets-prefstext' )->parseAsBlock(),
			'section' => 'gadgets',
			'raw' => true,
		];

		$skin = RequestContext::getMain()->getSkin();
		foreach ( $gadgets as $section => $thisSection ) {
			$available = [];

			/**
			 * @var $gadget Gadget
			 */
			foreach ( $thisSection as $gadget ) {
				if (
					!$gadget->isHidden()
					&& $gadget->isAllowed( $user )
					&& $gadget->isSkinSupported( $skin )
				) {
					$gname = $gadget->getName();
					$sectionLabelMsg = "gadget-section-$section";

					$preferences["gadget-$gname"] = [
						'type' => 'check',
						'label-message' => $gadget->getDescriptionMessageKey(),
						'section' => $section !== '' ? "gadgets/$sectionLabelMsg" : 'gadgets',
						'default' => $gadget->isEnabled( $user ),
						'noglobal' => true,
					];
				}
			}
		}
	}

	/**
	 * PreferencesGetLegend hook handler.
	 *
	 * Used to override the subsection heading labels for the gadget groups. The default message would
	 * be "prefs-$key", but we've previously used different messages, and they have on-wiki overrides
	 * that would have to be moved if the message keys changed.
	 *
	 * @param HTMLForm $form the HTMLForm object. This is a ContextSource as well
	 * @param string $key the section name
	 * @param string &$legend the legend text. Defaults to wfMessage( "prefs-$key" )->text() but may
	 *   be overridden
	 * @return bool|void True or no return value to continue or false to abort
	 */
	public function onPreferencesGetLegend( $form, $key, &$legend ) {
		if ( str_starts_with( $key, 'gadget-section-' ) ) {
			$legend = new HtmlSnippet( $form->msg( $key )->parse() );
		}
	}

	/**
	 * Add icon for Special:Preferences mobile layout
	 *
	 * @param array &$iconNames Array of icon names for their respective sections.
	 */
	public function onPreferencesGetIcon( &$iconNames ) {
		$iconNames[ 'gadgets' ] = 'puzzle';
	}

	/**
	 * ResourceLoaderRegisterModules hook handler.
	 * @param ResourceLoader $resourceLoader
	 */
	public function onResourceLoaderRegisterModules( ResourceLoader $resourceLoader ): void {
		$repo = GadgetRepo::singleton();
		$ids = $repo->getGadgetIds();

		foreach ( $ids as $id ) {
			$resourceLoader->register( Gadget::getModuleName( $id ), [
				'class' => GadgetResourceLoaderModule::class,
				'id' => $id,
			] );
		}
	}

	/**
	 * BeforePageDisplay hook handler.
	 * @param OutputPage $out
	 * @param Skin $skin
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		$repo = GadgetRepo::singleton();
		$ids = $repo->getGadgetIds();
		if ( !$ids ) {
			return;
		}

		$enabledLegacyGadgets = [];
		$conditions = new GadgetLoadConditions( $out );

		/**
		 * @var $gadget Gadget
		 */
		foreach ( $ids as $id ) {
			try {
				$gadget = $repo->getGadget( $id );
			} catch ( InvalidArgumentException $e ) {
				continue;
			}

			if ( $conditions->check( $gadget ) ) {
				if ( $gadget->hasModule() ) {
					if ( $gadget->getType() === 'styles' ) {
						$out->addModuleStyles( Gadget::getModuleName( $gadget->getName() ) );
					} else {
						$out->addModules( Gadget::getModuleName( $gadget->getName() ) );

						$peers = [];
						foreach ( $gadget->getPeers() as $peerName ) {
							try {
								$peers[] = $repo->getGadget( $peerName );
							} catch ( InvalidArgumentException $e ) {
								// Ignore
								// @todo: Emit warning for invalid peer?
							}
						}
						// Load peer modules
						foreach ( $peers as $peer ) {
							if ( $peer->getType() === 'styles' ) {
								$out->addModuleStyles( Gadget::getModuleName( $peer->getName() ) );
							}
							// Else, if not type=styles: Use dependencies instead.
							// Note: No need for recursion as styles modules don't support
							// either of 'dependencies' and 'peers'.
						}
					}
				}

				if ( $gadget->getLegacyScripts() ) {
					$enabledLegacyGadgets[] = $id;
				}
			}
		}

		$strings = [];
		foreach ( $enabledLegacyGadgets as $id ) {
			$strings[] = $this->makeLegacyWarning( $id );
		}
		$out->addHTML( WrappedString::join( "\n", $strings ) );
	}

	/**
	 * @param string $id
	 * @return string|WrappedString HTML
	 */
	private function makeLegacyWarning( $id ) {
		$special = SpecialPage::getTitleFor( 'Gadgets' );

		return ResourceLoader::makeInlineScript(
			Xml::encodeJsCall( 'mw.log.warn', [
				"Gadget \"$id\" was not loaded. Please migrate it to use ResourceLoader. " .
				'See <' . $special->getCanonicalURL() . '>.'
			] )
		);
	}

	/**
	 * Valid gadget definition page after content is modified
	 *
	 * @param IContextSource $context
	 * @param Content $content
	 * @param Status $status
	 * @param string $summary
	 * @param User $user
	 * @param bool $minoredit
	 * @throws Exception
	 * @return bool
	 */
	public function onEditFilterMergedContent(
		IContextSource $context,
		Content $content,
		Status $status,
		$summary,
		User $user,
		$minoredit
	) {
		if ( $content instanceof GadgetDefinitionContent ) {
			$validateStatus = $content->validate();
			if ( !$validateStatus->isGood() ) {
				$status->merge( $validateStatus );
				return false;
			}
		} else {
			$title = $context->getTitle();
			if ( $title->inNamespace( NS_GADGET_DEFINITION ) ) {
				$status->merge( Status::newFatal( "gadgets-wrong-contentmodel" ) );
				return false;
			}
		}

		return true;
	}

	/**
	 * Mark the Title as having a content model of javascript or css for pages
	 * in the Gadget namespace based on their file extension
	 *
	 * @param Title $title
	 * @param string &$model
	 * @return bool
	 */
	public function onContentHandlerDefaultModelFor( $title, &$model ) {
		if ( $title->inNamespace( NS_GADGET ) ) {
			preg_match( '!\.(css|js|json)$!u', $title->getText(), $ext );
			$ext = $ext[1] ?? '';
			switch ( $ext ) {
				case 'js':
					$model = 'javascript';
					return false;
				case 'css':
					$model = 'css';
					return false;
				case 'json':
					$model = 'json';
					return false;
			}
		}

		return true;
	}

	/**
	 * Set the CodeEditor language for Gadget definition pages. It already
	 * knows the language for Gadget: namespace pages.
	 *
	 * @param Title $title
	 * @param string &$lang
	 * @return bool
	 */
	public static function onCodeEditorGetPageLanguage( Title $title, &$lang ) {
		if ( $title->hasContentModel( 'GadgetDefinition' ) ) {
			$lang = 'json';
			return false;
		}

		return true;
	}

	/**
	 * Add the GadgetUsage special page to the list of QueryPages.
	 * @param array &$queryPages
	 */
	public function onWgQueryPages( &$queryPages ) {
		$queryPages[] = [ 'SpecialGadgetUsage', 'GadgetUsage' ];
	}

	/**
	 * Prevent gadget preferences from being deleted.
	 * @link https://www.mediawiki.org/wiki/Manual:Hooks/DeleteUnknownPreferences
	 * @param string[] &$where Array of where clause conditions to add to.
	 * @param IDatabase $db
	 */
	public function onDeleteUnknownPreferences( &$where, $db ) {
		$where[] = 'up_property NOT' . $db->buildLike( 'gadget-', $db->anyString() );
	}
}
