<?php

// phpcs:disable MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName

namespace MediaWiki\Extension\Thanks;

use ApiModuleManager;
use Article;
use ConfigException;
use DatabaseLogEntry;
use DifferenceEngine;
use EchoAttributeManager;
use EchoUserLocator;
use ExtensionRegistry;
use Html;
use IContextSource;
use LogEventsList;
use LogPage;
use MediaWiki\Api\Hook\ApiMain__moduleManagerHook;
use MediaWiki\Auth\Hook\LocalUserCreatedHook;
use MediaWiki\Block\Hook\GetAllBlockActionsHook;
use MediaWiki\Diff\Hook\DifferenceEngineViewHeaderHook;
use MediaWiki\Diff\Hook\DiffToolsHook;
use MediaWiki\Extension\Notifications\Model\Event;
use MediaWiki\Extension\Thanks\Api\ApiFlowThank;
use MediaWiki\Hook\BeforePageDisplayHook;
use MediaWiki\Hook\GetLogTypesOnUserHook;
use MediaWiki\Hook\HistoryToolsHook;
use MediaWiki\Hook\LogEventsListLineEndingHook;
use MediaWiki\Hook\PageHistoryBeforeListHook;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;
use MediaWiki\User\UserIdentity;
use MobileContext;
use OutputPage;
use Skin;
use SpecialPage;
use User;

/**
 * Hooks for Thanks extension
 *
 * @file
 * @ingroup Extensions
 */
