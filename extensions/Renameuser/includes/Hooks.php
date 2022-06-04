<?php

namespace MediaWiki\Extension\Renameuser;

use Article;
use LogEventsList;
use MediaWiki\Hook\ContributionsToolLinksHook;
use MediaWiki\Hook\GetLogTypesOnUserHook;
use MediaWiki\Page\Hook\ShowMissingArticleHook;
use MediaWiki\Permissions\PermissionManager;
use SpecialPage;
use Title;
use User;

class Hooks implements
	ShowMissingArticleHook,
	ContributionsToolLinksHook,
	GetLogTypesOnUserHook
{

	/** @var PermissionManager */
	private $permissionManager;

	public function __construct( PermissionManager $permissionManager ) {
		$this->permissionManager = $permissionManager;
	}

	/**
	 * Show a log if the user has been renamed and point to the new username.
	 * Don't show the log if the $oldUserName exists as a user.
	 *
	 * @param Article $article
	 */
	public function onShowMissingArticle( $article ) {
		$title = $article->getTitle();
		$oldUser = User::newFromName( $title->getBaseText() );
		if ( ( $title->getNamespace() === NS_USER || $title->getNamespace() === NS_USER_TALK ) &&
			( $oldUser && $oldUser->isAnon() )
		) {
			// Get the title for the base userpage
			$page = Title::makeTitle( NS_USER, str_replace( ' ', '_', $title->getBaseText() ) )
				->getPrefixedDBkey();
			$out = $article->getContext()->getOutput();
			LogEventsList::showLogExtract(
				$out,
				'renameuser',
				$page,
				'',
				[
					'lim' => 10,
					'showIfEmpty' => false,
					'msgKey' => [ 'renameuser-renamed-notice', $title->getBaseText() ]
				]
			);
		}
	}

	/**
	 * Shows link to Special:Renameuser on Special:Contributions/foo
	 *
	 * @param int $id
	 * @param Title $nt
	 * @param array &$tools
	 * @param SpecialPage $sp
	 */
	public function onContributionsToolLinks(
		$id, Title $nt, array &$tools, SpecialPage $sp
	) {
		if ( $id && $this->permissionManager->userHasRight( $sp->getUser(), 'renameuser' ) ) {
			$tools['renameuser'] = $sp->getLinkRenderer()->makeKnownLink(
				SpecialPage::getTitleFor( 'Renameuser' ),
				$sp->msg( 'renameuser-linkoncontribs', $nt->getText() )->text(),
				[ 'title' => $sp->msg( 'renameuser-linkoncontribs-text', $nt->getText() )->parse() ],
				[ 'oldusername' => $nt->getText() ]
			);
		}
	}

	/**
	 * So users can just type in a username for target and it'll work
	 * @param array &$types
	 */
	public function onGetLogTypesOnUser( &$types ) {
		$types[] = 'renameuser';
	}
}
