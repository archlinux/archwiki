<?php
/**
 * Hooks for Title Blacklist
 * @author Victor Vasiliev
 * @copyright © 2007-2010 Victor Vasiliev et al
 * @license GNU General Public License 2.0 or later
 */

/**
 * Hooks for the TitleBlacklist class
 *
 * @ingroup Extensions
 */
class TitleBlacklistHooks {

	/**
	 * getUserPermissionsErrorsExpensive hook
	 *
	 * @param $title Title
	 * @param $user User
	 * @param $action
	 * @param $result
	 * @return bool
	 */
	public static function userCan( $title, $user, $action, &$result ) {
		# Some places check createpage, while others check create.
		# As it stands, upload does createpage, but normalize both
		# to the same action, to stop future similar bugs.
		if ( $action === 'createpage' || $action === 'createtalk' ) {
			$action = 'create';
		}
		if ( $action == 'create' || $action == 'edit' || $action == 'upload' ) {
			$blacklisted = TitleBlacklist::singleton()->userCannot( $title, $user, $action );
			if ( $blacklisted instanceof TitleBlacklistEntry ) {
				$errmsg = $blacklisted->getErrorMessage( 'edit' );
				ApiBase::$messageMap[$errmsg] = array(
					'code' => $errmsg,
					'info' => 'TitleBlacklist prevents this title from being created'
				);
				$result = array( $errmsg,
					htmlspecialchars( $blacklisted->getRaw() ),
					$title->getFullText() );
				return false;
			}
		}
		return true;
	}

	/**
	 * Display a notice if a user is only able to create or edit a page
	 * because they have tboverride.
	 *
	 * @param Title $title
	 * @param integer $oldid
	 * @param array &$notices
	 */
	public static function displayBlacklistOverrideNotice( Title $title, $oldid, array &$notices ) {
		if ( !RequestContext::getMain()->getUser()->isAllowed( 'tboverride' ) ) {
			return true;
		}

		$blacklisted = TitleBlacklist::singleton()->isBlacklisted(
			$title,
			$title->exists() ? 'edit' : 'create'
		);
		if ( !$blacklisted ) {
			return true;
		}

		$params = $blacklisted->getParams();
		$msg = wfMessage( 'titleblacklist-warning' );
		$notices['titleblacklist'] = $msg->rawParams(
			htmlspecialchars( $blacklisted->getRaw() ) )->parseAsBlock();
		return true;
	}

	/**
	 * MovePageCheckPermissions hook (1.25+)
	 *
	 * @param Title $oldTitle
	 * @param Title $newTitle
	 * @param User $user
	 * @param $reason
	 * @param Status $status
	 * @return bool
	 */
	public static function onMovePageCheckPermissions( Title $oldTitle, Title $newTitle, User $user, $reason, Status $status ) {
		$titleBlacklist = TitleBlacklist::singleton();
		$blacklisted = $titleBlacklist->userCannot( $newTitle, $user, 'move' );
		if ( !$blacklisted ) {
			$blacklisted = $titleBlacklist->userCannot( $oldTitle, $user, 'edit' );
		}
		if ( $blacklisted instanceof TitleBlacklistEntry ) {
			$errmsg = $blacklisted->getErrorMessage( 'move' );
			ApiBase::$messageMap[$errmsg] = array(
				'code' => $errmsg,
				'info' => 'TitleBlacklist prevents this new title from being created or old title from being edited'
			);
			$status->fatal( $errmsg,
				$blacklisted->getRaw(),
				$oldTitle->getFullText(),
				$newTitle->getFullText() );
			return false;
		}

		return true;
	}

	/**
	 * Check whether a user name is acceptable,
	 * and set a message if unacceptable.
	 *
	 * Used by abortNewAccount and centralAuthAutoCreate.
	 * May also be called externally to vet alternate account names.
	 *
	 * @return bool Acceptable
	 */
	public static function acceptNewUserName( $userName, $permissionsUser, &$err, $override = true, $log = false ) {
		global $wgUser;
		$title = Title::makeTitleSafe( NS_USER, $userName );
		$blacklisted = TitleBlacklist::singleton()->userCannot( $title, $permissionsUser,
			'new-account', $override );
		if ( $blacklisted instanceof TitleBlacklistEntry ) {
			$message = $blacklisted->getErrorMessage( 'new-account' );
			ApiBase::$messageMap[$message] = array(
				'code' => $message,
				'info' => 'TitleBlacklist prevents this username from being created'
			);
			$err = wfMessage( $message, $blacklisted->getRaw(), $userName )->parse();
			if ( $log ) {
				self::logFilterHitUsername( $wgUser, $title, $blacklisted->getRaw() );
			}
			return false;
		}
		return true;
	}

