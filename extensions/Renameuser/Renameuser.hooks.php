<?php

class RenameuserHooks {
	/**
	 * Show a log if the user has been renamed and point to the new username.
	 * Don't show the log if the $oldUserName exists as a user.
	 *
	 * @param $article Article
	 * @return bool
	 */
	public static function onShowMissingArticle( $article ) {
		$title = $article->getTitle();
		$oldUser = User::newFromName( $title->getBaseText() );
		if ( ($title->getNamespace() == NS_USER || $title->getNamespace() == NS_USER_TALK ) && ($oldUser && $oldUser->isAnon() )) {
			// Get the title for the base userpage
			$page = Title::makeTitle( NS_USER, str_replace( ' ', '_', $title->getBaseText() ) )->getPrefixedDBkey();
			$out = $article->getContext()->getOutput();
			LogEventsList::showLogExtract(
				$out,
				'renameuser',
				$page,
				'',
				array(
					'lim' => 10,
					'showIfEmpty' => false,
					'msgKey' => array( 'renameuser-renamed-notice', $title->getBaseText() )
				)
			);
		}

		return true;
	}

	/**
	 * Shows link to Special:Renameuser on Special:Contributions/foo
	 *
	 * @param $id
	 * @param $nt Title
	 * @param $tools
	 *
	 * @return bool
	 */
	public static function onContributionsToolLinks( $id, $nt, &$tools ) {
		global $wgUser;

		if ( $wgUser->isAllowed( 'renameuser' ) && $id ) {
			$tools[] = Linker::link(
				SpecialPage::getTitleFor( 'Renameuser' ),
				wfMessage( 'renameuser-linkoncontribs' )->text(),
				array( 'title' => wfMessage( 'renameuser-linkoncontribs-text' )->parse() ),
				array( 'oldusername' => $nt->getText() )
			);
		}
		return true;
	}

	/**
	 * So users can just type in a username for target and it'll work
	 * @param array $types
	 * @return bool
	 */
	public static function onGetLogTypesOnUser( array &$types ) {
		$types[] = 'renameuser';
		return true;
	}
}
