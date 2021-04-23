<?php

class CiteThisPageHooks {
	/**
	 * Checks, if the "cite this page" link should be added. By default the link is added to all
	 * pages in the main namespace, and additionally to pages, which are in one of the namespaces
	 * named in $wgCiteThisPageAddiotionalNamespaces.
	 *
	 * @param Title $title
	 * @return bool
	 */
	private static function shouldAddLink( Title $title ) {
		global $wgCiteThisPageAdditionalNamespaces;

		return $title->isContentPage() ||
			(
				isset( $wgCiteThisPageAdditionalNamespaces[$title->getNamespace()] ) &&
				$wgCiteThisPageAdditionalNamespaces[$title->getNamespace()]
			);
	}

	/**
	 * @param Skin $skin
	 * @param string[] &$sidebar
	 * @return void
	 */
	public static function onSidebarBeforeOutput( Skin $skin, array &$sidebar ): void {
		$out = $skin->getOutput();
		$title = $out->getTitle();

		if ( !self::shouldAddLink( $title ) ) {
			return;
		}

		$revid = $out->getRevisionId();

		if ( $revid === 0 || empty( $revid ) ) {
			return;
		}

		$specialPage = SpecialPage::getTitleFor( 'CiteThisPage' );
		$citeURL = $specialPage->getLocalURL( [
				'page' => $title->getPrefixedDBkey(),
				'id' => $revid,
				'wpFormIdentifier' => 'titleform'
			]
		);

		$citeThisPageLink = [
			'id' => 't-cite',
			'href' => $citeURL,
			'text' => $skin->msg( 'citethispage-link' )->text(),
			// Message keys: 'tooltip-citethispage', 'accesskey-citethispage'
			'single-id' => 'citethispage',
		];

		// Append link
		$sidebar['TOOLBOX']['citethispage'] = $citeThisPageLink;
	}
}
