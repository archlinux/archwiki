const mobile = require( 'mobile.startup' );

/**
 * @module module:ext.echo.mobile
 */

/**
 * @fires module:ext.echo.mobile#echo.mobile every time the notifications overlay is opened
 */
function onOpenNotificationsOverlay() {
	mw.hook( 'echo.mobile' ).fire( true );
}

/**
 * @fires module:ext.echo.mobile#echo.mobile every time the notifications overlay is closed
 */
function onCloseNotificationsOverlay() {
	mw.hook( 'echo.mobile' ).fire( false );
}

/**
 * This code loads the necessary modules for the notifications overlay, not to be confused
 * with the Toast notifications defined by common/toast.js.
 */
function init() {
	const notificationsFilterOverlay = require( './notificationsFilterOverlay.js' ),
		notificationsOverlay = require( './overlay.js' ),
		overlayManager = mobile.getOverlayManager();

	function showNotificationOverlay() {
		const overlay = notificationsOverlay(
			( exit ) => {
				onCloseNotificationsOverlay();
				exit();
			} );
		onOpenNotificationsOverlay();

		return overlay;
	}

	let initialized = false;
	// Once the DOM is loaded add the overlay and overlay manager. Minerva will handle the
	// notification button that will link to Special:Notifications.
	$( () => {
		overlayManager.add( /^\/notifications$/, showNotificationOverlay );

		/**
		 * Adds a filter button to the UI inside notificationsInboxWidget
		 *
		 * @method
		 * @ignore
		 */
		function addFilterButton() {
			// Create filter button once the notifications overlay has been loaded
			const filterStatusButton = new OO.ui.ButtonWidget(
				{
					href: '#/notifications-filter',
					classes: [ 'mw-echo-ui-notificationsInboxWidget-main-toolbar-nav-filter-placeholder' ],
					icon: 'funnel',
					label: mw.msg( 'echo-mobile-notifications-filter-title' )
				} );

			// eslint-disable-next-line no-jquery/no-global-selector
			$( '.mw-echo-ui-notificationsInboxWidget-cell-placeholder' ).append(
				$( '<div>' )
					.addClass( 'mw-echo-ui-notificationsInboxWidget-main-toolbar-nav-filter' )
					.addClass( 'mw-echo-ui-notificationsInboxWidget-cell' )
					.append( filterStatusButton.$element )
			);
		}

		// This code will currently only be invoked on Special:Notifications
		// The code is bundled here since it makes use of loadModuleScript. This also allows
		// the possibility of invoking the filter from outside the Special page in future.
		// Once the 'ext.echo.special.onInitialize' hook has fired, load notification filter.
		mw.hook( 'ext.echo.special.onInitialize' ).add( () => {
			// eslint-disable-next-line no-jquery/no-global-selector
			const $crossWikiUnreadFilter = $( '.mw-echo-ui-crossWikiUnreadFilterWidget' ),
				// eslint-disable-next-line no-jquery/no-global-selector
				$notifReadState = $( '.mw-echo-ui-notificationsInboxWidget-main-toolbar-readState' );

			// The 'ext.echo.special.onInitialize' hook is fired whenever special page notification
			// changes display on click of a filter.
			// Hence the hook is restricted from firing more than once.
			if ( initialized ) {
				return;
			}

			// setup the filter button (now we have OOjs UI)
			addFilterButton();

			// setup route
			overlayManager.add( /^\/notifications-filter$/, () => {
				onOpenNotificationsOverlay();
				return notificationsFilterOverlay( {
					onBeforeExit: function ( exit ) {
						onCloseNotificationsOverlay();
						exit();
					},
					$notifReadState: $notifReadState,
					$crossWikiUnreadFilter: $crossWikiUnreadFilter
				} );
			} );
			initialized = true;
		} );
	} );

}

module.exports.init = init;
