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
 * https://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */
namespace MediaWiki\Extension\ReplaceText;

use ALItem;
use ALRow;
use ALTree;
use Config;
use MediaWiki\Hook\SpecialMovepageAfterMoveHook;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\User\Hook\UserGetReservedNamesHook;
use MovePageForm;
use Title;

class Hooks implements
	SpecialMovepageAfterMoveHook,
	UserGetReservedNamesHook
{

	/** @var Config */
	private $config;

	/** @var SpecialPageFactory */
	private $specialPageFactory;

	/**
	 * @param Config $config
	 * @param SpecialPageFactory $specialPageFactory
	 */
	public function __construct(
		Config $config,
		SpecialPageFactory $specialPageFactory
	) {
		$this->config = $config;
		$this->specialPageFactory = $specialPageFactory;
	}

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
	 * @param MovePageForm $form
	 * @param Title $ot Title object of the old article (moved from)
	 * @param Title $nt Title object of the new article (moved to)
	 */
	public function onSpecialMovepageAfterMove( $form, $ot, $nt ) {
		if ( !$form->getUser()->isAllowed( 'replacetext' ) ) {
			return;
		}
		$out = $form->getOutput();
		$page = $this->specialPageFactory->getPage( 'ReplaceText' );
		$pageLink = $form->getLinkRenderer()->makeLink( $page->getPageTitle() );
		$out->addHTML( $form->msg( 'replacetext_reminder' )
			->rawParams( $pageLink )->inContentLanguage()->parseAsBlock() );
	}

	/**
	 * Implements UserGetReservedNames hook.
	 * @param array &$names
	 */
	public function onUserGetReservedNames( &$names ) {
		$replaceTextUser = $this->config->get( 'ReplaceTextUser' );
		if ( $replaceTextUser !== null ) {
			$names[] = $replaceTextUser;
		}
	}
}
