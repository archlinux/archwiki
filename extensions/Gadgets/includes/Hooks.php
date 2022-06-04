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

use Action;
use Content;
use EditPage;
use Exception;
use HTMLForm;
use IContextSource;
use InvalidArgumentException;
use LinkBatch;
use MediaWiki\Extension\Gadgets\Content\GadgetDefinitionContent;
use OOUI\HtmlSnippet;
use OutputPage;
use RequestContext;
use ResourceLoader;
use SpecialPage;
use Status;
use Title;
use User;
use WebRequest;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\WrappedString;
use WikiPage;
use Xml;

class Hooks {
	/**
	 * PageSaveComplete hook handler
	 *
	 * Only run in versions of mediawiki begining 1.35; before 1.35, ::onPageContentSaveComplete
	 * and ::onPageContentInsertComplete are used
	 *
	 * @note parameters include classes not available before 1.35, so for those typehints
	 * are not used. The variable name reflects the class
	 *
	 * @param WikiPage $wikiPage
	 * @param mixed $userIdentity unused
	 * @param string $summary
	 * @param int $flags
	 * @param mixed $revisionRecord unused
	 * @param mixed $editResult unused
	 */
	public static function onPageSaveComplete(
		WikiPage $wikiPage,
		$userIdentity,
		string $summary,
		int $flags,
		$revisionRecord,
		$editResult
	) {
		$title = $wikiPage->getTitle();
		$repo = GadgetRepo::singleton();

		if ( ( $flags & EDIT_NEW ) && $title->inNamespace( NS_GADGET_DEFINITION ) ) {
			$repo->handlePageCreation( $title );
		}

		$repo->handlePageUpdate( $title );
	}

	/**
	 * UserGetDefaultOptions hook handler
	 * @param array &$defaultOptions Array of default preference keys and values
	 */
	public static function userGetDefaultOptions( array &$defaultOptions ) {
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
	public static function getPreferences( User $user, array &$preferences ) {
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
	public static function onPreferencesGetLegend( $form, $key, &$legend ) {
		if ( str_starts_with( $key, 'gadget-section-' ) ) {
			$legend = new HtmlSnippet( $form->msg( $key )->parse() );
		}
	}

	/**
	 * ResourceLoaderRegisterModules hook handler.
	 * @param ResourceLoader $resourceLoader
	 */
	public static function registerModules( ResourceLoader $resourceLoader ) {
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
	 */
	public static function beforePageDisplay( OutputPage $out ) {
		$repo = GadgetRepo::singleton();
		$ids = $repo->getGadgetIds();
		if ( !$ids ) {
			return;
		}

		$lb = new LinkBatch();
		$lb->setCaller( __METHOD__ );
		$enabledLegacyGadgets = [];
		$req = $out->getRequest();

		/**
		 * @var $gadget Gadget
		 */
		$user = $out->getUser();
		foreach ( $ids as $id ) {
			try {
				$gadget = $repo->getGadget( $id );
			} catch ( InvalidArgumentException $e ) {
				continue;
			}

			if ( self::shouldLoadGadget( $gadget, $id, $user, $req, $out ) ) {
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
			$strings[] = self::makeLegacyWarning( $id );
		}
		$out->addHTML( WrappedString::join( "\n", $strings ) );
	}

	/**
	 * @param string $id
	 * @return string|WrappedString HTML
	 */
	private static function makeLegacyWarning( $id ) {
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
	 * @throws Exception
	 * @return bool
	 */
	public static function onEditFilterMergedContent( IContextSource $context,
		Content $content,
		Status $status,
		$summary
	) {
		if ( $content instanceof GadgetDefinitionContent ) {
			$validateStatus = $content->validate();
			if ( !$validateStatus->isGood() ) {
				$status->merge( $validateStatus );
				// @todo Remove this line after this extension do not support mediawiki version 1.36 and before
				$status->value = EditPage::AS_HOOK_ERROR_EXPECTED;
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
	public static function onContentHandlerDefaultModelFor( Title $title, &$model ) {
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
	 * @param Gadget $gadget
	 * @param string $id
	 * @param User $user
	 * @param WebRequest $req
	 * @param OutputPage $out
	 * @return bool Load gadget or not
	 */
	private static function shouldLoadGadget( $gadget, $id, $user, $req, $out ): bool {
		$urlLoad = $req->getRawVal( 'withgadget' ) === $id && $gadget->supportsUrlLoad();

		return ( $gadget->isEnabled( $user ) || $urlLoad )
			&& $gadget->isAllowed( $user )
			&& $gadget->isActionSupported( Action::getActionName( $out->getContext() ) )
			&& $gadget->isSkinSupported( $out->getSkin() )
			&& ( in_array( $out->getTarget() ?? 'desktop', $gadget->getTargets() ) );
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
	public static function onwgQueryPages( array &$queryPages ) {
		$queryPages[] = [ 'SpecialGadgetUsage', 'GadgetUsage' ];
	}

	/**
	 * Prevent gadget preferences from being deleted.
	 * @link https://www.mediawiki.org/wiki/Manual:Hooks/DeleteUnknownPreferences
	 * @param string[] &$where Array of where clause conditions to add to.
	 * @param IDatabase $db
	 */
	public static function onDeleteUnknownPreferences( array &$where, IDatabase $db ) {
		$where[] = 'up_property NOT' . $db->buildLike( 'gadget-', $db->anyString() );
	}
}
