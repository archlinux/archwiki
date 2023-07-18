<?php

namespace MediaWiki\Extension\Notifications;

use ApiModuleManager;
use Config;
use Content;
use DatabaseUpdater;
use DeferredUpdates;
use EchoAttributeManager;
use EchoDiscussionParser;
use EchoEmailFormat;
use EchoEmailFrequency;
use EchoSeenTime;
use EchoServices;
use EmailNotification;
use ExtensionRegistry;
use Hooks as MWHooks;
use HTMLCheckMatrix;
use LinksUpdate;
use LogEntry;
use MailAddress;
use MediaWiki\Api\Hook\ApiMain__moduleManagerHook;
use MediaWiki\Auth\Hook\LocalUserCreatedHook;
use MediaWiki\DAO\WikiAwareEntity;
use MediaWiki\Extension\Notifications\Controller\ModerationController;
use MediaWiki\Extension\Notifications\Controller\NotificationController;
use MediaWiki\Extension\Notifications\Formatters\EchoEventPresentationModel;
use MediaWiki\Extension\Notifications\Mapper\EventMapper;
use MediaWiki\Extension\Notifications\Mapper\NotificationMapper;
use MediaWiki\Extension\Notifications\Model\Event;
use MediaWiki\Extension\Notifications\Model\Notification;
use MediaWiki\Extension\Notifications\Push\Api\ApiEchoPushSubscriptions;
use MediaWiki\Hook\AbortTalkPageEmailNotificationHook;
use MediaWiki\Hook\BeforePageDisplayHook;
use MediaWiki\Hook\EmailUserCompleteHook;
use MediaWiki\Hook\GetNewMessagesAlertHook;
use MediaWiki\Hook\LinksUpdateCompleteHook;
use MediaWiki\Hook\LoginFormValidErrorMessagesHook;
use MediaWiki\Hook\OutputPageCheckLastModifiedHook;
use MediaWiki\Hook\PreferencesGetIconHook;
use MediaWiki\Hook\RecentChange_saveHook;
use MediaWiki\Hook\SendWatchlistEmailNotificationHook;
use MediaWiki\Hook\SkinTemplateNavigation__UniversalHook;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\Hook\ArticleDeleteCompleteHook;
use MediaWiki\Page\Hook\ArticleUndeleteHook;
use MediaWiki\Page\Hook\RollbackCompleteHook;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\Preferences\MultiTitleFilter;
use MediaWiki\Preferences\MultiUsernameFilter;
use MediaWiki\ResourceLoader as RL;
use MediaWiki\ResourceLoader\Hook\ResourceLoaderRegisterModulesHook;
use MediaWiki\ResourceLoader\ResourceLoader;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Storage\EditResult;
use MediaWiki\Storage\Hook\PageSaveCompleteHook;
use MediaWiki\User\Hook\UserClearNewTalkNotificationHook;
use MediaWiki\User\Hook\UserGetDefaultOptionsHook;
use MediaWiki\User\Hook\UserGroupsChangedHook;
use MediaWiki\User\Hook\UserSaveSettingsHook;
use MediaWiki\User\Options\Hook\LoadUserOptionsHook;
use MediaWiki\User\Options\Hook\SaveUserOptionsHook;
use MediaWiki\User\UserIdentity;
use MWEchoDbFactory;
use MWEchoNotifUser;
use MWException;
use OutputPage;
use RecentChange;
use ResourceLoaderEchoImageModule;
use Skin;
use SkinTemplate;
use SpecialPage;
use Title;
use UpdateEchoSchemaForSuppression;
use User;
use WebRequest;
use WikiMap;
use WikiPage;

