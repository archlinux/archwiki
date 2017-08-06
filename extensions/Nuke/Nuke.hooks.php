<?php

class NukeHooks {

	/**
	 * Shows link to Special:Nuke on Special:Contributions/username if applicable
	 *
	 * @param int $userId
	 * @param Title $userPageTitle
	 * @param string[] $toolLinks
	 * @param SpecialPage $sp
	 */
	public static function nukeContributionsLinks( $userId, $userPageTitle, &$toolLinks,
		SpecialPage $sp
	) {
		if ( $sp->getUser()->isAllowed( 'nuke' ) ) {
			$toolLinks['nuke'] = $sp->getLinkRenderer()->makeKnownLink(
				SpecialPage::getTitleFor( 'Nuke' ),
				$sp->msg( 'nuke-linkoncontribs' )->text(),
				[ 'title' => $sp->msg( 'nuke-linkoncontribs-text' )->text() ],
				[ 'target' => $userPageTitle->getText() ]
			);
		}
	}
}
