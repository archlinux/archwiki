<?php

class CiteThisPageHooks {

	/**
	 * @param SkinTemplate $skintemplate
	 * @param $nav_urls
	 * @param $oldid
	 * @param $revid
	 * @return bool
	 */
	public static function onSkinTemplateBuildNavUrlsNav_urlsAfterPermalink( &$skintemplate, &$nav_urls, &$oldid, &$revid ) {
		// check whether we’re in the right namespace, the $revid has the correct type and is not empty
		// (which would mean that the current page doesn’t exist)
		$title = $skintemplate->getTitle();
		if ( $title->isContentPage() && $revid !== 0 && !empty( $revid ) )
			$nav_urls['citeThisPage'] = array(
				'args' => array( 'page' => $title->getPrefixedDBkey(), 'id' => $revid )
			);

		return true;
	}

	/**
	 * @param Skin $skin
	 * @return bool
	 */
	public static function onSkinTemplateToolboxEnd( &$skin ) {
		if ( isset( $skin->data['nav_urls']['citeThisPage'] ) ) {
			echo Html::rawElement(
				'li',
				array( 'id' => 't-cite' ),
				Linker::link(
					SpecialPage::getTitleFor( 'CiteThisPage' ),
					wfMessage( 'citethispage-link' )->escaped(),
					# Used message keys: 'tooltip-citethispage', 'accesskey-citethispage'
					Linker::tooltipAndAccessKeyAttribs( 'citethispage' ),
					$skin->data['nav_urls']['citeThisPage']['args']
				)
			);
		}

		return true;
	}
}
