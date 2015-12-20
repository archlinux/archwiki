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
	 * ArticleSaveComplete hook handler.
	 *
	 * @param $article Article
	 * @param $user User
	 * @param $text String: New page text
	 * @return bool
	 */
	public static function articleSaveComplete( $article, $user, $text ) {
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
		if ( !$ids ) {
			return true;
		}

		foreach ( $ids as $id ) {
			$g = $repo->getGadget( $id );
			$module = $g->getModule();
			if ( $module ) {
				$resourceLoader->register( $g->getModuleName(), $module );
			}
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

		/**
		 * @var $gadget Gadget
		 */
		$user = $out->getUser();
		foreach ( $ids as $id ) {
			$gadget = $repo->getGadget( $id );
			if ( $gadget->isEnabled( $user ) && $gadget->isAllowed( $user ) ) {
				if ( $gadget->hasModule() ) {
					$out->addModuleStyles( $gadget->getModuleName() );
					$out->addModules( $gadget->getModuleName() );
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
		$out->addHTML( WrappedString::join( "\n", $strings ) );

		return true;
	}

	private static function makeLegacyWarning( $id ) {
		$special = SpecialPage::getTitleFor( 'Gadgets' );

		return ResourceLoader::makeInlineScript(
			Xml::encodeJsCall( 'mw.log.warn', array(
				"Gadget \"$id\" was not loaded. Please migrate it to use ResourceLoader. " .
				' See <' . $special->getCanonicalURL() . '>.'
			) )
		);
	}

	/**
	 * UnitTestsList hook handler
	 * @param array $files
	 * @return bool
	 */
	public static function onUnitTestsList( array &$files ) {
		$testDir = __DIR__ . '/tests/';
		$files = array_merge( $files, glob( "$testDir/*Test.php" ) );
		return true;
	}
}
