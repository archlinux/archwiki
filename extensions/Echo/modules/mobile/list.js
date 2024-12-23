const mobile = require( 'mobile.startup' ),
	View = mobile.View,
	promisedView = mobile.promisedView;

/**
 * @module module:ext.echo.mobile
 */

/**
 * @typedef {Function} FunctionCountChangeCallback
 * @memberof module:ext.echo.mobile
 * @param {number} count a capped (0-99 or 99+) count
 */

/**
 * List of notifications
 *
 * @param {mw.echo} echo class
 * @param {OO.ui.ButtonWidget} markAllReadButton - a button that will be associated with the
 *  read status of the notifications list.
 * @param {module:ext.echo.mobile.FunctionCountChangeCallback} onCountChange callback.
 * @return {View}
 */
function notificationsList( echo, markAllReadButton, onCountChange ) {
	const maxNotificationCount = require( './config.json' ).EchoMaxNotificationCount,
		echoApi = new echo.api.EchoApi(),
		unreadCounter = new echo.dm.UnreadNotificationCounter( echoApi, 'all', maxNotificationCount ),
		modelManager = new echo.dm.ModelManager( unreadCounter, { type: [ 'message', 'alert' ] } ),
		controller = new echo.Controller(
			echoApi,
			modelManager,
			{
				type: [ 'message', 'alert' ]
			}
		),
		markAsReadHandler = function () {
			markAllReadButton.toggle(
				controller.manager.hasLocalUnread()
			);
			markAllReadButton.setTitle(
				mw.msg( 'echo-mark-all-as-read', unreadCounter.getCount() )
			);
		},
		// Create a container which will be revealed when "more options" (...)
		// is clicked on a notification. Hidden by default.
		$moreOptions = $( '<div>' )
			.addClass( 'notifications-overlay-overlay position-fixed skin-invert' );

	echo.config.maxPrioritizedActions = 1;

	const wrapperWidget = new echo.ui.NotificationsWrapper( controller, modelManager, {
		$overlay: $moreOptions
	} );

	// Events
	unreadCounter.on( 'countChange', ( count ) => {
		onCountChange(
			controller.manager.getUnreadCounter().getCappedNotificationCount( count )
		);
		markAsReadHandler();
	} );
	markAllReadButton.on( 'click', () => {
		const numNotifications = controller.manager.getLocalUnread().length;

		controller.markLocalNotificationsRead()
			.then( () => {
				mw.notify( mw.msg( 'echo-mark-all-as-read-confirmation', numNotifications ) );
				markAllReadButton.toggle( false );
			}, () => {
				markAllReadButton.toggle( false );
			} );
	} );

	return promisedView(
		// Populate notifications
		wrapperWidget.populate().then( () => {
			controller.updateSeenTime();
			markAsReadHandler();
			// Connect event here as we know that everything loaded correctly
			modelManager.on( 'update', markAsReadHandler );
			return View.make( {}, [ wrapperWidget.$element, $moreOptions ] );
		} )
	);
}

module.exports = notificationsList;
