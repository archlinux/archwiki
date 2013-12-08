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
		wfProfileIn( __METHOD__ );
		$title = $article->getTitle();
		if ( $title->getNamespace() == NS_MEDIAWIKI && $title->getText() == 'Gadgets-definition' ) {
			Gadget::loadStructuredList( $text );
		}
		wfProfileOut( __METHOD__ );
		return true;
	}

	/**
	 * UserGetDefaultOptions hook handler
	 * @param $defaultOptions Array of default preference keys and values
	 * @return bool
	 */
	public static function userGetDefaultOptions( &$defaultOptions ) {
		$gadgets = Gadget::loadStructuredList();
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
		wfProfileIn( __METHOD__ );
		$gadgets = Gadget::loadStructuredList();
		if ( !$gadgets ) {
			wfProfileOut( __METHOD__ );
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
				if ( $gadget->isAllowed( $user ) ) {
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
		wfProfileOut( __METHOD__ );

		return true;
	}

	/**
	 * ResourceLoaderRegisterModules hook handler.
	 * @param $resourceLoader ResourceLoader
	 * @return bool
	 */
	public static function registerModules( &$resourceLoader ) {
		$gadgets = Gadget::loadList();
		if ( !$gadgets ) {
			return true;
		}

		/**
		 * @var $g Gadget
		 */
		foreach ( $gadgets as $g ) {
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
		wfProfileIn( __METHOD__ );

		$gadgets = Gadget::loadList();
		if ( !$gadgets ) {
			wfProfileOut( __METHOD__ );
			return true;
		}

		$lb = new LinkBatch();
		$lb->setCaller( __METHOD__ );
		$pages = array();

		/**
		 * @var $gadget Gadget
		 */
		$user = $out->getUser();
		foreach ( $gadgets as $gadget ) {
			if ( $gadget->isEnabled( $user ) && $gadget->isAllowed( $user ) ) {
				if ( $gadget->hasModule() ) {
					$out->addModuleStyles( $gadget->getModuleName() );
					$out->addModules( $gadget->getModuleName() );
				}

				foreach ( $gadget->getLegacyScripts() as $page ) {
					$lb->add( NS_MEDIAWIKI, $page );
					$pages[] = $page;
				}
			}
		}


		// Allow other extensions, e.g. MobileFrontend, to disallow legacy gadgets
		if ( wfRunHooks( 'Gadgets::allowLegacy', array( $out->getContext() ) ) ) {
			$lb->execute( __METHOD__ );

			$done = array();

			foreach ( $pages as $page ) {
				if ( isset( $done[$page] ) ) {
					continue;
				}

				$done[$page] = true;
				self::applyScript( $page, $out );
			}
		}
		wfProfileOut( __METHOD__ );

		return true;
	}

	/**
	 * Adds one legacy script to output.
	 *
	 * @param string $page Unprefixed page title
	 * @param OutputPage $out
	 */
	private static function applyScript( $page, $out ) {
		global $wgJsMimeType;

		# bug 22929: disable gadgets on sensitive pages.  Scripts loaded through the
		# ResourceLoader handle this in OutputPage::getModules()
		# TODO: make this extension load everything via RL, then we don't need to worry
		# about any of this.
		if ( $out->getAllowedModules( ResourceLoaderModule::TYPE_SCRIPTS ) < ResourceLoaderModule::ORIGIN_USER_SITEWIDE ) {
			return;
		}

		$t = Title::makeTitleSafe( NS_MEDIAWIKI, $page );
		if ( !$t ) {
			return;
		}

		$u = $t->getLocalURL( 'action=raw&ctype=' . $wgJsMimeType );
		$out->addScriptFile( $u, $t->getLatestRevID() );
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
