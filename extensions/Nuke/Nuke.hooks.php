<?php

class NukeHooks {

	/**
	 * Shows link to Special:Nuke on Special:Contributions/username if applicable
	 *
	 * @param $userId Integer
	 * @param $userPageTitle Title
	 * @param $toolLinks Array
	 *
	 * @return true
	 */
	public static function nukeContributionsLinks( $userId, $userPageTitle, &$toolLinks ) {
		global $wgUser;

		if ( $wgUser->isAllowed( 'nuke' ) ) {
			$toolLinks[] = Linker::link(
				SpecialPage::getTitleFor( 'Nuke' ),
				wfMessage( 'nuke-linkoncontribs' )->escaped(),
				array( 'title' => wfMessage( 'nuke-linkoncontribs-text' )->text() ),
				array( 'target' => $userPageTitle->getText() )
			);
		}
		return true;
	}
}
