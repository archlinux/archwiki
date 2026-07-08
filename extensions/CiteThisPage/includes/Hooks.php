<?php

namespace MediaWiki\Extension\CiteThisPage;

use MediaWiki\Config\Config;
use MediaWiki\Skin\Hook\SidebarBeforeOutputHook;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;

class Hooks implements SidebarBeforeOutputHook {
	/**
	 * Checks, if the "cite this page" link should be added. By default the link is added to all
	 * pages in the main namespace, and additionally to pages, which are in one of the namespaces
	 * named in $wgCiteThisPageAdditionalNamespaces.
	 */
	private static function shouldAddLink( ?Title $title, Config $config ): bool {
		if ( !$title ) {
			return false;
		}

		$additionalNamespaces = $config->get( 'CiteThisPageAdditionalNamespaces' );

		return $title->isContentPage() ||
			( $additionalNamespaces[ $title->getNamespace() ] ?? false );
	}

	/** @inheritDoc */
	public function onSidebarBeforeOutput( $skin, &$sidebar ): void {
		$out = $skin->getOutput();
		$title = $out->getTitle();
		$revid = $out->getRevisionId();

		if ( !$revid || !self::shouldAddLink( $title, $out->getConfig() ) ) {
			return;
		}

		$citeURL = SpecialPage::getTitleFor( 'CiteThisPage' )->getLocalURL( [
			'page' => $title->getPrefixedDBkey(),
			'id' => $revid,
			'wpFormIdentifier' => 'titleform'
		] );

		$citeThisPageLink = [
			'id' => 't-cite',
			'href' => $citeURL,
			'icon' => 'quotes',
			'text' => $skin->msg( 'citethispage-link' )->text(),
			// Message keys: 'tooltip-citethispage', 'accesskey-citethispage'
			'single-id' => 'citethispage',
		];

		// Append link
		$sidebar['TOOLBOX']['citethispage'] = $citeThisPageLink;
	}
}