	/**
	 * AbortNewAccount hook
	 *
	 * @param User $user
	 * @param string &$message
	 * @return bool
	 */
	public static function abortNewAccount( $user, &$message ) {
		global $wgUser, $wgRequest;
		$override = $wgRequest->getCheck( 'wpIgnoreTitleBlacklist' );
		return self::acceptNewUserName( $user->getName(), $wgUser, $message, $override, true );
	}

	/**
	 * AbortAutoAccount hook
	 *
	 * @param User $user
	 * @param string &$message
	 * @return bool
	 */
	public static function abortAutoAccount( $user, &$message ) {
		global $wgTitleBlacklistBlockAutoAccountCreation;
		if ( $wgTitleBlacklistBlockAutoAccountCreation ) {
			return self::abortNewAccount( $user, $message );
		}
		return true;
	}

	/**
	 * EditFilter hook
	 *
	 * @param $editor EditPage
	 */
	public static function validateBlacklist( $editor, $text, $section, &$error ) {
		global $wgUser;
		$title = $editor->mTitle;

		if ( $title->getNamespace() == NS_MEDIAWIKI && $title->getDBkey() == 'Titleblacklist' ) {

			$blackList = TitleBlacklist::singleton();
			$bl = $blackList->parseBlacklist( $text, 'page' );
			$ok = $blackList->validate( $bl );
			if ( count( $ok ) == 0 ) {
				return true;
			}

			$errmsg = wfMessage( 'titleblacklist-invalid' )->numParams( count( $ok ) )->text();
			$errlines = '* <code>' . implode( "</code>\n* <code>", array_map( 'wfEscapeWikiText', $ok ) ) . '</code>';
			$error = Html::openElement( 'div', array( 'class' => 'errorbox' ) ) .
				$errmsg .
				"\n" .
				$errlines .
				Html::closeElement( 'div' ) . "\n" .
				Html::element( 'br', array( 'clear' => 'all' ) ) . "\n";

			// $error will be displayed by the edit class
		}
		return true;
	}

	/**
	 * ArticleSaveComplete hook
	 *
	 * @param Article $article
	 */
	public static function clearBlacklist( &$article, &$user,
		$text, $summary, $isminor, $iswatch, $section )
	{
		$title = $article->getTitle();
		if ( $title->getNamespace() == NS_MEDIAWIKI && $title->getDBkey() == 'Titleblacklist' ) {
			TitleBlacklist::singleton()->invalidate();
		}
		return true;
	}

	/** UserCreateForm hook based on the one from AntiSpoof extension */
	public static function addOverrideCheckbox( &$template ) {
		global $wgRequest, $wgUser;

		if ( TitleBlacklist::userCanOverride( $wgUser, 'new-account' ) ) {
			$template->addInputItem( 'wpIgnoreTitleBlacklist',
				$wgRequest->getCheck( 'wpIgnoreTitleBlacklist' ),
				'checkbox', 'titleblacklist-override' );
		}
		return true;
	}

	/**
	 * Logs the filter username hit to Special:Log if
	 * $wgTitleBlacklistLogHits is enabled.
	 *
	 * @param User $user
	 * @param Title $title
	 * @param string $entry
	 */
	public static function logFilterHitUsername( $user, $title, $entry ) {
		global $wgTitleBlacklistLogHits;
		if ( $wgTitleBlacklistLogHits ) {
			$logEntry = new ManualLogEntry( 'titleblacklist', 'hit-username' );
			$logEntry->setPerformer( $user );
			$logEntry->setTarget( $title );
			$logEntry->setParameters( array(
				'4::entry' => $entry,
			) );
			$logid = $logEntry->insert();
			$logEntry->publish( $logid );
		}
	}

	/**
	 * Add phpunit tests
	 *
	 * @param array &$files List of test cases and directories to search
	 * @return bool
	 */
	public static function unitTestsList( &$files ) {
		$files = array_merge( $files, glob( __DIR__ . '/tests/*Test.php' ) );
		return true;
	}

	/**
	 * External Lua library for Scribunto
	 *
	 * @param string $engine
	 * @param array $extraLibraries
	 * @return bool
	 */
	public static function scribuntoExternalLibraries( $engine, array &$extraLibraries ) {
		if( $engine == 'lua' ) {
			$extraLibraries['mw.ext.TitleBlacklist'] = 'Scribunto_LuaTitleBlacklistLibrary';
		}
		return true;
	}
}
