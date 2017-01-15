<?php

class CiteThisPageHooks {

	/**
	 * @param SkinTemplate $skintemplate
	 * @param $nav_urls
	 * @param $oldid
	 * @param $revid
	 * @return bool
	 */
	public static function onSkinTemplateBuildNavUrlsNav_urlsAfterPermalink(
		&$skintemplate, &$nav_urls, &$oldid, &$revid
	) {
		// check whether we’re in the right namespace, the $revid has the correct type and is not empty
		// (which would mean that the current page doesn’t exist)
		$title = $skintemplate->getTitle();
		if ( $title->isContentPage() && $revid !== 0 && !empty( $revid ) ) {
			$nav_urls['citethispage'] = [
				'text' => $skintemplate->msg( 'citethispage-link' )->text(),
				'href' => SpecialPage::getTitleFor( 'CiteThisPage' )
					->getLocalURL( [ 'page' => $title->getPrefixedDBkey(), 'id' => $revid ] ),
				'id' => 't-cite',
				# Used message keys: 'tooltip-citethispage', 'accesskey-citethispage'
				'single-id' => 'citethispage',
			];
		}

		return true;
	}

	/**
	 * @param BaseTemplate $baseTemplate
	 * @param array $toolbox
	 * @return bool
	 */
	public static function onBaseTemplateToolbox( BaseTemplate $baseTemplate, array &$toolbox ) {
		if ( isset( $baseTemplate->data['nav_urls']['citethispage'] ) ) {
			$toolbox['citethispage'] = $baseTemplate->data['nav_urls']['citethispage'];
		}

		return true;
	}
}
