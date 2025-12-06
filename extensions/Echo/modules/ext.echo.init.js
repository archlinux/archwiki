/**
 * @module module:ext.echo.init
 */

/* eslint-disable no-jquery/no-global-selector */
mw.echo = mw.echo || {};
mw.echo.config = mw.echo.config || {};
// Set default max prioritized action links per item
mw.echo.config.maxPrioritizedActions = 2;

/**
 * Initialise desktop Echo experience
 */
function initDesktop() {
	'use strict';

	// Remove ?markasread=XYZ from the URL
	const url = new URL( location.href );
	if ( url.searchParams.has( 'markasread' ) ) {
		url.searchParams.delete( 'markasread' );
		url.searchParams.delete( 'markasreadwiki' );
		history.replaceState( null, '', url );
	}

	// Activate OOUI
	$( () => {
		const maxNotificationCount = require( './config.json' ).EchoMaxNotificationCount,
			pollingRate = require( './config.json' ).EchoPollForUpdates,
			documentTitle = document.title,
			$allNotifBadges = $( 'a[data-mw-notifications-section]' );

		let loadingPromise = null;
		let anyHasUnseen = false;
		const badgeControllers = {};
		const notificationCounts = {};

		$allNotifBadges.each( ( i, el ) => {
			const $badge = $( el );
			const section = $badge.attr( 'data-mw-notifications-section' );
			const numItems = $badge.attr( 'data-counter-num' );
			// eslint-disable-next-line no-jquery/no-class-state
			const hasUnseen = $badge.hasClass( 'mw-echo-unseen-notifications' );
			notificationCounts[ section ] = parseInt( numItems );
			anyHasUnseen = anyHasUnseen || hasUnseen;
		} );

		function updateDocumentTitleWithNotificationCount( counts ) {
			let totalCount = 0;
			for ( const section in counts ) {
				totalCount += counts[ section ];
			}

			let newTitle = documentTitle;
			if ( totalCount > 0 ) {
				let convertedTotalCount = totalCount <= maxNotificationCount ? totalCount : maxNotificationCount + 1;
				convertedTotalCount = mw.msg( 'echo-badge-count', mw.language.convertNumber( convertedTotalCount ) );
				newTitle = mw.msg( 'parentheses', convertedTotalCount ) + ' ' + documentTitle;
			}
			document.title = newTitle;
		}

		/**
		 * Show notification snippet via mw.notify of notifications which came after highestNotifTime.
		 *
		 * @param {mw.echo.dm.ModelManager} modelManager
		 * @param {Date} highestNotifTime Timestamp of latest notification the last time function was called
		 * @return {Date} Timestamp of latest notification
		 */
		function showNotificationSnippet( modelManager, highestNotifTime ) {
			let highestTime = highestNotifTime;
			modelManager.getLocalNotifications().forEach( ( notificationItem ) => {
				const timestampAsDate = new Date( notificationItem.timestamp );
				if ( timestampAsDate > highestNotifTime ) {
					if ( timestampAsDate > highestTime ) {
						highestTime = timestampAsDate;
					}
					if ( !notificationItem.seen ) {
						mw.notify( $.parseHTML( notificationItem.content.header ), { title: mw.msg( 'echo-displaysnippet-title' ) } );
					}
				}
			},
			);
			return highestTime;
		}

		/**
		 * Change the seen state of badges if there are any unseen notifications.
		 *
		 * @param {mw.echo.dm.ModelManager} modelManager
		 * @param {mw.echo.ui.NotificationBadgeController} badgeWidget
		 */
		function updateBadgeState( modelManager, badgeWidget ) {
			modelManager.getLocalNotifications().forEach( ( notificationItem ) => {
				if ( !notificationItem.isSeen() ) {
					badgeWidget.updateBadgeSeenState( true );
				}
			} );
		}

		function isLivePollingFeatureEnabledOnWiki() {
			return pollingRate !== 0;
		}

		/**
		 * User has opted in to preference to show notification snippets and update document title with unread count.
		 *
		 * Only useful when isLivePollingFeatureEnabledOnWiki() returns true.
		 *
		 * @return {boolean} User preference
		 */
		function userHasOptedInToLiveNotifications() {
			return mw.user.options.get( 'echo-show-poll-updates' ) === '1';
		}

		// Change document title on initialization only when polling rate feature flag is non-zero.
		if ( isLivePollingFeatureEnabledOnWiki() && userHasOptedInToLiveNotifications() ) {
			updateDocumentTitleWithNotificationCount( notificationCounts );
		}

		function loadEcho() {
			if ( loadingPromise !== null ) {
				return loadingPromise;
			}
			// This part executes only once, either when header icons are clicked or after completion of 60secs whichever occur first.
			const echoApi = new mw.echo.api.EchoApi();

			loadingPromise = mw.loader.using( 'ext.echo.ui.desktop' ).then( () => {
				// Overlay
				mw.echo.ui.$overlay.appendTo( document.body );

				$allNotifBadges.each( ( i, el ) => {
					const $badge = $( el );
					const section = $badge.attr( 'data-mw-notifications-section' );

					const numItems = $badge.attr( 'data-counter-num' );
					const convertedNumber = $badge.attr( 'data-counter-text' );
					// eslint-disable-next-line no-jquery/no-class-state
					const hasUnseen = $badge.hasClass( 'mw-echo-unseen-notifications' );
					const badgeIcon = $badge.attr( 'data-badge-icon' );

					const unreadCounter = new mw.echo.dm.UnreadNotificationCounter( echoApi, section, maxNotificationCount );
					const modelManager = new mw.echo.dm.ModelManager( unreadCounter, { type: section } );
					const controller = new mw.echo.Controller( echoApi, modelManager );
					const links = {
						notifications: $badge.attr( 'href' ) || mw.util.getUrl( 'Special:Notifications' ),
						preferences: ( $( '#pt-preferences a' ).attr( 'href' ) || mw.util.getUrl( 'Special:Preferences' ) ) +
							'#mw-prefsection-echo',
					};

					const badgeController = new mw.echo.ui.NotificationBadgeController(
						controller,
						modelManager,
						links,
						{
							numItems: Number( numItems ),
							convertedNumber: convertedNumber,
							hasUnseen: hasUnseen,
							badgeIcon: badgeIcon,
							$overlay: mw.echo.ui.$overlay,
							$badge: $badge,
						},
					);
					badgeControllers[ section ] = badgeController;

					modelManager.on( 'allTalkRead', () => {
						// If there was a talk page notification, get rid of it
						$( '#pt-talk-alert' ).remove();
					} );

					// listen to event countChange and change title only if polling rate is non-zero
					if ( isLivePollingFeatureEnabledOnWiki() ) {
						// latestNotifTime is the time of most recent notification that came when we called showNotificationSnippet last
						// the function showNotificationSnippet returns the time of the latest notification and latestNotifTime is updated
						let latestNotifTime = new Date();
						modelManager.getUnreadCounter().on( 'countChange', ( count ) => {
							controller.fetchLocalNotifications().then( () => {
								updateBadgeState( modelManager, badgeController );
								if ( userHasOptedInToLiveNotifications() ) {
									latestNotifTime = showNotificationSnippet( modelManager, latestNotifTime );
									notificationCounts[ section ] = count;
									updateDocumentTitleWithNotificationCount( notificationCounts );
								}
							} );
						} );
					}

					$badge.removeClass( 'mw-echo-notifications-badge-dimmed' );
				} );

				$allNotifBadges.off( 'click', badgeFirstClick );
			} );
			return loadingPromise;
		}

		// Respond to click on the notification button and load the UI on demand
		function badgeFirstClick( e ) {
			const timeOfClick = mw.now(),
				$badge = $( this ),
				clickedSection = $( e.target ).closest( 'a' ).data( 'mw-notifications-section' );
			if ( e.which !== 1 || $badge.data( 'clicked' ) ) {
				// Do not return false (as that calls stopPropagation)
				e.preventDefault();
				return;
			}

			$badge.data( 'clicked', true );

			// Dim the badge while we load
			$badge.addClass( 'mw-echo-notifications-badge-dimmed' );

			// Fire the notification API requests
			const echoApi = new mw.echo.api.EchoApi();
			echoApi.fetchNotifications( clickedSection )
				.then( ( data ) => {
					const now = mw.now();
					mw.track( 'stats.mediawiki_echo_overlay_seconds', now - timeOfClick, {
						component: 'api',
					} );
					// TODO remove graphite compatible call once new dashboards are created, T359347
					mw.track( 'timing.MediaWiki.echo.overlay.api', now - timeOfClick );
					return data;
				} );

			loadEcho().then( () => {
				// Now that the module loaded, show the popup
				const selectedWidget = badgeControllers[ clickedSection ];
				selectedWidget.once( 'finishLoading', () => {
					// Log timing after notifications are shown
					const notificationsLoadedTime = mw.now();
					mw.track( 'stats.mediawiki_echo_overlay_seconds', notificationsLoadedTime - timeOfClick, {
						component: 'none',
					} );
					// TODO remove graphite compatible call once new dashboards are created, T359347
					mw.track( 'timing.MediaWiki.echo.overlay', notificationsLoadedTime - timeOfClick );
				} );
				selectedWidget.popup.toggle( true );
				const now = mw.now();
				mw.track( 'stats.mediawiki_echo_overlay_seconds', now - timeOfClick, {
					component: 'ooui',
				} );
				// TODO remove graphite compatible call once new dashboards are created, T359347
				mw.track( 'timing.MediaWiki.echo.overlay.ooui', now - timeOfClick );

				if ( anyHasUnseen ) {
					// Clicked on the flyout due to having unread notifications
					// This is part of tracking how likely users are to click a badge with unseen notifications.
					// The other part is the 'echo.unseen' counter, see EchoHooks::onSkinTemplateNavigationUniversal().
					// TODO: remove the dedicated Graphite metric counter.MediaWiki.echo.unseen.click once
					// dashboard consuming Prometheus is setup, T381607
					mw.track( 'counter.MediaWiki.echo.unseen.click' );
					mw.track( 'stats.mediawiki_echo_unseen_click_total', 1, {
						wiki: mw.config.get( 'wgDBname' ),
						// eslint-disable-next-line camelcase
						user_type: getUserTypeForStats(),
					} );
				}
			}, () => {
				// Un-dim badge if loading failed
				$badge.removeClass( 'mw-echo-notifications-badge-dimmed' );
			} );
			// Prevent default. Do not return false (as that calls stopPropagation)
			e.preventDefault();
		}
		$allNotifBadges.on( 'click', badgeFirstClick );

		function pollForNotificationCountUpdates() {
			for ( const section in badgeControllers ) {
				badgeControllers[ section ].controller.refreshUnreadCount();
			}
			// Make notification update after n*pollingRate(time in secs) where n depends on document.hidden
			setTimeout( pollForNotificationCountUpdates, ( document.hidden ? 5 : 1 ) * pollingRate * 1000 );
		}

		function pollStart() {
			if ( mw.config.get( 'skin' ) !== 'minerva' && isLivePollingFeatureEnabledOnWiki() ) {
				// load widgets if not loaded already then start polling
				loadEcho().then( pollForNotificationCountUpdates );
			}
		}

		setTimeout( pollStart, 60 * 1000 );

	} );

}

/**
 * Get the user type for stats metrics
 * Needs to be in sync with Echo/includes/Hooks.php
 *
 * @return {string}
 */
function getUserTypeForStats() {
	if ( mw.user.isAnon() ) {
		return 'ip';
	} else if ( mw.user.isTemp() ) {
		return 'temp';
	} else if ( mw.user.isNamed() ) {
		return 'registered';
	}
	return 'unknown';
}

/**
 * Initialise a mobile experience instead
 */
function initMobile() {
	if ( !mw.user.isAnon() ) {
		mw.loader.using( [ 'ext.echo.mobile', 'mobile.startup' ] ).then( ( require ) => {
			require( 'ext.echo.mobile' ).init();
		} );
	}
}

$( () => {
	if ( mw.config.get( 'wgMFMode' ) ) {
		initMobile();
	} else {
		initDesktop();
	}
} );
