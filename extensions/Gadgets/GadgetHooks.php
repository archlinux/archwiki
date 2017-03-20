<?php

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
use WrappedString\WrappedString;

class GadgetHooks {
	/**
	 * PageContentSaveComplete hook handler.
	 *
	 * @param $article Article
	 * @param $user User
	 * @param $content Content New page content
	 * @return bool
	 */
	public static function onPageContentSaveComplete( $article, $user, $content ) {
		// update cache if MediaWiki:Gadgets-definition was edited
		$title = $article->getTitle();
		$repo = GadgetRepo::singleton();
		if ( $title->getNamespace() == NS_MEDIAWIKI && $title->getText() == 'Gadgets-definition'
			&& $repo instanceof MediaWikiGadgetsDefinitionRepo
		) {
			$repo->purgeDefinitionCache();
		}
		return true;
	}

	/**
	 * UserGetDefaultOptions hook handler
	 * @param $defaultOptions Array of default preference keys and values
	 * @return bool
	 */
	public static function userGetDefaultOptions( &$defaultOptions ) {
		$gadgets = GadgetRepo::singleton()->getStructuredList();
		if ( !$gadgets ) {
			return true;
		}

		/**
		 * @var $gadget Gadget
		 */
		foreach ( $gadgets as $thisSection ) {
			foreach ( $thisSection as $gadgetId => $gadget ) {
				if ( $gadget->isOnByDefault() ) {
					$defaultOptions['gadget-' . $gadgetId] = 1;
				}
			}
		}

		return true;
	}

	/**
	 * GetPreferences hook handler.
	 * @param $user User
	 * @param $preferences Array: Preference descriptions
	 * @return bool
	 */
	public static function getPreferences( $user, &$preferences ) {
		$gadgets = GadgetRepo::singleton()->getStructuredList();
		if ( !$gadgets ) {
			return true;
		}

		$options = array();
		$default = array();
		foreach ( $gadgets as $section => $thisSection ) {
			$available = array();

			/**
			 * @var $gadget Gadget
			 */
			foreach ( $thisSection as $gadget ) {
				if ( !$gadget->isHidden() && $gadget->isAllowed( $user ) ) {
					$gname = $gadget->getName();
					# bug 30182: dir="auto" because it's often not translated
					$desc = '<span dir="auto">' . $gadget->getDescription() . '</span>';
					$available[$desc] = $gname;
					if ( $gadget->isEnabled( $user ) ) {
						$default[] = $gname;
					}
				}
			}

			if ( $section !== '' ) {
				$section = wfMessage( "gadget-section-$section" )->parse();

				if ( count ( $available ) ) {
					$options[$section] = $available;
				}
			} else {
				$options = array_merge( $options, $available );
			}
		}

		$preferences['gadgets-intro'] =
			array(
				'type' => 'info',
				'label' => '&#160;',
				'default' => Xml::tags( 'tr', array(),
					Xml::tags( 'td', array( 'colspan' => 2 ),
						wfMessage( 'gadgets-prefstext' )->parseAsBlock() ) ),
				'section' => 'gadgets',
				'raw' => 1,
				'rawrow' => 1,
			);

		$preferences['gadgets'] =
			array(
				'type' => 'multiselect',
				'options' => $options,
				'section' => 'gadgets',
				'label' => '&#160;',
				'prefix' => 'gadget-',
				'default' => $default,
			);

		return true;
	}

	/**
	 * ResourceLoaderRegisterModules hook handler.
	 * @param $resourceLoader ResourceLoader
	 * @return bool
	 */
	public static function registerModules( &$resourceLoader ) {
		$repo = GadgetRepo::singleton();
		$ids = $repo->getGadgetIds();

		foreach ( $ids as $id ) {
			$resourceLoader->register( Gadget::getModuleName( $id ), array(
				'class' => 'GadgetResourceLoaderModule',
				'id' => $id,
			) );
		}

		return true;
	}

