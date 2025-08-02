<?php

namespace MediaWiki\Extension\ReplaceText;

use AdminLinksHook;
use ALItem;
use ALRow;
use ALTree;

class AdminLinksHooks implements AdminLinksHook {

	/**
	 * Implements AdminLinks hook from Extension:Admin_Links.
	 */
	public function onAdminLinks( ALTree &$adminLinksTree ) {
		$generalSection = $adminLinksTree->getSection( wfMessage( 'adminlinks_general' )->text() );

		if ( !$generalSection ) {
			return;
		}
		$extensionsRow = $generalSection->getRow( 'extensions' );

		if ( $extensionsRow === null ) {
			$extensionsRow = new ALRow( 'extensions' );
			$generalSection->addRow( $extensionsRow );
		}

		$extensionsRow->addItem( ALItem::newFromSpecialPage( 'ReplaceText' ) );
	}
}
