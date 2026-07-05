<?php
/**
 * Hooks for Title Blacklist
 * @author Victor Vasiliev
 * @copyright © 2007-2010 Victor Vasiliev et al
 * @license GPL-2.0-or-later
 */

namespace MediaWiki\Extension\TitleBlacklist;

use MediaWiki\Api\ApiMessage;
use MediaWiki\Api\ApiResult;
use MediaWiki\Content\TextContent;
use MediaWiki\Context\RequestContext;
use MediaWiki\Hook\MovePageCheckPermissionsHook;
use MediaWiki\Hook\TitleGetEditNoticesHook;
use MediaWiki\Logging\ManualLogEntry;
use MediaWiki\MainConfigNames;
use MediaWiki\Message\Message;
use MediaWiki\Page\WikiPage;
use MediaWiki\Permissions\GrantsInfo;
use MediaWiki\Permissions\Hook\GetUserPermissionsErrorsExpensiveHook;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Settings\SettingsBuilder;
use MediaWiki\Status\Status;
use MediaWiki\Storage\EditResult;
use MediaWiki\Storage\Hook\MultiContentSaveHook;
use MediaWiki\Storage\Hook\PageSaveCompleteHook;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentity;
use StatusValue;
use Wikimedia\Message\MessageSpecifier;
use Wikimedia\Message\MessageValue;

/**
 * Hooks for the TitleBlacklist class
 *
 * @ingroup Extensions
 */
