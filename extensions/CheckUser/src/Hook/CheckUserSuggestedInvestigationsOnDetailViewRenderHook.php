<?php

namespace MediaWiki\Extension\CheckUser\Hook;

use MediaWiki\Output\OutputPage;

interface CheckUserSuggestedInvestigationsOnDetailViewRenderHook {
	/**
	 * This hook is run when rendering the "detail view" on Special:SuggestedInvestigations (a page
	 * with the format Special:SuggestedInvestigations/detail/X where X is a hexadecimal string).
	 *
	 * Hooks can interact with the provided OutputPage instance to add additional content to the page,
	 * as well as other changes to the output to support this. The intention is for this to be used
	 * to add extended information about the case that is not generic to every case.
	 *
	 * NOTE: Private code handles this hook, so updating it's signature may break code not visible
	 * in codesearch.
	 *
	 * @since 1.46
	 * @param int $caseId The ID of the case (a sic_id value in cusi_case) being viewed in the detail view
	 * @param OutputPage $output The OutputPage instance for the special page, used to add content to the detail view
	 */
	public function onCheckUserSuggestedInvestigationsOnDetailViewRender( int $caseId, $output ): void;
}

// @codeCoverageIgnoreStart
/**
 * @deprecated since 1.46
 */
class_alias(
	CheckUserSuggestedInvestigationsOnDetailViewRenderHook::class,
	'MediaWiki\\CheckUser\\Hook\\CheckUserSuggestedInvestigationsOnDetailViewRenderHook'
);
// @codeCoverageIgnoreEnd
