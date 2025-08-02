<?php
namespace MediaWiki\CheckUser\HookHandler;

use MediaWiki\SpecialPage\Hook\SpecialPageBeforeExecuteHook;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\WikiMap\WikiMap;

class SpecialPageBeforeExecuteHandler implements SpecialPageBeforeExecuteHook {

	/**
	 * Is there a central wiki defined for the Special:GlobalContributions feature?
	 * If so, redirect the user there, preserving the query parameters.
	 *
	 * @param SpecialPage $special
	 * @param string|null $subPage
	 * @return bool `true` to continue default special page execution, `false` to abort
	 */
	public function onSpecialPageBeforeExecute( $special, $subPage ): bool {
		$context = $special->getContext();
		$globalContributionsCentralWikiId = $context->getConfig()->get( 'CheckUserGlobalContributionsCentralWikiId' );
		$title = $context->getTitle();

		if ( $globalContributionsCentralWikiId &&
			$title->isSpecial( 'GlobalContributions' ) &&
			$globalContributionsCentralWikiId !== WikiMap::getCurrentWikiId() ) {
			// Note: Use the canonical (English) name for the namespace and page
			// since non-English aliases would likely not be recognized by the central wiki.
			$page = "Special:GlobalContributions";
			$slashPos = strpos( $title->getText(), '/' );
			if ( $slashPos !== false ) {
				$page .= substr(
					$title->getText(),
					$slashPos
				);
			}

			$url = WikiMap::getForeignURL(
				$globalContributionsCentralWikiId,
				$page,
			);
			$queryValues = $context->getRequest()->getQueryValuesOnly();
			// Don't duplicate the title, as we have this already from ::getForeignURL above
			if ( isset( $queryValues['title'] ) ) {
				unset( $queryValues['title'] );
			}
			$url = wfAppendQuery( $url, $queryValues );
			$context->getOutput()->redirect( $url );

			return false;
		}

		return true;
	}
}