class Hooks implements
	ApiMain__moduleManagerHook,
	BeforePageDisplayHook,
	DiffToolsHook,
	DifferenceEngineViewHeaderHook,
	GetAllBlockActionsHook,
	GetLogTypesOnUserHook,
	HistoryToolsHook,
	LocalUserCreatedHook,
	LogEventsListLineEndingHook,
	PageHistoryBeforeListHook
{

	/**
	 * Handler for the HistoryTools hook
	 *
	 * @param RevisionRecord $revisionRecord
	 * @param array &$links
	 * @param RevisionRecord|null $oldRevisionRecord
	 * @param UserIdentity $userIdentity
	 */
	public function onHistoryTools(
		$revisionRecord,
		&$links,
		$oldRevisionRecord,
		$userIdentity
	) {
		self::insertThankLink( $revisionRecord,
			$links, $userIdentity );
	}

	/**
	 * Handler for the DiffTools hook
	 *
	 * @param RevisionRecord $revisionRecord
	 * @param array &$links
	 * @param RevisionRecord|null $oldRevisionRecord
	 * @param UserIdentity $userIdentity
	 */
	public function onDiffTools(
		$revisionRecord,
		&$links,
		$oldRevisionRecord,
		$userIdentity
	) {
		// Don't allow thanking for a diff that includes multiple revisions
		// This does a query that is too expensive for history rows (T284274)
		$previous = MediaWikiServices::getInstance()
			->getRevisionLookup()
			->getPreviousRevision( $revisionRecord );
		if ( $oldRevisionRecord && $previous &&
			$previous->getId() !== $oldRevisionRecord->getId()
		) {
			return;
		}

		self::insertThankLink( $revisionRecord,
			$links, $userIdentity );
	}

	/**
	 * Insert a 'thank' link into revision interface, if the user is allowed to thank.
	 *
	 * @param RevisionRecord $revisionRecord RevisionRecord object to add the thank link for
	 * @param array &$links Links to add to the revision interface
	 * @param UserIdentity $userIdentity The user performing the thanks.
	 */
	private static function insertThankLink(
		RevisionRecord $revisionRecord,
		array &$links,
		UserIdentity $userIdentity
	) {
		$recipient = $revisionRecord->getUser();
		if ( $recipient === null ) {
			// Cannot see the user
			return;
		}

		$user = MediaWikiServices::getInstance()->getUserFactory()->newFromUserIdentity( $userIdentity );

		// Don't let users thank themselves.
		// Exclude anonymous users.
		// Exclude temp users (T345679)
		// Exclude users who are blocked.
		// Check whether bots are allowed to receive thanks.
		// Don't allow thanking for a diff that includes multiple revisions
		// Check whether we have a revision id to link to
		if ( $user->isNamed()
			&& !$userIdentity->equals( $recipient )
			&& !self::isUserBlockedFromTitle( $user, $revisionRecord->getPageAsLinkTarget() )
			&& !self::isUserBlockedFromThanks( $user )
			&& self::canReceiveThanks( $recipient )
			&& !$revisionRecord->isDeleted( RevisionRecord::DELETED_TEXT )
			&& $revisionRecord->getId() !== 0
		) {
			$links[] = self::generateThankElement(
				$revisionRecord->getId(),
				$user,
				$recipient
			);
		}
	}

	/**
	 * Check whether the user is blocked from the title associated with the revision.
	 *
	 * This queries the replicas for a block; if 'no block' is incorrectly reported, it
	 * will be caught by ApiThank::dieOnUserBlockedFromTitle when the user attempts to thank.
	 *
	 * @param User $user
	 * @param LinkTarget $title
	 * @return bool
	 */
	private static function isUserBlockedFromTitle( User $user, LinkTarget $title ) {
		return MediaWikiServices::getInstance()->getPermissionManager()
			->isBlockedFrom( $user, $title, true );
	}

	/**
	 * Check whether the user is blocked from giving thanks.
	 *
	 * @param User $user
	 * @return bool
	 */
	private static function isUserBlockedFromThanks( User $user ) {
		$block = $user->getBlock();
		return $block && ( $block->isSitewide() || $block->appliesToRight( 'thanks' ) );
	}

	/**
	 * Check whether a user is allowed to receive thanks or not
	 *
	 * @param UserIdentity $user Recipient
	 * @return bool true if allowed, false if not
	 */
	protected static function canReceiveThanks( UserIdentity $user ) {
		global $wgThanksSendToBots;

		$legacyUser = MediaWikiServices::getInstance()->getUserFactory()->newFromUserIdentity( $user );
		if ( !$user->isRegistered() || $legacyUser->isSystemUser() ) {
			return false;
		}

		if ( !$wgThanksSendToBots && $legacyUser->isBot() ) {
			return false;
		}

		return true;
	}

	/**
	 * Helper for self::insertThankLink
	 * Creates either a thank link or thanked span based on users session
	 * @param int $id Revision or log ID to generate the thank element for.
	 * @param User $sender User who sends thanks notification.
	 * @param UserIdentity $recipient User who receives thanks notification.
	 * @param string $type Either 'revision' or 'log'.
	 * @return string
	 */
	protected static function generateThankElement(
		$id, User $sender, UserIdentity $recipient, $type = 'revision'
	) {
		// Check if the user has already thanked for this revision or log entry.
		// Session keys are backwards-compatible, and are also used in the ApiCoreThank class.
		$sessionKey = ( $type === 'revision' ) ? $id : $type . $id;
		if ( $sender->getRequest()->getSessionData( "thanks-thanked-$sessionKey" ) ) {
			return Html::element(
				'span',
				[ 'class' => 'mw-thanks-thanked' ],
				wfMessage( 'thanks-thanked', $sender->getName(), $recipient->getName() )->text()
			);
		}

		$genderCache = MediaWikiServices::getInstance()->getGenderCache();
		// Add 'thank' link
		$tooltip = wfMessage( 'thanks-thank-tooltip' )
			->params( $sender->getName(), $recipient->getName() )
			->text();

		$subpage = ( $type === 'revision' ) ? '' : 'Log/';
		return Html::element(
			'a',
			[
				'class' => 'mw-thanks-thank-link',
				'href' => SpecialPage::getTitleFor( 'Thanks', $subpage . $id )->getFullURL(),
				'title' => $tooltip,
				'data-' . $type . '-id' => $id,
				'data-recipient-gender' => $genderCache->getGenderOf( $recipient->getName(), __METHOD__ ),
			],
			wfMessage( 'thanks-thank', $sender->getName(), $recipient->getName() )->text()
		);
	}

	/**
	 * @param OutputPage $outputPage The OutputPage to add the module to.
	 */
	protected static function addThanksModule( OutputPage $outputPage ) {
		$confirmationRequired = MediaWikiServices::getInstance()
			->getMainConfig()
			->get( 'ThanksConfirmationRequired' );
		$outputPage->addModules( [ 'ext.thanks.corethank' ] );
		$outputPage->addJsConfigVars( 'thanks-confirmation-required', $confirmationRequired );
	}

	/**
	 * Handler for PageHistoryBeforeList hook.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/PageHistoryBeforeList
	 *
	 * @param Article $page Not used
	 * @param IContextSource $context RequestContext object
	 */
	public function onPageHistoryBeforeList( $page, $context ) {
		if ( $context->getUser()->isRegistered() ) {
			static::addThanksModule( $context->getOutput() );
		}
	}

	/**
	 * Handler for DifferenceEngineViewHeader hook.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/DifferenceEngineViewHeader
	 * @param DifferenceEngine $diff DifferenceEngine object that's calling.
	 */
	public function onDifferenceEngineViewHeader( $diff ) {
		if ( $diff->getUser()->isRegistered() ) {
			static::addThanksModule( $diff->getOutput() );
		}
	}

	/**
	 * Add Thanks events to Echo
	 *
	 * @param array &$notifications array of Echo notifications
	 * @param array &$notificationCategories array of Echo notification categories
	 * @param array &$icons array of icon details
	 */
	public static function onBeforeCreateEchoEvent(
		&$notifications, &$notificationCategories, &$icons
	) {
		$notificationCategories['edit-thank'] = [
			'priority' => 3,
			'tooltip' => 'echo-pref-tooltip-edit-thank',
		];

		$notifications['edit-thank'] = [
			'category' => 'edit-thank',
			'group' => 'positive',
			'section' => 'message',
			'presentation-model' => EchoCoreThanksPresentationModel::class,
			'bundle' => [
				'web' => true,
				'expandable' => true,
			],
			EchoAttributeManager::ATTR_LOCATORS => [
				[
					[ EchoUserLocator::class, 'locateFromEventExtra' ],
					[ 'thanked-user-id' ]
				],
			],
		];

		if ( ExtensionRegistry::getInstance()->isLoaded( 'Flow' ) ) {
			$notifications['flow-thank'] = [
				'category' => 'edit-thank',
				'group' => 'positive',
				'section' => 'message',
				'presentation-model' => EchoFlowThanksPresentationModel::class,
				'bundle' => [
					'web' => true,
					'expandable' => true,
				],
				EchoAttributeManager::ATTR_LOCATORS => [
					[
						[ EchoUserLocator::class, 'locateFromEventExtra' ],
						[ 'thanked-user-id' ]
					],
				],
			];
		}

		$icons['thanks'] = [
			'path' => [
				'ltr' => 'Thanks/modules/userTalk-constructive-ltr.svg',
				'rtl' => 'Thanks/modules/userTalk-constructive-rtl.svg'
			]
		];
	}

	/**
	 * Handler for LocalUserCreated hook
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/LocalUserCreated
	 * @param User $user User object that was created.
	 * @param bool $autocreated True when account was auto-created
	 */
	public function onLocalUserCreated( $user, $autocreated ) {
		// New users get echo preferences set that are not the default settings for existing users.
		// Specifically, new users are opted into email notifications for thanks.
		if ( !$user->isTemp() && !$autocreated ) {
			$userOptionsManager = MediaWikiServices::getInstance()->getUserOptionsManager();
			$userOptionsManager->setOption( $user, 'echo-subscriptions-email-edit-thank', true );
		}
	}

	/**
	 * Add thanks button to SpecialMobileDiff page
	 * @param OutputPage &$output OutputPage object
	 * @param MobileContext $ctx MobileContext object
	 * @param array $revisions Array with two elements, either nulls or RevisionRecord objects for
	 *     the two revisions that are being compared in the diff
	 */
	public static function onBeforeSpecialMobileDiffDisplay( &$output, $ctx, $revisions ) {
		$rev = $revisions[1];

		// If the MobileFrontend extension is installed and the user is
		// logged in or recipient is not a bot if bots cannot receive thanks, show a 'Thank' link.
		if ( $rev
			&& ExtensionRegistry::getInstance()->isLoaded( 'MobileFrontend' )
			&& $rev->getUser()
			&& self::canReceiveThanks( $rev->getUser() )
			&& $output->getUser()->isRegistered()
		) {
			$output->addModules( [ 'ext.thanks.mobilediff' ] );

			if ( $output->getRequest()->getSessionData( 'thanks-thanked-' . $rev->getId() ) ) {
				// User already sent thanks for this revision
				$output->addJsConfigVars( 'wgThanksAlreadySent', true );
			}

		}
	}

	/**
	 * Handler for GetLogTypesOnUser.
	 * So users can just type in a username for target and it'll work.
	 * @link https://www.mediawiki.org/wiki/Manual:Hooks/GetLogTypesOnUser
	 * @param string[] &$types The list of log types, to add to.
	 */
	public function onGetLogTypesOnUser( &$types ) {
		$types[] = 'thanks';
	}

	public function onGetAllBlockActions( &$actions ) {
		$actions[ 'thanks' ] = 100;
	}

	/**
	 * Handler for BeforePageDisplay.  Inserts javascript to enhance thank
	 * links from static urls to in-page dialogs along with reloading
	 * the previously thanked state.
	 * @link https://www.mediawiki.org/wiki/Manual:Hooks/BeforePageDisplay
	 * @param OutputPage $out OutputPage object
	 * @param Skin $skin The skin in use.
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		$title = $out->getTitle();
		// Add to Flow boards.
		if ( $title instanceof Title && $title->hasContentModel( 'flow-board' ) ) {
			$out->addModules( 'ext.thanks.flowthank' );
		}
		// Add to special pages where thank links appear
		if (
			$title->isSpecial( 'Log' ) ||
			$title->isSpecial( 'Contributions' ) ||
			$title->isSpecial( 'DeletedContributions' ) ||
			$title->isSpecial( 'Recentchanges' ) ||
			$title->isSpecial( 'Recentchangeslinked' ) ||
			$title->isSpecial( 'Watchlist' )
		) {
			static::addThanksModule( $out );
		}
	}

	/**
	 * Conditionally load API module 'flowthank' depending on whether or not
	 * Flow is installed.
	 *
	 * @param ApiModuleManager $moduleManager Module manager instance
	 */
	public function onApiMain__moduleManager( $moduleManager ) {
		if ( ExtensionRegistry::getInstance()->isLoaded( 'Flow' ) ) {
			$moduleManager->addModule(
				'flowthank',
				'action',
				[
					"class" => ApiFlowThank::class,
					"services" => [
						"PermissionManager",
						"ThanksLogStore"
					]
				]
			);
		}
	}

	/**
	 * Handler for EchoGetBundleRule hook, which defines the bundle rules for each notification.
	 *
	 * @param Event $event The event being notified.
	 * @param string &$bundleString Determines how the notification should be bundled.
	 */
	public static function onEchoGetBundleRules( $event, &$bundleString ) {
		switch ( $event->getType() ) {
			case 'edit-thank':
				$bundleString = 'edit-thank';
				// Try to get either the revid or logid parameter.
				$revOrLogId = $event->getExtraParam( 'logid' );
				if ( $revOrLogId ) {
					// avoid collision with revision ids
					$revOrLogId = 'log' . $revOrLogId;
				} else {
					$revOrLogId = $event->getExtraParam( 'revid' );
				}
				if ( $revOrLogId ) {
					$bundleString .= $revOrLogId;
				}
				break;
			case 'flow-thank':
				$bundleString = 'flow-thank';
				$postId = $event->getExtraParam( 'post-id' );
				if ( $postId ) {
					$bundleString .= $postId;
				}
				break;
		}
	}

	/**
	 * Insert a 'thank' link into the log interface, if the user is allowed to thank.
	 *
	 * @link https://www.mediawiki.org/wiki/Manual:Hooks/LogEventsListLineEnding
	 * @param LogEventsList $page The log events list.
	 * @param string &$ret The line ending HTML, to modify.
	 * @param DatabaseLogEntry $entry The log entry.
	 * @param string[] &$classes CSS classes to add to the line.
	 * @param string[] &$attribs HTML attributes to add to the line.
	 * @throws ConfigException
	 */
	public function onLogEventsListLineEnding(
		$page, &$ret, $entry, &$classes, &$attribs
	) {
		$user = $page->getUser();

		// Don't thank if anonymous or blocked or if user is deleted from the log entry
		if (
			$user->isAnon()
			|| $entry->isDeleted( LogPage::DELETED_USER )
			|| self::isUserBlockedFromTitle( $user, $entry->getTarget() )
			|| self::isUserBlockedFromThanks( $user )
		) {
			return;
		}

		// Make sure this log type is allowed.
		$allowedLogTypes = MediaWikiServices::getInstance()
			->getMainConfig()
			->get( 'ThanksAllowedLogTypes' );
		if ( !in_array( $entry->getType(), $allowedLogTypes )
			&& !in_array( $entry->getType() . '/' . $entry->getSubtype(), $allowedLogTypes ) ) {
			return;
		}

		// Don't thank if no recipient,
		// or if recipient is the current user or unable to receive thanks.
		// Don't check for deleted revision (this avoids extraneous queries from Special:Log).

		$recipient = $entry->getPerformerIdentity();
		if ( $recipient->getId() === $user->getId() || !self::canReceiveThanks( $recipient ) ) {
			return;
		}

		// Create thank link either for the revision (if there is an associated revision ID)
		// or the log entry.
		$type = $entry->getAssociatedRevId() ? 'revision' : 'log';
		$id = $entry->getAssociatedRevId() ?: $entry->getId();
		$thankLink = self::generateThankElement( $id, $user, $recipient, $type );

		// Add parentheses to match what's done with Thanks in revision lists and diff displays.
		$ret .= ' ' . wfMessage( 'parentheses' )->rawParams( $thankLink )->escaped();
	}
}
