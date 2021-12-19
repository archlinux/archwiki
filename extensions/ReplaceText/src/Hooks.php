<?php
/**
 * Hook functions for the Replace Text extension.
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
namespace MediaWiki\Extension\ReplaceText;

use ALItem;
use ALRow;
use ALTree;
use MediaWiki\MediaWikiServices;
use MovePageForm;
use Title;

class Hooks {

	/**
	 * Implements AdminLinks hook from Extension:Admin_Links.
	 *
	 * @param ALTree &$adminLinksTree
	 * @return bool
	 */
	public static function addToAdminLinks( ALTree &$adminLinksTree ) {
		$generalSection = $adminLinksTree->getSection( wfMessage( 'adminlinks_general' )->text() );

		if ( !$generalSection ) {
			return true;
		}
		$extensionsRow = $generalSection->getRow( 'extensions' );

		if ( $extensionsRow === null ) {
			$extensionsRow = new ALRow( 'extensions' );
			$generalSection->addRow( $extensionsRow );
		}

		$extensionsRow->addItem( ALItem::newFromSpecialPage( 'ReplaceText' ) );

		return true;
	}

	/**
	 * Implements SpecialMovepageAfterMove hook.
	 *
	 * Adds a link to the Special:ReplaceText page at the end of a successful
	 * regular page move message.
	 *
	 * @param MovePageForm &$form
	 * @param Title &$ot Title object of the old article (moved from)
	 * @param Title &$nt Title object of the new article (moved to)
	 */
	public static function replaceTextReminder( &$form, &$ot, &$nt ) {
		$out = $form->getOutput();
		$services = MediaWikiServices::getInstance();
		$page = $services->getSpecialPageFactory()->getPage( 'ReplaceText' );
		$pageLink = $services->getLinkRenderer()->makeLink( $page->getPageTitle(), null );
		$out->addHTML( $form->msg( 'replacetext_reminder' )
			->rawParams( $pageLink )->inContentLanguage()->parseAsBlock() );
	}

	/**
	 * Implements UserGetReservedNames hook.
	 * @param array &$names
	 */
	public static function getReservedNames( &$names ) {
		global $wgReplaceTextUser;
		if ( $wgReplaceTextUser !== null ) {
			$names[] = $wgReplaceTextUser;
		}
	}
}