class Hooks implements
	MultiContentSaveHook,
	TitleGetEditNoticesHook,
	MovePageCheckPermissionsHook,
	GetUserPermissionsErrorsExpensiveHook,
	PageSaveCompleteHook
{

	public static function onRegistration( array $extInfo, SettingsBuilder $settings ) {
		$grantRiskGroups = $settings->getConfig()->get( MainConfigNames::GrantRiskGroups );
		// Make sure the risk rating is at least 'security'. TitleBlacklist adds the
		// tboverride-account right to the createaccount grant, which makes it possible
		// to use it for social engineering attacks with restricted usernames.
		if ( $grantRiskGroups['createaccount'] !== GrantsInfo::RISK_INTERNAL ) {
			$grantRiskGroups['createaccount'] = GrantsInfo::RISK_SECURITY;
			$settings->overrideConfigValue( MainConfigNames::GrantRiskGroups, $grantRiskGroups );
		}
	}

	/**
	 * getUserPermissionsErrorsExpensive hook
	 *
	 * @param Title $title
	 * @param User $user
	 * @param string $action
	 * @param array|string|MessageSpecifier &$result
	 *
	 * @return bool
	 */
	public function onGetUserPermissionsErrorsExpensive( $title, $user, $action, &$result ) {
		# Some places check createpage, while others check create.
		# As it stands, upload does createpage, but normalize both
		# to the same action, to stop future similar bugs.
		if ( $action === 'createpage' || $action === 'createtalk' ) {
			$action = 'create';
		}
		if ( $action !== 'create' && $action !== 'edit' && $action !== 'upload' ) {
			return true;
		}
		$blacklisted = TitleBlacklist::singleton()->userCannot( $title, $user, $action );
		if ( !$blacklisted ) {
			return true;
		}

		$errmsg = $blacklisted->getErrorMessage( 'edit' );
		$params = [
			$blacklisted->getRaw(),
			$title->getFullText()
		];
		ApiResult::setIndexedTagName( $params, 'param' );
		$result = ApiMessage::create(
			wfMessage(
				$errmsg,
				wfEscapeWikiText( $blacklisted->getRaw() ),
				$title->getFullText()
			),
			'titleblacklist-forbidden',
			[
				'message' => [
					'key' => $errmsg,
					'params' => $params,
				],
				'line' => $blacklisted->getRaw(),
				// As $errmsg usually represents a non-default message here, and ApiBase
				// uses ->inLanguage( 'en' )->useDatabase( false ) for all messages, it will
				// never result in useful 'info' text in the API. Try this, extra data seems
				// to override the default.
				'info' => 'TitleBlacklist prevents this title from being created',
			]
		);
		return false;
	}

	/**
	 * Display a notice if a user is only able to create or edit a page
	 * because they have tboverride.
	 *
	 * @param Title $title
	 * @param int $oldid
	 * @param array &$notices
	 */
	public function onTitleGetEditNotices( $title, $oldid, &$notices ) {
		if ( !RequestContext::getMain()->getUser()->isAllowed( 'tboverride' ) ) {
			return;
		}

		$blacklisted = TitleBlacklist::singleton()->isBlacklisted(
			$title,
			$title->exists() ? 'edit' : 'create'
		);
		if ( !$blacklisted ) {
			return;
		}

		$params = $blacklisted->getParams();
		if ( isset( $params['autoconfirmed'] ) ) {
			return;
		}

		$msg = wfMessage( 'titleblacklist-warning' );
		$notices['titleblacklist'] = $msg->plaintextParams( $blacklisted->getRaw() )
			->parseAsBlock();
	}

	/**
	 * MovePageCheckPermissions hook (1.25+)
	 *
	 * @param Title $oldTitle
	 * @param Title $newTitle
	 * @param User $user
	 * @param string $reason
	 * @param Status $status
	 *
	 * @return bool
	 */
	public function onMovePageCheckPermissions(
		$oldTitle, $newTitle, $user, $reason, $status
	) {
		$titleBlacklist = TitleBlacklist::singleton();
		$blacklisted = $titleBlacklist->userCannot( $newTitle, $user, 'move' );
		if ( !$blacklisted ) {
			$blacklisted = $titleBlacklist->userCannot( $oldTitle, $user, 'edit' );
		}
		if ( $blacklisted instanceof TitleBlacklistEntry ) {
			$status->fatal( ApiMessage::create( [
				$blacklisted->getErrorMessage( 'move' ),
				wfEscapeWikiText( $blacklisted->getRaw() ),
				$oldTitle->getFullText(),
				$newTitle->getFullText()
			] ) );
			return false;
		}

		return true;
	}

	/**
	 * Check whether a user name is acceptable for account creation or autocreation, and explain
	 * why not if that's the case.
	 *
	 * @param string $userName
	 * @param User $creatingUser
	 * @param bool $override Should the test be skipped, if the user has sufficient privileges?
	 * @param bool $log Log blacklist hits to Special:Log
	 *
	 * @return StatusValue
	 */
	public static function testUserName(
		$userName, User $creatingUser, $override = true, $log = false
	) {
		$title = Title::makeTitleSafe( NS_USER, $userName );
		$blacklisted = TitleBlacklist::singleton()->userCannot( $title, $creatingUser,
			'new-account', $override );
		if ( !$blacklisted ) {
			return StatusValue::newGood();
		}

		if ( $log ) {
			self::logFilterHitUsername( $creatingUser, $title, $blacklisted->getRaw() );
		}
		$message = $blacklisted->getErrorMessage( 'new-account' );
		$params = [
			$blacklisted->getRaw(),
			$userName,
		];
		ApiResult::setIndexedTagName( $params, 'param' );
		return StatusValue::newFatal( ApiMessage::create(
			[ $message, wfEscapeWikiText( $blacklisted->getRaw() ), $userName ],
			'titleblacklist-forbidden',
			[
				'message' => [
					'key' => $message,
					'params' => $params,
				],
				'line' => $blacklisted->getRaw(),
				// The text of the message probably isn't useful API info, so do this instead
				'info' => 'TitleBlacklist prevents this username from being created',
			]
		) );
	}

	/**
	 * @inheritDoc
	 */
	public function onMultiContentSave( $renderedRevision, $user, $summary, $flags, $status ): bool {
		$page = $renderedRevision->getRevision()->getPage();
		if ( $page->getNamespace() !== NS_MEDIAWIKI || $page->getDBkey() !== 'Titleblacklist' ) {
			return true;
		}

		$blackList = TitleBlacklist::singleton();
		$content = $renderedRevision->getRevision()->getContent( SlotRecord::MAIN );
		if ( !$content instanceof TextContent ) {
			return true;
		}

		$text = $content->getText();
		$bl = TitleBlacklist::parseBlacklist( $text, 'page' );
		$ok = $blackList->validate( $bl );
		if ( $ok === [] ) {
			return true;
		}

		$invalidLines = '* <code>' .
			implode( "</code>\n* <code>", array_map( wfEscapeWikiText( ... ), $ok ) ) .
			'</code>';
		// We're passing a MessageValue to the StatusValue here so the parameters aren't wikitext-escaped.
		$status->fatal( MessageValue::new(
			'titleblacklist-invalid-error',
			[
				Message::numParam( count( $ok ) ),
				$invalidLines,
			]
		) );
		return false;
	}

	/**
	 * PageSaveComplete hook
	 *
	 * @param WikiPage $wikiPage
	 * @param UserIdentity $userIdentity
	 * @param string $summary
	 * @param int $flags
	 * @param RevisionRecord $revisionRecord
	 * @param EditResult $editResult
	 */
	public function onPageSaveComplete(
		$wikiPage,
		$userIdentity,
		$summary,
		$flags,
		$revisionRecord,
		$editResult
	) {
		$title = $wikiPage->getTitle();
		if ( $title->getNamespace() === NS_MEDIAWIKI && $title->getDBkey() == 'Titleblacklist' ) {
			TitleBlacklist::singleton()->invalidate();
		}
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
			$logEntry->setParameters( [
				'4::entry' => $entry,
			] );
			$logid = $logEntry->insert();
			$logEntry->publish( $logid );
		}
	}
}