class Hooks implements
	AbortTalkPageEmailNotificationHook,
	ApiMain__moduleManagerHook,
	ArticleDeleteCompleteHook,
	ArticleUndeleteHook,
	BeforePageDisplayHook,
	EmailUserCompleteHook,
	GetNewMessagesAlertHook,
	GetPreferencesHook,
	LinksUpdateCompleteHook,
	LoadUserOptionsHook,
	LocalUserCreatedHook,
	LoginFormValidErrorMessagesHook,
	OutputPageCheckLastModifiedHook,
	PageSaveCompleteHook,
	PreferencesGetIconHook,
	RecentChange_saveHook,
	ResourceLoaderRegisterModulesHook,
	RollbackCompleteHook,
	SaveUserOptionsHook,
	SendWatchlistEmailNotificationHook,
	SkinTemplateNavigation__UniversalHook,
	UserClearNewTalkNotificationHook,
	UserGetDefaultOptionsHook,
	UserGroupsChangedHook,
	UserSaveSettingsHook
{
	/**
	 * @var Config
	 */
	private $config;

	/** @var array */
	private static $revertedRevIds = [];

	public function __construct( Config $config ) {
		$this->config = $config;
	}

	/**
	 * @param array &$defaults
	 */
	public function onUserGetDefaultOptions( &$defaults ) {
		global $wgAllowHTMLEmail, $wgEchoNotificationCategories, $wgEchoEnablePush;

		if ( $wgAllowHTMLEmail ) {
			$defaults['echo-email-format'] = 'html';
		} else {
			$defaults['echo-email-format'] = 'plain-text';
		}

		$presets = [
			// Set all of the events to notify by web but not email by default
			// (won't affect events that don't email)
			'default' => [
				'email' => false,
				'web' => true,
			],
			// most settings default to web on, email off, but override these
			'system' => [
				'email' => true,
			],
			'user-rights' => [
				'email' => true,
			],
			'article-linked' => [
				'web' => false,
			],
			'mention-failure' => [
				'web' => false,
			],
			'mention-success' => [
				'web' => false,
			],
			'watchlist' => [
				'web' => false,
			],
			'minor-watchlist' => [
				'web' => false,
			],
		];
		if ( $wgEchoEnablePush ) {
			$presets['default']['push'] = true;
			$presets['article-linked']['push'] = false;
			$presets['mention-failure']['push'] = false;
			$presets['mention-success']['push'] = false;
			$presets['watchlist']['push'] = false;
			$presets['minor-watchlist']['push'] = false;
		}

		foreach ( $wgEchoNotificationCategories as $category => $categoryData ) {
			if ( !isset( $defaults["echo-subscriptions-email-{$category}"] ) ) {
				$defaults["echo-subscriptions-email-{$category}"] = $presets[$category]['email']
					?? $presets['default']['email'];
			}
			if ( !isset( $defaults["echo-subscriptions-web-{$category}"] ) ) {
				$defaults["echo-subscriptions-web-{$category}"] = $presets[$category]['web']
					?? $presets['default']['web'];
			}
			if ( $wgEchoEnablePush && !isset( $defaults["echo-subscriptions-push-{$category}"] ) ) {
				$defaults["echo-subscriptions-push-{$category}"] = $presets[$category]['push']
					// @phan-suppress-next-line PhanTypePossiblyInvalidDimOffset
					?? $presets['default']['push'];
			}
		}
	}

	/**
	 * Initialize Echo extension with necessary data, this function is invoked
	 * from $wgExtensionFunctions
	 */
	public static function initEchoExtension() {
		global $wgEchoNotifications, $wgEchoNotificationCategories, $wgEchoNotificationIcons,
			$wgEchoMentionStatusNotifications, $wgAllowArticleReminderNotification, $wgAPIModules,
			$wgEchoWatchlistNotifications, $wgEchoSeenTimeCacheType, $wgMainStash, $wgEnableEmail,
			$wgEnableUserEmail;

		// allow extensions to define their own event
		MWHooks::run( 'BeforeCreateEchoEvent',
			[ &$wgEchoNotifications, &$wgEchoNotificationCategories, &$wgEchoNotificationIcons ] );

		// Only allow mention status notifications when enabled
		if ( !$wgEchoMentionStatusNotifications ) {
			unset( $wgEchoNotificationCategories['mention-failure'] );
			unset( $wgEchoNotificationCategories['mention-success'] );
		}

		// Only allow article reminder notifications when enabled
		if ( !$wgAllowArticleReminderNotification ) {
			unset( $wgEchoNotificationCategories['article-reminder'] );
			unset( $wgAPIModules['echoarticlereminder'] );
		}

		// Only allow watchlist notifications when enabled
		if ( !$wgEchoWatchlistNotifications ) {
			unset( $wgEchoNotificationCategories['watchlist'] );
			unset( $wgEchoNotificationCategories['minor-watchlist'] );
		}

		// Only allow user email notifications when enabled
		if ( !$wgEnableEmail || !$wgEnableUserEmail ) {
			unset( $wgEchoNotificationCategories['emailuser'] );
		}

		// Default $wgEchoSeenTimeCacheType to $wgMainStash
		if ( $wgEchoSeenTimeCacheType === null ) {
			$wgEchoSeenTimeCacheType = $wgMainStash;
		}
	}

	/**
	 * Handler for ResourceLoaderRegisterModules hook
	 * @param ResourceLoader $resourceLoader
	 */
	public function onResourceLoaderRegisterModules( ResourceLoader $resourceLoader ): void {
		global $wgExtensionDirectory, $wgEchoNotificationIcons, $wgEchoSecondaryIcons;
		$resourceLoader->register( 'ext.echo.emailicons', [
			'class' => ResourceLoaderEchoImageModule::class,
			'icons' => $wgEchoNotificationIcons,
			'selector' => '.mw-echo-icon-{name}',
			'localBasePath' => $wgExtensionDirectory,
			'remoteExtPath' => 'Echo/modules'
		] );
		$resourceLoader->register( 'ext.echo.secondaryicons', [
			'class' => ResourceLoaderEchoImageModule::class,
			'icons' => $wgEchoSecondaryIcons,
			'selector' => '.mw-echo-icon-{name}',
			'localBasePath' => $wgExtensionDirectory,
			'remoteExtPath' => 'Echo/modules'
		] );
	}

	/**
	 * @param DatabaseUpdater $updater
	 */
	public static function onLoadExtensionSchemaUpdates( $updater ) {
		global $wgEchoCluster;
		if ( $wgEchoCluster ) {
			// DatabaseUpdater does not support other databases, so skip
			return;
		}

		$db = $updater->getDB();
		$dbType = $db->getType();

		$dir = dirname( __DIR__ ) . '/sql';

		$updater->addExtensionTable( 'echo_event', "$dir/$dbType/tables-generated.sql" );

		// 1.33
		// Can't use addPostDatabaseUpdateMaintenance() here because that would
		// run the migration script after dropping the fields
		$updater->addExtensionUpdate( [ 'runMaintenance', UpdateEchoSchemaForSuppression::class,
			'extensions/Echo/maintenance/updateEchoSchemaForSuppression.php' ] );
		$updater->dropExtensionField( 'echo_event', 'event_page_namespace',
			"$dir/patch-drop-echo_event-event_page_namespace.sql" );
		$updater->dropExtensionField( 'echo_event', 'event_page_title',
			"$dir/patch-drop-echo_event-event_page_title.sql" );
		if ( $dbType === 'mysql' ) {
			$updater->dropExtensionField( 'echo_notification', 'notification_bundle_base',
				"$dir/mysql/patch-drop-notification_bundle_base.sql" );
			$updater->dropExtensionField( 'echo_notification', 'notification_bundle_display_hash',
				"$dir/mysql/patch-drop-notification_bundle_display_hash.sql" );
		}
		$updater->dropExtensionIndex( 'echo_notification', 'echo_notification_user_hash_timestamp',
			"$dir/patch-drop-user-hash-timestamp-index.sql" );

		// 1.35
		$updater->addExtensionTable( 'echo_push_provider', "$dir/echo_push_provider.sql" );
		$updater->addExtensionTable( 'echo_push_subscription', "$dir/echo_push_subscription.sql" );

		// 1.36
		$updater->addExtensionTable( 'echo_push_topic', "$dir/echo_push_topic.sql" );

		// 1.39
		if ( $dbType === 'mysql' && $db->tableExists( 'echo_push_subscription', __METHOD__ ) ) {
			// Splitted into single steps to support updates from some releases as well - T322143
			$updater->renameExtensionIndex(
				'echo_push_subscription',
				'echo_push_subscription_user_id',
				'eps_user',
				"$dir/$dbType/patch-echo_push_subscription-rename-index-eps_user.sql",
				false
			);
			$updater->dropExtensionIndex(
				'echo_push_subscription',
				'echo_push_subscription_token',
				"$dir/$dbType/patch-echo_push_subscription-drop-index-eps_token.sql"
			);
			$updater->addExtensionIndex(
				'echo_push_subscription',
				'eps_token',
				"$dir/$dbType/patch-echo_push_subscription-create-index-eps_token.sql"
			);
			$updater->addExtensionField(
				'echo_push_subscription',
				'eps_topic',
				"$dir/$dbType/patch-echo_push_subscription-add-column-eps_topic.sql"
			);

			$res = $db->query( 'SHOW CREATE TABLE ' . $db->tableName( 'echo_push_subscription' ), __METHOD__ );
			$row = $res ? $res->fetchRow() : false;
			$statement = $row ? $row[1] : '';
			if ( str_contains( $statement, $db->addIdentifierQuotes( 'echo_push_subscription_ibfk_1' ) ) ) {
				$updater->modifyExtensionTable(
					'echo_push_subscription',
					"$dir/$dbType/patch-echo_push_subscription-drop-foreign-keys_1.sql"
				);
			}
			if ( str_contains( $statement, $db->addIdentifierQuotes( 'echo_push_subscription_ibfk_2' ) ) ) {
				$updater->modifyExtensionTable(
					'echo_push_subscription',
					"$dir/$dbType/patch-echo_push_subscription-drop-foreign-keys_2.sql"
				);
			}
		}
		if ( $dbType === 'sqlite' ) {
			$updater->addExtensionIndex( 'echo_push_subscription', 'eps_user',
				"$dir/$dbType/patch-cleanup-push_subscription-foreign-keys-indexes.sql" );
		}

		global $wgEchoSharedTrackingCluster, $wgEchoSharedTrackingDB;
		// Following tables should only be created if both cluster and database are false.
		// Otherwise they are not created in the place they are accesses, because
		// DatabaseUpdater does not support other databases other than main wiki schema.
		if ( $wgEchoSharedTrackingCluster === false && $wgEchoSharedTrackingDB === false ) {
			$updater->addExtensionTable( 'echo_unread_wikis', "$dir/$dbType/tables-sharedtracking-generated.sql" );

			// 1.34 (backported) - not for sqlite, the used data type supports the new length
			if ( $updater->getDB()->getType() === 'mysql' ) {
				$updater->modifyExtensionField( 'echo_unread_wikis', 'euw_wiki',
					"$dir/mysql/patch-increase-varchar-echo_unread_wikis-euw_wiki.sql" );
			}
		}
	}

	/**
	 * Handler for EchoGetBundleRule hook, which defines the bundle rule for each notification
	 *
	 * @param Event $event
	 * @param string &$bundleString Determines how the notification should be bundled, for example,
	 * talk page notification is bundled based on namespace and title, the bundle string would be
	 * 'edit-user-talk-' + namespace + title, email digest/email bundling would use this hash as
	 * a key to identify bundle-able event.  For web bundling, we bundle further based on user's
	 * visit to the overlay, we would generate a display hash based on the hash of $bundleString
	 */
	public static function onEchoGetBundleRules( $event, &$bundleString ) {
		switch ( $event->getType() ) {
			case 'edit-user-talk':
			case 'page-linked':
				$bundleString = $event->getType();
				if ( $event->getTitle() ) {
					$bundleString .= '-' . $event->getTitle()->getNamespace()
						. '-' . $event->getTitle()->getDBkey();
				}
				break;
			case 'mention-success':
			case 'mention-failure':
				$bundleString = 'mention-status-' . $event->getExtraParam( 'revid' );
				break;
			case 'watchlist-change':
			case 'minor-watchlist-change':
				$bundleString = 'watchlist-change';
				if ( $event->getTitle() ) {
					$bundleString .= '-' . $event->getTitle()->getNamespace()
						. '-' . $event->getTitle()->getDBkey();
				}
				break;
		}
	}

	/**
	 * Handler for GetPreferences hook.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/GetPreferences
	 *
	 * @param User $user User to get preferences for
	 * @param array &$preferences Preferences array
	 *
	 * @throws MWException
	 */
	public function onGetPreferences( $user, &$preferences ) {
		global $wgEchoEnableEmailBatch,
			$wgEchoNotifiers, $wgEchoNotificationCategories, $wgEchoNotifications,
			$wgAllowHTMLEmail, $wgEchoPollForUpdates,
			$wgEchoCrossWikiNotifications, $wgEchoPerUserBlacklist,
			$wgEchoWatchlistNotifications;

		$attributeManager = EchoServices::getInstance()->getAttributeManager();

		// Show email frequency options
		$freqOptions = [
			'echo-pref-email-frequency-never' => EchoEmailFrequency::NEVER,
			'echo-pref-email-frequency-immediately' => EchoEmailFrequency::IMMEDIATELY,
		];
		// Only show digest options if email batch is enabled
		if ( $wgEchoEnableEmailBatch ) {
			$freqOptions += [
				'echo-pref-email-frequency-daily' => EchoEmailFrequency::DAILY_DIGEST,
				'echo-pref-email-frequency-weekly' => EchoEmailFrequency::WEEKLY_DIGEST,
			];
		}
		$preferences['echo-email-frequency'] = [
			'type' => 'select',
			'label-message' => 'echo-pref-send-me',
			'section' => 'echo/emailsettings',
			'options-messages' => $freqOptions
		];

		$preferences['echo-dont-email-read-notifications'] = [
			'type' => 'toggle',
			'label-message' => 'echo-pref-dont-email-read-notifications',
			'section' => 'echo/emailsettings',
			'hide-if' => [ 'OR', [ '===', 'echo-email-frequency', '-1' ], [ '===', 'echo-email-frequency', '0' ] ]
		];

		// Display information about the user's currently set email address
		$prefsTitle = SpecialPage::getTitleFor( 'Preferences', false, 'mw-prefsection-echo' );
		$link = MediaWikiServices::getInstance()->getLinkRenderer()->makeLink(
			SpecialPage::getTitleFor( 'ChangeEmail' ),
			wfMessage( $user->getEmail() ? 'prefs-changeemail' : 'prefs-setemail' )->text(),
			[],
			[ 'returnto' => $prefsTitle->getFullText() ]
		);
		$permManager = MediaWikiServices::getInstance()->getPermissionManager();
		$emailAddress = $user->getEmail() && $permManager->userHasRight( $user, 'viewmyprivateinfo' )
			? htmlspecialchars( $user->getEmail() ) : '';
		if ( $permManager->userHasRight( $user, 'editmyprivateinfo' ) && self::isEmailChangeAllowed() ) {
			if ( $emailAddress === '' ) {
				$emailAddress .= $link;
			} else {
				$emailAddress .= wfMessage( 'word-separator' )->escaped()
					. wfMessage( 'parentheses' )->rawParams( $link )->escaped();
			}
		}
		$preferences['echo-emailaddress'] = [
			'type' => 'info',
			'raw' => true,
			'default' => $emailAddress,
			'label-message' => 'echo-pref-send-to',
			'section' => 'echo/emailsettings'
		];

		// Only show this option if html email is allowed, otherwise it is always plain text format
		if ( $wgAllowHTMLEmail ) {
			// Email format
			$preferences['echo-email-format'] = [
				'type' => 'select',
				'label-message' => 'echo-pref-email-format',
				'section' => 'echo/emailsettings',
				'options-messages' => [
					'echo-pref-email-format-html' => EchoEmailFormat::HTML,
					'echo-pref-email-format-plain-text' => EchoEmailFormat::PLAIN_TEXT,
				]
			];
		}

		// Sort notification categories by priority
		$categoriesAndPriorities = [];
		foreach ( $attributeManager->getInternalCategoryNames() as $category ) {
			// See if the category should be hidden from preferences.
			if ( !$attributeManager->isCategoryDisplayedInPreferences( $category ) ) {
				continue;
			}

			// See if user is eligible to receive this notification (per user group restrictions)
			if ( $attributeManager->getCategoryEligibility( $user, $category ) ) {
				$categoriesAndPriorities[$category] = $attributeManager->getCategoryPriority( $category );
			}
		}
		asort( $categoriesAndPriorities );
		$validSortedCategories = array_keys( $categoriesAndPriorities );

		// Show subscription options.  IMPORTANT: 'echo-subscriptions-email-edit-user-talk',
		// 'echo-subscriptions-email-watchlist', and 'echo-subscriptions-email-minor-watchlist' are
		// virtual options, their values are saved to existing notification options 'enotifusertalkpages',
		// 'enotifwatchlistpages', and 'enotifminoredits', see onLoadUserOptions() and onSaveUserOptions()
		// for more information on how it is handled. Doing it in this way, we can avoid keeping running
		// massive data migration script to keep these two options synced when echo is enabled on
		// new wikis or Echo is disabled and re-enabled for some reason.  We can update the name
		// if Echo is ever merged to core

		// Build the columns (notify types)
		$columns = [];
		foreach ( $wgEchoNotifiers as $notifierType => $notifierData ) {
			$formatMessage = wfMessage( 'echo-pref-' . $notifierType )->escaped();
			$columns[$formatMessage] = $notifierType;
		}

		// Build the rows (notification categories)
		$rows = [];
		$tooltips = [];
		foreach ( $validSortedCategories as $category ) {
			$categoryMessage = wfMessage( 'echo-category-title-' . $category )->numParams( 1 )->escaped();
			$rows[$categoryMessage] = $category;
			if ( isset( $wgEchoNotificationCategories[$category]['tooltip'] ) ) {
				$tooltips[$categoryMessage] = wfMessage( $wgEchoNotificationCategories[$category]['tooltip'] )->text();
			}
		}

		// Figure out the individual exceptions in the matrix and make them disabled
		$forceOptionsOff = $forceOptionsOn = [];
		foreach ( $wgEchoNotifiers as $notifierType => $notifierData ) {
			foreach ( $validSortedCategories as $category ) {
				// See if this notify type is non-dismissable
				if ( !$attributeManager->isNotifyTypeDismissableForCategory( $category, $notifierType ) ) {
					$forceOptionsOn[] = "$notifierType-$category";
				}

				if ( !$attributeManager->isNotifyTypeAvailableForCategory( $category, $notifierType ) ) {
					$forceOptionsOff[] = "$notifierType-$category";
				}
			}
		}

		$invalid = array_intersect( $forceOptionsOff, $forceOptionsOn );
		if ( $invalid ) {
			throw new MWException( sprintf(
				'The following notifications are both forced and removed: %s',
				implode( ', ', $invalid )
			) );
		}
		$preferences['echo-subscriptions'] = [
			'class' => HTMLCheckMatrix::class,
			'section' => 'echo/echosubscriptions',
			'rows' => $rows,
			'columns' => $columns,
			'prefix' => 'echo-subscriptions-',
			'force-options-off' => $forceOptionsOff,
			'force-options-on' => $forceOptionsOn,
			'tooltips' => $tooltips,
		];

		if ( $wgEchoCrossWikiNotifications ) {
			$preferences['echo-cross-wiki-notifications'] = [
				'type' => 'toggle',
				'label-message' => 'echo-pref-cross-wiki-notifications',
				'section' => 'echo/echocrosswiki'
			];
		}

		if ( $wgEchoPollForUpdates ) {
			$preferences['echo-show-poll-updates'] = [
				'type' => 'toggle',
				'label-message' => 'echo-pref-show-poll-updates',
				'help-message' => 'echo-pref-show-poll-updates-help',
				'section' => 'echo/echopollupdates'
			];
		}

		// If we're using Echo to handle user talk page post or watchlist notifications,
		// hide the old (non-Echo) preferences for them. If Echo is moved to core
		// we'll want to remove the old user options entirely. For now, though,
		// we need to keep it defined in case Echo is ever uninstalled.
		// Otherwise, that preference could be lost entirely. This hiding logic
		// is not abstracted since there are only three preferences in core
		// that are potentially made obsolete by Echo.
		if ( isset( $wgEchoNotifications['edit-user-talk'] ) ) {
			$preferences['enotifusertalkpages']['type'] = 'hidden';
			unset( $preferences['enotifusertalkpages']['section'] );
		}
		if ( $wgEchoWatchlistNotifications && isset( $wgEchoNotifications['watchlist-change'] ) ) {
			$preferences['enotifwatchlistpages']['type'] = 'hidden';
			unset( $preferences['enotifusertalkpages']['section'] );
			$preferences['enotifminoredits']['type'] = 'hidden';
			unset( $preferences['enotifminoredits']['section'] );
		}

		if ( $wgEchoPerUserBlacklist ) {
			$preferences['echo-notifications-blacklist'] = [
				'type' => 'usersmultiselect',
				'label-message' => 'echo-pref-notifications-blacklist',
				'section' => 'echo/blocknotificationslist',
				'filter' => MultiUsernameFilter::class,
			];
			$preferences['echo-notifications-page-linked-title-muted-list'] = [
				'type' => 'titlesmultiselect',
				'label-message' => 'echo-pref-notifications-page-linked-title-muted-list',
				'section' => 'echo/mutedpageslist',
				'showMissing' => false,
				'excludeDynamicNamespaces' => true,
				'filter' => new MultiTitleFilter()
			];
		}
	}

	/**
	 * Add icon for Special:Preferences mobile layout
	 *
	 * @param array &$iconNames Array of icon names for their respective sections.
	 */
	public function onPreferencesGetIcon( &$iconNames ) {
		$iconNames[ 'echo' ] = 'bell';
	}

	/**
	 * Test whether email address change is supposed to be allowed
	 * @return bool
	 */
	private static function isEmailChangeAllowed() {
		return MediaWikiServices::getInstance()->getAuthManager()
			->allowsPropertyChange( 'emailaddress' );
	}

	/**
	 * Handler for PageSaveComplete hook
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/PageSaveComplete
	 *
	 * @param WikiPage $wikiPage modified WikiPage
	 * @param UserIdentity $userIdentity User who edited
	 * @param string $summary Edit summary
	 * @param int $flags Edit flags
	 * @param RevisionRecord $revisionRecord RevisionRecord for the revision that was created
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
		if ( $editResult->isNullEdit() ) {
			return;
		}

		$title = $wikiPage->getTitle();
		$isRevert = $editResult->getRevertMethod() === EditResult::REVERT_UNDO ||
			$editResult->getRevertMethod() === EditResult::REVERT_ROLLBACK;

		// Save the revert status for the LinksUpdateComplete hook
		if ( $isRevert ) {
			self::$revertedRevIds[$revisionRecord->getId()] = true;
		}

		// Try to do this after the HTTP response
		DeferredUpdates::addCallableUpdate( static function () use ( $revisionRecord, $isRevert ) {
			EchoDiscussionParser::generateEventsForRevision( $revisionRecord, $isRevert );
		} );

		// If the user is not an IP and this is not a null edit,
		// test for them reaching a congratulatory threshold
		$thresholds = [ 1, 10, 100, 1000, 10000, 100000, 1000000, 10000000 ];
		if ( $userIdentity->isRegistered() ) {
			$thresholdCount = self::getEditCount( $userIdentity );
			if ( in_array( $thresholdCount, $thresholds ) ) {
				DeferredUpdates::addCallableUpdate( static function () use (
					$revisionRecord, $userIdentity, $title, $thresholdCount ) {
					$notificationMapper = new NotificationMapper();
					$notifications = $notificationMapper->fetchByUser( $userIdentity, 10, null, [ 'thank-you-edit' ] );
					/** @var Notification $notification */
					foreach ( $notifications as $notification ) {
						if ( $notification->getEvent()->getExtraParam( 'editCount' ) === $thresholdCount ) {
							LoggerFactory::getInstance( 'Echo' )->debug(
								'{user} (id: {id}) has already been thanked for their {count} edit',
								[
									'user' => $userIdentity->getName(),
									'id' => $userIdentity->getId(),
									'count' => $thresholdCount,
								]
							);
							return;
						}
					}

					Event::create( [
						'type' => 'thank-you-edit',
						'title' => $title,
						'agent' => $userIdentity,
						// Edit threshold notifications are sent to the agent
						'extra' => [
							'editCount' => $thresholdCount,
							'revid' => $revisionRecord->getId(),
						]
					] );
				} );
			}
		}

		// Handle the case of someone undoing an edit, either through the
		// 'undo' link in the article history or via the API.
		// Reverts through the 'rollback' link (EditResult::REVERT_ROLLBACK)
		// are handled in ::onRollbackComplete().
		if ( $editResult->getRevertMethod() === EditResult::REVERT_UNDO ) {
			$store = MediaWikiServices::getInstance()->getRevisionStore();
			$undidRevId = $editResult->getUndidRevId();
			$undidRevision = $store->getRevisionById( $undidRevId );
			if (
				$undidRevision &&
				Title::newFromLinkTarget( $undidRevision->getPageAsLinkTarget() )->equals( $title )
			) {
				$revertedUser = $undidRevision->getUser();
				// No notifications for anonymous users
				if ( $revertedUser && $revertedUser->getId() ) {
					Event::create( [
						'type' => 'reverted',
						'title' => $title,
						'extra' => [
							'revid' => $revisionRecord->getId(),
							'reverted-user-id' => $revertedUser->getId(),
							'reverted-revision-id' => $undidRevId,
							'method' => 'undo',
							'summary' => $summary,
						],
						'agent' => $userIdentity,
					] );
				}
			}
		}
	}

	/**
	 * @param UserIdentity $user
	 * @return int
	 */
	private static function getEditCount( UserIdentity $user ) {
		$editCount = MediaWikiServices::getInstance()->getUserEditTracker()
			->getUserEditCount( $user ) ?: 0;
		// When this code runs from a maintenance script or unit tests
		// the deferred update incrementing edit count runs right away
		// so the edit count is right. Otherwise it lags by one.
		if ( wfIsCLI() ) {
			return $editCount;
		}
		return $editCount + 1;
	}

	/**
	 * Handler for EchoAbortEmailNotification hook
	 * @param User $user
	 * @param Event $event
	 * @return bool true - send email, false - do not send email
	 */
	public static function onEchoAbortEmailNotification( $user, $event ) {
		global $wgEchoWatchlistEmailOncePerPage;
		$type = $event->getType();
		if ( $type === 'edit-user-talk' ) {
			$extra = $event->getExtra();
			if ( !empty( $extra['minoredit'] ) ) {
				global $wgEnotifMinorEdits;
				$userOptionsLookup = MediaWikiServices::getInstance()->getUserOptionsLookup();
				if ( !$wgEnotifMinorEdits || !$userOptionsLookup->getOption( $user, 'enotifminoredits' ) ) {
					// Do not send talk page notification email
					return false;
				}
			}
		// Mimic core code of only sending watchlist notification emails once per page
		} elseif ( $type === "watchlist-change" || $type === "minor-watchlist-change" ) {
			if ( !$wgEchoWatchlistEmailOncePerPage ) {
				// Don't care about rate limiting
				return true;
			}
			$store = MediaWikiServices::getInstance()->getWatchedItemStore();
			$ts = $store->getWatchedItem( $user, $event->getTitle() )->getNotificationTimestamp();
			// if (ts != null) is not sufficient because, if $wgEchoUseJobQueue is set,
			// wl_notificationtimestamp will have already been set for the new edit
			// by the time this code runs.
			if ( $ts !== null && $ts !== $event->getExtraParam( "timestamp" ) ) {
				// User has already seen an email for this page before
				return false;
			}
		}
		// Proceed to send notification email
		return true;
	}

	/**
	 * Get overrides for new users.  This allows changes that only apply going forward,
	 * without affecting existing users.
	 *
	 * @return bool[] Associative array mapping key to bool for whether it should be enabled
	 */
	public static function getNewUserPreferenceOverrides() {
		return [
			'echo-subscriptions-web-reverted' => false,
			'echo-subscriptions-email-reverted' => false,
			'echo-subscriptions-web-article-linked' => true,
			'echo-subscriptions-email-mention' => true,
			'echo-subscriptions-email-article-linked' => true,
		];
	}

	/**
	 * Handler for LocalUserCreated hook.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/LocalUserCreated
	 * @param User $user User object that was created.
	 * @param bool $autocreated True when account was auto-created
	 */
	public function onLocalUserCreated( $user, $autocreated ) {
		if ( !$autocreated ) {
			$overrides = self::getNewUserPreferenceOverrides();
			$userOptionsManager = MediaWikiServices::getInstance()->getUserOptionsManager();
			foreach ( $overrides as $prefKey => $value ) {
				$userOptionsManager->setOption( $user, $prefKey, $value );
			}
			Event::create( [
				'type' => 'welcome',
				'agent' => $user,
			] );
		}

		$seenTime = EchoSeenTime::newFromUser( $user );

		// Set seen time to UNIX epoch, so initially all notifications are unseen.
		$seenTime->setTime( wfTimestamp( TS_MW, 1 ), 'all' );
	}

	/**
	 * Handler for UserGroupsChanged hook.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/UserGroupsChanged
	 *
	 * @param UserIdentity $userId user that was changed
	 * @param string[] $add strings corresponding to groups added
	 * @param string[] $remove strings corresponding to groups removed
	 * @param User|bool $performer
	 * @param string|bool $reason Reason given by the user changing the rights
	 * @param array $oldUGMs
	 * @param array $newUGMs
	 */
	public function onUserGroupsChanged( $userId, $add, $remove, $performer,
		$reason, $oldUGMs, $newUGMs ) {
		if ( !$performer ) {
			// TODO: Implement support for autopromotion
			return;
		}

		if ( $userId->getWikiId() !== WikiAwareEntity::LOCAL ) {
			// TODO: Support external users
			return;
		}

		$user = MediaWikiServices::getInstance()->getUserFactory()->newFromUserIdentity( $userId );

		if ( $user->equals( $performer ) ) {
			// Don't notify for self changes
			return;
		}

		// If any old groups are in $add, those groups are having their expiry
		// changed, not actually being added
		$expiryChanged = [];
		$reallyAdded = [];
		foreach ( $add as $group ) {
			if ( isset( $oldUGMs[$group] ) ) {
				$expiryChanged[] = $group;
			} else {
				$reallyAdded[] = $group;
			}
		}

		if ( $expiryChanged ) {
			// use a separate notification for these, so the notification text doesn't
			// get too long
			Event::create(
				[
					'type' => 'user-rights',
					'extra' => [
						'user' => $user->getId(),
						'expiry-changed' => $expiryChanged,
						'reason' => $reason,
					],
					'agent' => $performer,
				]
			);
		}

		if ( $reallyAdded || $remove ) {
			Event::create(
				[
					'type' => 'user-rights',
					'extra' => [
						'user' => $user->getId(),
						'add' => $reallyAdded,
						'remove' => $remove,
						'reason' => $reason,
					],
					'agent' => $performer,
				]
			);
		}
	}

	/**
	 * Handler for LinksUpdateComplete hook.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/LinksUpdateComplete
	 * @param LinksUpdate $linksUpdate
	 * @param mixed $ticket
	 */
	public function onLinksUpdateComplete( $linksUpdate, $ticket ) {
		// Rollback or undo should not trigger link notification
		if ( $linksUpdate->getRevisionRecord() ) {
			$revId = $linksUpdate->getRevisionRecord()->getId();
			if ( isset( self::$revertedRevIds[$revId] ) ) {
				return;
			}
		}

		$namespaceInfo = MediaWikiServices::getInstance()->getNamespaceInfo();

		// Handle only
		// 1. content namespace pages &&
		// 2. non-transcluding pages &&
		// 3. non-redirect pages
		if ( !$namespaceInfo->isContent( $linksUpdate->getTitle()->getNamespace() )
			|| !$linksUpdate->isRecursive() || $linksUpdate->getTitle()->isRedirect()
		) {
			return;
		}

		$revRecord = $linksUpdate->getRevisionRecord();
		$revid = $revRecord ? $revRecord->getId() : null;
		$user = $revRecord ? $revRecord->getUser() : null;

		// link notification is boundless as you can include infinite number of links in a page
		// db insert is expensive, limit it to a reasonable amount, we can increase this limit
		// once the storage is on Redis
		$max = 10;
		// Only create notifications for links to content namespace pages
		// @Todo - use one big insert instead of individual insert inside foreach loop
		foreach ( $linksUpdate->getAddedLinks() as $title ) {
			if ( $namespaceInfo->isContent( $title->getNamespace() ) ) {
				if ( $title->isRedirect() ) {
					continue;
				}

				$linkFromPageId = $linksUpdate->getTitle()->getArticleID();
				Event::create( [
					'type' => 'page-linked',
					'title' => $title,
					'agent' => $user,
					'extra' => [
						'target-page' => $linkFromPageId,
						'link-from-page-id' => $linkFromPageId,
						'revid' => $revid,
					]
				] );
				$max--;
			}
			if ( $max < 0 ) {
				break;
			}
		}
	}

	/**
	 * Handler for BeforePageDisplay hook.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/BeforePageDisplay
	 * @param OutputPage $out
	 * @param Skin $skin Skin being used.
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		$user = $out->getUser();

		if ( !$user->isRegistered() ) {
			if ( ExtensionRegistry::getInstance()->isLoaded( 'CentralAuth' ) ) {
				$out->addModules( [ 'ext.echo.centralauth' ] );
			}
			return;
		}

		if ( self::shouldDisplayTalkAlert( $user, $out->getTitle() ) ) {
			// Load the module for the Orange alert
			$out->addModuleStyles( 'ext.echo.styles.alert' );
		}

		// Load the module for the Notifications flyout
		$out->addModules( [ 'ext.echo.init' ] );
		// Load the styles for the Notifications badge
		$out->addModuleStyles( [
			'ext.echo.styles.badge',
			'oojs-ui.styles.icons-alerts'
		] );
	}

	private static function processMarkAsRead( User $user, WebRequest $request, Title $title ) {
		global $wgEchoCrossWikiNotifications;
		$subtractions = [
			EchoAttributeManager::ALERT => 0,
			EchoAttributeManager::MESSAGE => 0
		];

		// Attempt to mark a notification as read when visiting a page
		$eventIds = [];
		if ( $title->getArticleID() ) {
			$eventMapper = new EventMapper();
			$events = $eventMapper->fetchUnreadByUserAndPage( $user, $title->getArticleID() );

			foreach ( $events as $event ) {
				$subtractions[$event->getSection()]++;
				$eventIds[] = $event->getId();
			}
		}

		// Attempt to mark as read the event IDs in the ?markasread= parameter, if present
		$markAsReadIds = array_filter( explode( '|', $request->getText( 'markasread' ) ) );
		$markAsReadWiki = $request->getText( 'markasreadwiki', WikiMap::getCurrentWikiId() );
		$markAsReadLocal = !$wgEchoCrossWikiNotifications || $markAsReadWiki === WikiMap::getCurrentWikiId();
		if ( $markAsReadIds ) {
			if ( $markAsReadLocal ) {
				// gather the IDs that we didn't already find with target_pages
				$eventsToMarkAsRead = [];
				foreach ( $markAsReadIds as $markAsReadId ) {
					$markAsReadId = intval( $markAsReadId );
					if ( $markAsReadId !== 0 && !in_array( $markAsReadId, $eventIds ) ) {
						$eventsToMarkAsRead[] = $markAsReadId;
					}
				}

				if ( $eventsToMarkAsRead ) {
					// fetch the notifications to adjust the counters
					$notifMapper = new NotificationMapper();
					$notifs = $notifMapper->fetchByUserEvents( $user, $eventsToMarkAsRead );

					foreach ( $notifs as $notif ) {
						if ( !$notif->getReadTimestamp() ) {
							$subtractions[$notif->getEvent()->getSection()]++;
							$eventIds[] = intval( $notif->getEvent()->getId() );
						}
					}
				}
			} else {
				$markAsReadIds = array_map( 'intval', $markAsReadIds );
				// Look up the notifications on the foreign wiki
				$notifUser = MWEchoNotifUser::newFromUser( $user );
				$notifInfo = $notifUser->getForeignNotificationInfo( $markAsReadIds, $markAsReadWiki, $request );
				foreach ( $notifInfo as $id => $info ) {
					$subtractions[$info['section']]++;
				}

				// Schedule a deferred update to mark these notifications as read on the foreign wiki
				DeferredUpdates::addCallableUpdate(
					static function () use ( $user, $markAsReadIds, $markAsReadWiki, $request ) {
						$notifUser = MWEchoNotifUser::newFromUser( $user );
						$notifUser->markReadForeign( $markAsReadIds, $markAsReadWiki, $request );
					}
				);
			}
		}

		// Schedule a deferred update to mark local target_page and ?markasread= notifications as read
		if ( $eventIds ) {
			DeferredUpdates::addCallableUpdate( static function () use ( $user, $eventIds ) {
				$notifUser = MWEchoNotifUser::newFromUser( $user );
				$notifUser->markRead( $eventIds );
			} );
		}

		return $subtractions;
	}

	/**
	 * Determine if a talk page alert should be displayed.
	 * We need to check:
	 * - User actually has new messages
	 * - User is not viewing their user talk page, as user_newtalk will not have been cleared yet.
	 *   (bug T107655).
	 *
	 * @param User $user
	 * @param Title $title
	 * @return bool
	 */
	private static function shouldDisplayTalkAlert( $user, $title ) {
		$userHasNewMessages = MediaWikiServices::getInstance()
			->getTalkPageNotificationManager()
			->userHasNewMessages( $user );

		return $userHasNewMessages && !$user->getTalkPage()->equals( $title );
	}

	/**
	 * Handler for SkinTemplateNavigation::Universal hook.
	 * Adds "Notifications" items to the notifications content navigation.
	 * SkinTemplate automatically merges these into the personal tools for older skins.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/SkinTemplateNavigation::Universal
	 * @param SkinTemplate $skinTemplate
	 * @param array &$links Array of URLs to append to.
	 */
	public function onSkinTemplateNavigation__Universal( $skinTemplate, &$links ): void {
		$user = $skinTemplate->getUser();
		if ( !$user->isRegistered() ) {
			return;
		}

		$title = $skinTemplate->getTitle();
		$out = $skinTemplate->getOutput();

		$subtractions = self::processMarkAsRead( $user, $out->getRequest(), $title );

		// Add a "My notifications" item to personal URLs
		$notifUser = MWEchoNotifUser::newFromUser( $user );
		$msgCount = $notifUser->getMessageCount() - $subtractions[EchoAttributeManager::MESSAGE];
		$alertCount = $notifUser->getAlertCount() - $subtractions[EchoAttributeManager::ALERT];
		// But make sure we never show a negative number (T130853)
		$msgCount = max( 0, $msgCount );
		$alertCount = max( 0, $alertCount );

		$msgNotificationTimestamp = $notifUser->getLastUnreadMessageTime();
		$alertNotificationTimestamp = $notifUser->getLastUnreadAlertTime();

		$seenTime = EchoSeenTime::newFromUser( $user );
		if ( $title->isSpecial( 'Notifications' ) ) {
			// If this is the Special:Notifications page, seenTime to now
			$seenTime->setTime( wfTimestamp( TS_MW ), EchoAttributeManager::ALL );
		}
		$seenAlertTime = $seenTime->getTime( 'alert', TS_ISO_8601 );
		$seenMsgTime = $seenTime->getTime( 'message', TS_ISO_8601 );

		$out->addJsConfigVars( 'wgEchoSeenTime', [
			'alert' => $seenAlertTime,
			'notice' => $seenMsgTime,
		] );

		$msgFormattedCount = NotificationController::formatNotificationCount( $msgCount );
		$alertFormattedCount = NotificationController::formatNotificationCount( $alertCount );

		$url = SpecialPage::getTitleFor( 'Notifications' )->getLocalURL();

		$skinName = strtolower( $skinTemplate->getSkinName() );
		$isMinervaSkin = $skinName === 'minerva';
		// HACK: inverted icons only work in the "MediaWiki" OOUI theme
		// Avoid flashes in skins that don't use it (T111821)
		$out::setupOOUI( $skinName, $out->getLanguage()->getDir() );
		$bellIconClass = $isMinervaSkin ? 'oo-ui-icon-bellOutline' : 'oo-ui-icon-bell';

		$msgLinkClasses = [ "mw-echo-notifications-badge", "mw-echo-notification-badge-nojs","oo-ui-icon-tray" ];
		$alertLinkClasses = [ "mw-echo-notifications-badge", "mw-echo-notification-badge-nojs", $bellIconClass ];

		$hasUnseen = false;
		if (
			// no unread notifications
			$msgCount !== 0 &&
			// should already always be false if count === 0
			$msgNotificationTimestamp !== false &&
			// there are no unseen notifications
			( $seenMsgTime === null ||
				$seenMsgTime < $msgNotificationTimestamp->getTimestamp( TS_ISO_8601 ) )
		) {
			$msgLinkClasses[] = 'mw-echo-unseen-notifications';
			$hasUnseen = true;
		} elseif ( $msgCount === 0 ) {
			$msgLinkClasses[] = 'mw-echo-notifications-badge-all-read';
		}

		if ( $msgCount > MWEchoNotifUser::MAX_BADGE_COUNT ) {
			$msgLinkClasses[] = 'mw-echo-notifications-badge-long-label';
		}

		if (
			// no unread notifications
			$alertCount !== 0 &&
			// should already always be false if count === 0
			$alertNotificationTimestamp !== false &&
			// all notifications have already been seen
			( $seenAlertTime === null ||
				$seenAlertTime < $alertNotificationTimestamp->getTimestamp( TS_ISO_8601 ) )
		) {
			$alertLinkClasses[] = 'mw-echo-unseen-notifications';
			$hasUnseen = true;
		} elseif ( $alertCount === 0 ) {
			$alertLinkClasses[] = 'mw-echo-notifications-badge-all-read';
		}

		if ( $alertCount > MWEchoNotifUser::MAX_BADGE_COUNT ) {
			$alertLinkClasses[] = 'mw-echo-notifications-badge-long-label';
		}

		$mytalk = $links['user-menu']['mytalk'] ?? false;
		if (
			$mytalk &&
			self::shouldDisplayTalkAlert( $user, $title ) &&
			MediaWikiServices::getInstance()
				->getHookContainer()->run( 'BeforeDisplayOrangeAlert', [ $user, $title ] )
		) {
			// Create new talk alert inheriting from the talk link data.
			$links['notifications']['talk-alert'] = array_merge(
				$links['user-menu']['mytalk'],
				[
					// Hardcode id, which is needed to dismiss the talk alert notification
					'id' => 'pt-talk-alert',
					// If Vector hook ran anicon will have  been copied to the link class.
					// We must reset it.
					'link-class' => [],
					'text' => $skinTemplate->msg( 'echo-new-messages' )->text(),
					'class' => [ 'mw-echo-alert' ],
					// unset icon
					'icon' => '',
				]
			);

			// If there's exactly one new user talk message, then link directly to it from the alert.
			$notificationMapper = new NotificationMapper();
			$notifications = $notificationMapper->fetchUnreadByUser( $user, 2, null, [ 'edit-user-talk' ] );
			if ( count( $notifications ) === 1 ) {
				$presModel = EchoEventPresentationModel::factory(
					current( $notifications )->getEvent(),
					$out->getLanguage(),
					$user
				);
				$links['notifications']['talk-alert']['href'] = $presModel->getPrimaryLink()['url'];
			}
		}

		$links['notifications']['notifications-alert'] = [
			'href' => $url,
			'text' => $skinTemplate->msg( 'echo-notification-alert', $alertCount )->text(),
			'active' => ( $url == $title->getLocalURL() ),
			'link-class' => $alertLinkClasses,
			'icon' => 'bell',
			'data' => [
				'event-name' => 'ui.notifications',
				'counter-num' => $alertCount,
				'counter-text' => $alertFormattedCount,
			],
			// This item used to be part of personal tools, and much CSS relies on it using this id.
			'id' => 'pt-notifications-alert',
		];

		$links['notifications']['notifications-notice'] = [
			'href' => $url,
			'text' => $skinTemplate->msg( 'echo-notification-notice', $msgCount )->text(),
			'active' => ( $url == $title->getLocalURL() ),
			'link-class' => $msgLinkClasses,
			'icon' => 'tray',
			'data' => [
				'counter-num' => $msgCount,
				'counter-text' => $msgFormattedCount,
			],
			// This item used to be part of personal tools, and much CSS relies on it using this id.
			'id' => 'pt-notifications-notice',
		];

		if ( $hasUnseen ) {
			// Record that the user is going to see an indicator that they have unseen notifications
			// This is part of tracking how likely users are to click a badge with unseen notifications.
			// The other part is the 'echo.unseen.click' counter, see ext.echo.init.js.
			MediaWikiServices::getInstance()->getStatsdDataFactory()->increment( 'echo.unseen' );
		}
	}

	/**
	 * Handler for AbortTalkPageEmailNotification hook.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/AbortTalkPageEmailNotification
	 * @param User $targetUser
	 * @param Title $title
	 * @return bool
	 */
	public function onAbortTalkPageEmailNotification( $targetUser, $title ) {
		global $wgEchoNotifications;

		// Send legacy talk page email notification if
		// 1. echo is disabled for them or
		// 2. echo talk page notification is disabled
		if ( !isset( $wgEchoNotifications['edit-user-talk'] ) ) {
			// Legacy talk page email notification
			return true;
		}

		// Echo talk page email notification
		return false;
	}

	/**
	 * Handler for AbortWatchlistEmailNotification hook.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/AbortWatchlistEmailNotification
	 * @param User $targetUser
	 * @param Title $title
	 * @param EmailNotification $emailNotification The email notification object that sends non-echo notifications
	 * @return bool
	 */
	public function onSendWatchlistEmailNotification( $targetUser, $title, $emailNotification ) {
		global $wgEchoNotifications, $wgEchoWatchlistNotifications;
		if ( $wgEchoWatchlistNotifications && isset( $wgEchoNotifications["watchlist-change"] ) ) {
			// Let echo handle watchlist notifications entirely
			return false;
		}
		// If a user is watching his/her own talk page, do not send talk page watchlist
		// email notification if the user is receiving Echo talk page notification
		if ( $title->isTalkPage() && $targetUser->getTalkPage()->equals( $title ) ) {
			$attributeManager = EchoServices::getInstance()->getAttributeManager();
			$events = $attributeManager->getUserEnabledEvents( $targetUser, 'email' );
			if ( in_array( 'edit-user-talk', $events ) ) {
				// Do not send watchlist email notification, the user will receive an Echo notification
				return false;
			}
		}

		// Proceed to send watchlist email notification
		return true;
	}

	/**
	 * @param array &$modifiedTimes
	 * @param OutputPage $out
	 */
	public function onOutputPageCheckLastModified( &$modifiedTimes, $out ) {
		$req = $out->getRequest();
		if ( $req->getRawVal( 'action' ) === 'raw' || $req->getRawVal( 'action' ) === 'render' ) {
			// Optimisation: Avoid expensive EchoSeenTime compute on non-skin responses (T279213)
			return;
		}

		$user = $out->getUser();
		if ( $user->isRegistered() ) {
			$notifUser = MWEchoNotifUser::newFromUser( $user );
			$lastUpdate = $notifUser->getGlobalUpdateTime();
			if ( $lastUpdate !== false ) {
				$modifiedTimes['notifications-global'] = $lastUpdate;
			}

			$modifiedTimes['notifications-seen-alert'] = EchoSeenTime::newFromUser( $user )->getTime( 'alert' );
			$modifiedTimes['notifications-seen-message'] = EchoSeenTime::newFromUser( $user )->getTime( 'message' );
		}
	}

	/**
	 * Handler for GetNewMessagesAlert hook.
	 * We're using the GetNewMessagesAlert hook instead of the
	 * ArticleEditUpdateNewTalk hook since we still want the user_newtalk data
	 * to be updated and available to client-side tools and the API.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/GetNewMessagesAlert
	 * @param string &$newMessagesAlert An alert that the user has new messages
	 *     or an empty string if the user does not (empty by default)
	 * @param array $newtalks This will be empty if the user has no new messages
	 *     or an Array containing links and revisions if there are new messages
	 * @param User $user The user who is loading the page
	 * @param OutputPage $out
	 * @return bool Should return false to prevent the new messages alert (OBOD)
	 *     or true to allow the new messages alert
	 */
	public function onGetNewMessagesAlert( &$newMessagesAlert, $newtalks, $user, $out ) {
		global $wgEchoNotifications;

		// If the user has the notifications flyout turned on and is receiving
		// notifications for talk page messages, disable the new messages alert.
		if ( $user->isRegistered()
			&& isset( $wgEchoNotifications['edit-user-talk'] )
			&& MWHooks::run( 'EchoCanAbortNewMessagesAlert' )
		) {
			// hide new messages alert
			return false;
		} else {
			// show new messages alert
			return true;
		}
	}

	/**
	 * Handler for RollbackComplete hook.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/RollbackComplete
	 *
	 * @param WikiPage $wikiPage The article that was edited
	 * @param UserIdentity $agent The user who did the rollback
	 * @param RevisionRecord $newRevision The revision the page was reverted back to
	 * @param RevisionRecord $oldRevision The revision of the top edit that was reverted
	 */
	public function onRollbackComplete(
		$wikiPage,
		$agent,
		$newRevision,
		$oldRevision
	) {
		$revertedUser = $oldRevision->getUser();
		$latestRevision = $wikiPage->getRevisionRecord();

		if (
			$revertedUser &&
			// No notifications for anonymous users
			$revertedUser->isRegistered() &&
			// No notifications for null rollbacks
			!$oldRevision->hasSameContent( $newRevision )
		) {
			Event::create( [
				'type' => 'reverted',
				'title' => $wikiPage->getTitle(),
				'extra' => [
					'revid' => $latestRevision->getId(),
					'reverted-user-id' => $revertedUser->getId(),
					'reverted-revision-id' => $oldRevision->getId(),
					'method' => 'rollback',
				],
				'agent' => $agent,
			] );
		}
	}

	/**
	 * Handler for UserSaveSettings hook.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/UserSaveSettings
	 * @param User $user whose settings were saved
	 */
	public function onUserSaveSettings( $user ) {
		// Extensions like AbuseFilter might create an account, but
		// the tables we need might not exist. Bug 57335
		if ( !defined( 'MW_UPDATER' ) ) {
			// Reset the notification count since it may have changed due to user
			// option changes. This covers both explicit changes in the preferences
			// and changes made through the options API (since both call this hook).
			DeferredUpdates::addCallableUpdate( static function () use ( $user ) {
				if ( !$user->isRegistered() ) {
					// It's possible the user account was deleted before the deferred
					// update runs (T318081)
					return;
				}
				MWEchoNotifUser::newFromUser( $user )->resetNotificationCount();
			} );
		}
	}

	/**
	 * Some of Echo's subscription user preferences are mapped to existing user preferences defined in
	 * core MediaWiki. This returns the map of Echo preference names to core preference names.
	 *
	 * @return array
	 */
	public static function getVirtualUserOptions() {
		global $wgEchoWatchlistNotifications;
		$options = [];
		$options['echo-subscriptions-email-edit-user-talk'] = 'enotifusertalkpages';
		if ( $wgEchoWatchlistNotifications ) {
			$options['echo-subscriptions-email-watchlist'] = 'enotifwatchlistpages';
			$options['echo-subscriptions-email-minor-watchlist'] = 'enotifminoredits';
		}
		return $options;
	}

	/**
	 * Handler for LoadUserOptions hook.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/LoadUserOptions
	 * @param UserIdentity $user User whose options were loaded
	 * @param array &$options Options can be modified
	 */
	public function onLoadUserOptions( UserIdentity $user, &$options ): void {
		foreach ( self::getVirtualUserOptions() as $echoPref => $mwPref ) {
			// Use the existing core option's value for the Echo option
			if ( isset( $options[ $mwPref ] ) ) {
				$options[ $echoPref ] = $options[ $mwPref ];
			}
		}
	}

	/**
	 * Handler for SaveUserOptions hook.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/SaveUserOptions
	 * @param UserIdentity $user User whose options are being saved
	 * @param array &$modifiedOptions Options can be modified
	 * @param array $originalOptions
	 */
	public function onSaveUserOptions( UserIdentity $user, array &$modifiedOptions, array $originalOptions ) {
		foreach ( self::getVirtualUserOptions() as $echoPref => $mwPref ) {
			// Save virtual option values in corresponding real option values
			if ( isset( $modifiedOptions[ $echoPref ] ) ) {
				$modifiedOptions[ $mwPref ] = $modifiedOptions[ $echoPref ];
				unset( $modifiedOptions[ $echoPref ] );
			}
		}
	}

	/**
	 * Convert all values in an array to integers and filter out zeroes.
	 *
	 * @param array $numbers
	 *
	 * @return int[]
	 */
	protected static function mapToInt( array $numbers ) {
		$data = [];

		foreach ( $numbers as $value ) {
			$int = intval( $value );
			if ( $int === 0 ) {
				continue;
			}
			$data[] = $int;
		}

		return $data;
	}

	/**
	 * Handler for UserClearNewTalkNotification hook.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/UserClearNewTalkNotification
	 * @param UserIdentity $user User whose talk page notification should be marked as read
	 * @param int $oldid
	 */
	public function onUserClearNewTalkNotification( $user, $oldid ) {
		if ( $user->isRegistered() ) {
			DeferredUpdates::addCallableUpdate( static function () use ( $user ) {
				MWEchoNotifUser::newFromUser( $user )->clearUserTalkNotifications();
			} );
		}
	}

	/**
	 * Handler for EmailUserComplete hook.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/EmailUserComplete
	 * @param MailAddress $address Adress of receiving user
	 * @param MailAddress $from Adress of sending user
	 * @param string $subject Subject of the mail
	 * @param string $text Text of the mail
	 */
	public function onEmailUserComplete( $address, $from, $subject, $text ) {
		if ( $from->name === $address->name ) {
			// nothing to notify
			return;
		}
		$userTo = User::newFromName( $address->name );
		$userFrom = User::newFromName( $from->name );

		$autoSubject = wfMessage( 'defemailsubject', $from->name )->inContentLanguage()->text();
		if ( $subject === $autoSubject ) {
			$autoFooter = "\n\n-- \n" . wfMessage( 'emailuserfooter', $from->name, $address->name )
				->inContentLanguage()->text();
			$textWithoutFooter = preg_replace( '/' . preg_quote( $autoFooter, '/' ) . '$/', '', $text );
			$preview = MediaWikiServices::getInstance()->getContentLanguage()
				->truncateForVisual( $textWithoutFooter, 125 );
		} else {
			$preview = $subject;
		}

		Event::create( [
			'type' => 'emailuser',
			'extra' => [
				'to-user-id' => $userTo->getId(),
				'preview' => $preview,
			],
			'agent' => $userFrom,
		] );
	}

	/**
	 * For integration with the UserMerge extension.
	 *
	 * @param array &$updateFields
	 */
	public static function onUserMergeAccountFields( &$updateFields ) {
		// array( tableName, idField, textField )
		$dbw = MWEchoDbFactory::newFromDefault()->getEchoDb( DB_PRIMARY );
		$updateFields[] = [ 'echo_event', 'event_agent_id', 'db' => $dbw ];
		$updateFields[] = [ 'echo_notification', 'notification_user', 'db' => $dbw, 'options' => [ 'IGNORE' ] ];
		$updateFields[] = [ 'echo_email_batch', 'eeb_user_id', 'db' => $dbw, 'options' => [ 'IGNORE' ] ];
	}

	public static function onMergeAccountFromTo( User &$oldUser, User &$newUser ) {
		$method = __METHOD__;
		DeferredUpdates::addCallableUpdate( static function () use ( $oldUser, $newUser, $method ) {
			if ( $newUser->isRegistered() ) {
				// Select notifications that are now sent to the same user
				$dbw = MWEchoDbFactory::newFromDefault()->getEchoDb( DB_PRIMARY );
				$attributeManager = EchoServices::getInstance()->getAttributeManager();
				$selfIds = $dbw->selectFieldValues(
					[ 'echo_notification', 'echo_event' ],
					'event_id',
					[
						'notification_user' => $newUser->getId(),
						'notification_event = event_id',
						'notification_user = event_agent_id',
						'event_type NOT IN (' . $dbw->makeList( $attributeManager->getNotifyAgentEvents() ) . ')'
					],
					$method
				) ?: [];

				// Select newer welcome notification(s)
				$welcomeIds = $dbw->selectFieldValues(
					[ 'echo_notification', 'echo_event' ],
					'event_id',
					[
						'notification_user' => $newUser->getId(),
						'notification_event = event_id',
						'event_type' => 'welcome',
					],
					$method,
					[
						'ORDER BY' => 'notification_timestamp ASC',
						'OFFSET' => 1,
					]
				) ?: [];

				// Select newer milestone notifications (per milestone level)
				$counts = [];
				$thankYouIds = [];
				$thankYouRows = $dbw->select(
					[ 'echo_notification', 'echo_event' ],
					Event::selectFields(),
					[
						'notification_user' => $newUser->getId(),
						'notification_event = event_id',
						'event_type' => 'thank-you-edit',
					],
					$method,
					[ 'ORDER BY' => 'notification_timestamp ASC' ]
				) ?: [];
				foreach ( $thankYouRows as $row ) {
					$event = Event::newFromRow( $row );
					$editCount = $event ? $event->getExtraParam( 'editCount' ) : null;
					if ( $editCount ) {
						if ( isset( $counts[$editCount] ) ) {
							$thankYouIds[] = $row->event_id;
						} else {
							$counts[$editCount] = true;
						}
					}
				}

				// Delete notifications
				$ids = array_merge( $selfIds, $welcomeIds, $thankYouIds );
				if ( $ids !== [] ) {
					$dbw->delete(
						'echo_notification',
						[
							'notification_user' => $newUser->getId(),
							'notification_event' => $ids
						],
						$method
					);
				}
			}

			MWEchoNotifUser::newFromUser( $oldUser )->resetNotificationCount();
			if ( $newUser->isRegistered() ) {
				MWEchoNotifUser::newFromUser( $newUser )->resetNotificationCount();
			}
		} );
	}

	public static function onUserMergeAccountDeleteTables( &$tables ) {
		$dbw = MWEchoDbFactory::newFromDefault()->getEchoDb( DB_PRIMARY );
		$tables['echo_notification'] = [ 'notification_user', 'db' => $dbw ];
		$tables['echo_email_batch'] = [ 'eeb_user_id', 'db' => $dbw ];
	}

	/**
	 * Sets custom login message for redirect from notification page
	 *
	 * @param array &$messages
	 */
	public function onLoginFormValidErrorMessages( array &$messages ) {
		$messages[] = 'echo-notification-loginrequired';
	}

	public static function getConfigVars( RL\Context $context, Config $config ) {
		return [
			'EchoMaxNotificationCount' => MWEchoNotifUser::MAX_BADGE_COUNT,
			'EchoPollForUpdates' => $config->get( 'EchoPollForUpdates' )
		];
	}

	public static function getLoggerConfigVars( RL\Context $context, Config $config ) {
		$schemas = $config->get( 'EchoEventLoggingSchemas' );
		return [
			'EchoInteractionLogging' => $schemas['EchoInteraction']['enabled'] &&
				ExtensionRegistry::getInstance()->isLoaded( 'EventLogging' ),
			'EchoEventLoggingVersion' => $config->get( 'EchoEventLoggingVersion' )
		];
	}

	/**
	 * @param WikiPage $article
	 * @param User $user
	 * @param string $reason
	 * @param int $articleId
	 * @param Content|null $content
	 * @param LogEntry $logEntry
	 * @param int $archivedRevisionCount
	 */
	public function onArticleDeleteComplete(
		$article,
		$user,
		$reason,
		$articleId,
		$content,
		$logEntry,
		$archivedRevisionCount
	) {
		DeferredUpdates::addCallableUpdate( static function () use ( $articleId ) {
			$eventMapper = new EventMapper();
			$eventIds = $eventMapper->fetchIdsByPage( $articleId );
			ModerationController::moderate( $eventIds, true );
		} );
	}

	/**
	 * @param Title $title
	 * @param bool $create
	 * @param string $comment
	 * @param int $oldPageId
	 * @param array $restoredPages
	 */
	public function onArticleUndelete( $title, $create, $comment, $oldPageId, $restoredPages ) {
		if ( $create ) {
			DeferredUpdates::addCallableUpdate( static function () use ( $oldPageId ) {
				$eventMapper = new EventMapper();
				$eventIds = $eventMapper->fetchIdsByPage( $oldPageId );
				ModerationController::moderate( $eventIds, false );
			} );
		}
	}

	/**
	 * Handler for SpecialMuteModifyFormFields hook
	 *
	 * @param UserIdentity|null $target
	 * @param User $user
	 * @param array &$fields
	 */
	public static function onSpecialMuteModifyFormFields( $target, $user, &$fields ) {
		$services = MediaWikiServices::getInstance();
		$echoPerUserBlacklist = $services->getMainConfig()->get( 'EchoPerUserBlacklist' );
		if ( $echoPerUserBlacklist ) {
			$id = $target ? $services->getCentralIdLookup()->centralIdFromLocalUser( $target ) : 0;
			$list = MultiUsernameFilter::splitIds(
				$services->getUserOptionsLookup()->getOption( $user, 'echo-notifications-blacklist' )
			);
			$fields[ 'echo-notifications-blacklist'] = [
				'type' => 'check',
				'label-message' => [
					'echo-specialmute-label-mute-notifications',
					$target ? $target->getName() : ''
				],
				'default' => in_array( $id, $list, true ),
			];
		}
	}

	/**
	 * @param RecentChange $change
	 * @return bool|void
	 * @throws MWException
	 */
	public function onRecentChange_save( $change ) {
		if ( !$this->config->get( 'EchoWatchlistNotifications' ) ) {
			return;
		}
		if ( $change->getAttribute( 'rc_minor' ) ) {
			$type = 'minor-watchlist-change';
		} else {
			$type = 'watchlist-change';
		}
		Event::create( [
			'type' => $type,
			'title' => $change->getTitle(),
			'extra' => [
				'page_title' => $change->getPage()->getDBkey(),
				'page_namespace' => $change->getPage()->getNamespace(),
				'revid' => $change->getAttribute( "rc_this_oldid" ),
				'logid' => $change->getAttribute( "rc_logid" ),
				'status' => $change->mExtra["pageStatus"],
				'timestamp' => $change->getAttribute( "rc_timestamp" ),
				'emailonce' => $this->config->get( 'EchoWatchlistEmailOncePerPage' )
			],
			'agent' => $change->getPerformerIdentity(),
		] );
	}

	/**
	 * Hook handler for ApiMain::moduleManager.
	 * Used here to put the echopushsubscriptions API module behind our push feature flag.
	 * TODO: Register this the usual way in extension.json when we don't need the feature flag
	 *  anymore.
	 * @param ApiModuleManager $moduleManager
	 */
	public function onApiMain__ModuleManager( $moduleManager ) {
		$services = MediaWikiServices::getInstance();
		$echoConfig = $services->getConfigFactory()->makeConfig( 'Echo' );
		$pushEnabled = $echoConfig->get( 'EchoEnablePush' );
		if ( $pushEnabled ) {
			$moduleManager->addModule(
				'echopushsubscriptions',
				'action',
				ApiEchoPushSubscriptions::class
			);
		}
	}

}