	/**
	 * BeforePageDisplay hook handler.
	 * @param $out OutputPage
	 * @return bool
	 */
	public static function beforePageDisplay( $out ) {
		$repo = GadgetRepo::singleton();
		$ids = $repo->getGadgetIds();
		if ( !$ids ) {
			return true;
		}

		$lb = new LinkBatch();
		$lb->setCaller( __METHOD__ );
		$enabledLegacyGadgets = array();
		$typelessMixedGadgets = array();

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
			if ( $gadget->isEnabled( $user ) && $gadget->isAllowed( $user ) ) {
				if ( $gadget->hasModule() ) {
					if ( $gadget->getType() === 'styles' ) {
						$out->addModuleStyles( Gadget::getModuleName( $gadget->getName() ) );
					} elseif ( $gadget->getType() === 'general' ) {
						$out->addModules( Gadget::getModuleName( $gadget->getName() ) );
					} else {
						$out->addModuleStyles( Gadget::getModuleName( $gadget->getName() ) );
						$out->addModules( Gadget::getModuleName( $gadget->getName() ) );
						$typelessMixedGadgets[] = $id;
					}
				}

				if ( $gadget->getLegacyScripts() ) {
					$enabledLegacyGadgets[] = $id;
				}
			}
		}

		$strings = array();
		foreach ( $enabledLegacyGadgets as $id ) {
			$strings[] = self::makeLegacyWarning( $id );
		}
		foreach ( $typelessMixedGadgets as $id ) {
			$strings[] = self::makeTypelessWarning( $id );
		}
		$out->addHTML( WrappedString::join( "\n", $strings ) );

		return true;
	}

	private static function makeLegacyWarning( $id ) {
		$special = SpecialPage::getTitleFor( 'Gadgets' );

		return ResourceLoader::makeInlineScript(
			Xml::encodeJsCall( 'mw.log.warn', array(
				"Gadget \"$id\" was not loaded. Please migrate it to use ResourceLoader. " .
				'See <' . $special->getCanonicalURL() . '>.'
			) )
		);
	}

	private static function makeTypelessWarning( $id ) {
		return ResourceLoader::makeInlineScript( Xml::encodeJsCall( 'mw.log.warn', array(
			"Gadget \"$id\" styles loaded twice. Migrate to type=general. " .
			'See <https://phabricator.wikimedia.org/T42284>.'
		) ) );
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
	public static function onEditFilterMergedContent( $context, $content, $status, $summary ) {
		$title = $context->getTitle();

		if ( !$title->inNamespace( NS_GADGET_DEFINITION ) ) {
			return true;
		}

		if ( !$content instanceof GadgetDefinitionContent ) {
			// This should not be possible?
			throw new Exception( "Tried to save non-GadgetDefinitionContent to {$title->getPrefixedText()}" );
		}

		$status = $content->validate();
		if ( !$status->isGood() ) {
			$status->merge( $status );
			return false;
		}

		return true;
	}

	/**
	 * After a new page is created in the Gadget definition namespace,
	 * invalidate the list of gadget ids
	 *
	 * @param WikiPage $page
	 */
	public static function onPageContentInsertComplete( WikiPage $page ) {
		if ( $page->getTitle()->inNamespace( NS_GADGET_DEFINITION ) ) {
			$repo = GadgetRepo::singleton();
			if ( $repo instanceof GadgetDefinitionNamespaceRepo ) {
				$repo->purgeGadgetIdsList();
			}
		}
	}

	/**
	 * Mark the Title as having a content model of javascript or css for pages
	 * in the Gadget namespace based on their file extension
	 *
	 * @param Title $title
	 * @param string $model
	 * @return bool
	 */
	public static function onContentHandlerDefaultModelFor( Title $title, &$model ) {
		if ( $title->inNamespace( NS_GADGET ) ) {
			preg_match( '!\.(css|js)$!u', $title->getText(), $ext );
			$ext = isset( $ext[1] ) ? $ext[1] : '';
			switch ( $ext ) {
				case 'js':
					$model = 'javascript';
					return false;
				case 'css':
					$model = 'css';
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
	 * @param string $lang
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
	 * @return bool
	 */
	public static function onwgQueryPages( &$queryPages ) {
		$queryPages[] = array( 'SpecialGadgetUsage', 'GadgetUsage' );
		return true;
	}
}
