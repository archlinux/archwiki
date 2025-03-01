( function () {
	/**
	 * Notification badge button widget for echo popup.
	 *
	 * @class
	 * @extends OO.ui.ButtonWidget
	 *
	 * @constructor
	 * @param {mw.echo.Controller} controller Echo notifications controller
	 * @param {mw.echo.dm.ModelManager} manager Model manager
	 * @param {Object} links Links object, containing 'notifications' and 'preferences' URLs
	 * @param {Object} config Configuration object
	 * @param {string|string[]} [config.type='message'] The type or array of types of
	 *  notifications that are in this model. They can be 'alert', 'message' or
	 *  an array of both. Defaults to 'message'
	 * @param {number} [config.numItems=0] The number of items that are in the button display
	 * @param {string} [config.convertedNumber] A converted version of the initial count
	 * @param {string} [config.badgeLabel=0] The initial label for the badge. This is the
	 *  formatted version of the number of items in the badge.
	 * @param {boolean} [config.hasUnseen=false] Whether there are unseen items
	 * @param {number} [config.popupWidth=450] The width of the popup
	 * @param {string} [config.badgeIcon] Icon to use for the popup header
	 * @param {string} [config.href] URL the badge links to
	 * @param {jQuery} [config.$overlay] A jQuery element functioning as an overlay
	 *  for popups.
	 */
	mw.echo.ui.NotificationBadgeWidget = function MwEchoUiNotificationBadgeButtonPopupWidget( controller, manager, links, config ) {
		config = config || {};

		// Parent constructor
		mw.echo.ui.NotificationBadgeWidget.super.call( this, config );

		// Mixin constructors
		OO.ui.mixin.PendingElement.call( this, config );

		this.$overlay = config.$overlay || this.$element;
		// Create a menu overlay
		this.$menuOverlay = $( '<div>' )
			.addClass( 'mw-echo-ui-NotificationBadgeWidget-overlay-menu' );
		this.$overlay.append( this.$menuOverlay );

		// Controller
		this.controller = controller;
		this.manager = manager;

		const adjustedTypeString = this.controller.getTypeString() === 'message' ? 'notice' : this.controller.getTypeString();

		// Properties
		this.types = this.manager.getTypes();

		this.numItems = config.numItems || 0;
		this.hasRunFirstTime = false;

		const buttonFlags = [];
		if ( config.hasUnseen ) {
			buttonFlags.push( 'unseen' );
		}

		this.badgeButton = new mw.echo.ui.BadgeLinkWidget( {
			convertedNumber: config.convertedNumber,
			type: this.manager.getTypeString(),
			numItems: this.numItems,
			flags: buttonFlags,
			// The following messages can be used here:
			// * tooltip-pt-notifications-alert
			// * tooltip-pt-notifications-notice
			title: mw.msg( 'tooltip-pt-notifications-' + adjustedTypeString ),
			href: config.href
		} );

		// Notifications list widget
		this.notificationsWidget = new mw.echo.ui.NotificationsListWidget(
			this.controller,
			this.manager,
			{
				type: this.types,
				$overlay: this.$menuOverlay,
				animated: true
			}
		);

		// Footer
		const allNotificationsButton = new OO.ui.ButtonWidget( {
			icon: 'next',
			label: mw.msg( 'echo-overlay-link' ),
			href: links.notifications,
			classes: [ 'mw-echo-ui-notificationBadgeButtonPopupWidget-footer-allnotifs' ]
		} );
		allNotificationsButton.$element.children().first().removeAttr( 'role' );

		const preferencesButton = new OO.ui.ButtonWidget( {
			icon: 'settings',
			label: mw.msg( 'mypreferences' ),
			href: links.preferences,
			classes: [ 'mw-echo-ui-notificationBadgeButtonPopupWidget-footer-preferences' ]
		} );
		preferencesButton.$element.children().first().removeAttr( 'role' );

		const footerItems = [ allNotificationsButton ];
		if ( !mw.user.isTemp() ) {
			footerItems.push( preferencesButton );
		}
		const footerButtonGroupWidget = new OO.ui.ButtonGroupWidget( {
			items: footerItems,
			classes: [ 'mw-echo-ui-notificationBadgeButtonPopupWidget-footer-buttons' ]
		} );
		const $footer = $( '<div>' )
			.addClass( 'mw-echo-ui-notificationBadgeButtonPopupWidget-footer' )
			.append( footerButtonGroupWidget.$element );

		const screenWidth = $( window ).width();
		// FIXME 639 is @max-width-breakpoint-mobile value in wikimedia-ui-base.less,
		// should be updated with aproppriate JS exported Codex token once available, T366622
		const maxWidthBreakPoint = 639;
		const isUnderBreakpointMobile = screenWidth < maxWidthBreakPoint;
		const mql = window.matchMedia( `(max-width: ${ maxWidthBreakPoint }px)` );
		const matchMedia = function ( event ) {
			if ( event.matches ) {
				this.popup.containerPadding = 0;
			} else {
				this.popup.containerPadding = 20;
			}
		};
		this.popup = new OO.ui.PopupWidget( {
			$content: this.notificationsWidget.$element,
			$footer: $footer,
			width: config.popupWidth || 500,
			hideWhenOutOfView: false,
			autoFlip: false,
			autoClose: true,
			containerPadding: isUnderBreakpointMobile ? 0 : 20,
			$floatableContainer: this.$element,
			// Also ignore clicks from the nested action menu items, that
			// actually exist in the overlay
			$autoCloseIgnore: this.$element.add( this.$menuOverlay ),
			head: true,
			// The following messages can be used here:
			// * echo-notification-alert-text-only
			// * echo-notification-notice-text-only
			label: mw.msg(
				'echo-notification-' + adjustedTypeString +
				'-text-only'
			),
			classes: [ 'mw-echo-ui-notificationBadgeButtonPopupWidget-popup' ]
		} );
		mql.addEventListener( 'change', matchMedia.bind( this ) );
		// Append the popup to the overlay
		this.$overlay.append( this.popup.$element );

		// HACK: Add an icon to the popup head label
		this.popupHeadIcon = new OO.ui.IconWidget( { icon: config.badgeIcon } );
		this.popup.$head.prepend( this.popupHeadIcon.$element );

		this.setPendingElement( this.popup.$head );

		// Mark all as read button
		this.markAllReadLabel = mw.msg( 'echo-mark-all-as-read', config.convertedNumber );
		this.markAllReadButton = new OO.ui.ButtonWidget( {
			framed: false,
			label: this.markAllReadLabel,
			classes: [ 'mw-echo-ui-notificationsWidget-markAllReadButton' ]
		} );

		// Hide the close button
		this.popup.closeButton.toggle( false );
		// Add the 'mark all as read' button to the header
		this.popup.$head.append( this.markAllReadButton.$element );
		this.markAllReadButton.toggle( false );

		// Events
		this.markAllReadButton.connect( this, { click: 'onMarkAllReadButtonClick' } );
		this.manager.connect( this, {
			update: 'updateBadge'
		} );
		this.manager.getSeenTimeModel().connect( this, { update: 'onSeenTimeModelUpdate' } );
		this.manager.getUnreadCounter().connect( this, { countChange: 'updateBadge' } );
		this.popup.connect( this, { toggle: 'onPopupToggle' } );
		this.badgeButton.connect( this, {
			click: 'onBadgeButtonClick'
		} );
		this.notificationsWidget.connect( this, { modified: 'onNotificationsListModified' } );

		this.$element
			.prop( 'id', 'pt-notifications-' + adjustedTypeString )
			// The following classes can be used here:
			// * mw-echo-ui-notificationBadgeButtonPopupWidget-alert
			// * mw-echo-ui-notificationBadgeButtonPopupWidget-message
			.addClass(
				'mw-echo-ui-notificationBadgeButtonPopupWidget ' +
				'mw-echo-ui-notificationBadgeButtonPopupWidget-' + adjustedTypeString
			)
			.append( this.badgeButton.$element );
		mw.hook( 'ext.echo.NotificationBadgeWidget.onInitialize' ).fire( this );
	};

	/* Initialization */

	OO.inheritClass( mw.echo.ui.NotificationBadgeWidget, OO.ui.Widget );
	OO.mixinClass( mw.echo.ui.NotificationBadgeWidget, OO.ui.mixin.PendingElement );

	/* Static properties */

	mw.echo.ui.NotificationBadgeWidget.static.tagName = 'li';

	/* Events */

	/**
	 * All notifications were marked as read
	 *
	 * @event mw.echo.ui.NotificationBadgeWidget#allRead
	 */

	/**
	 * Notifications have successfully finished being processed and are fully loaded
	 *
	 * @event mw.echo.ui.NotificationBadgeWidget#finishLoading
	 */

	/* Methods */

	/**
	 * Respond to list widget modified event.
	 *
	 * This means the list's actual DOM was modified and we should make sure
	 * that the popup resizes itself.
	 */
	mw.echo.ui.NotificationBadgeWidget.prototype.onNotificationsListModified = function () {
		this.popup.clip();
	};

	/**
	 * Respond to badge button click
	 */
	mw.echo.ui.NotificationBadgeWidget.prototype.onBadgeButtonClick = function () {
		this.popup.toggle();
	};

	/**
	 * Respond to SeenTime model update event
	 */
	mw.echo.ui.NotificationBadgeWidget.prototype.onSeenTimeModelUpdate = function () {
		this.updateBadgeSeenState( false );
	};

	/**
	 * Update the badge style to match whether it contains unseen notifications.
	 *
	 * @param {boolean} [hasUnseen=false] There are unseen notifications
	 */
	mw.echo.ui.NotificationBadgeWidget.prototype.updateBadgeSeenState = function ( hasUnseen ) {
		hasUnseen = hasUnseen === undefined ? false : !!hasUnseen;

		this.badgeButton.setFlags( { unseen: !!hasUnseen } );
	};

	/**
	 * Update the badge state and label based on changes to the model
	 */
	mw.echo.ui.NotificationBadgeWidget.prototype.updateBadge = function () {
		const unreadCount = this.manager.getUnreadCounter().getCount();
		const cappedUnreadCount = this.manager.getUnreadCounter().getCappedNotificationCount( unreadCount );
		const convertedCount = mw.language.convertNumber( cappedUnreadCount );
		const badgeLabel = mw.msg( 'echo-badge-count', convertedCount );
		this.markAllReadLabel = mw.msg( 'echo-mark-all-as-read', convertedCount );
		this.markAllReadButton.setLabel( this.markAllReadLabel );

		this.badgeButton.setLabel( badgeLabel );
		this.badgeButton.setCount( unreadCount, badgeLabel );
		// Update seen state only if the counter is 0
		// so we don't run into inconsistencies and have an unseen state
		// for the badge with 0 unread notifications
		if ( unreadCount === 0 ) {
			this.updateBadgeSeenState( false );
		}

		// Check if we need to display the 'mark all unread' button
		this.markAllReadButton.toggle( this.manager.hasLocalUnread() );
	};

	/**
	 * Respond to 'mark all as read' button click
	 */
	mw.echo.ui.NotificationBadgeWidget.prototype.onMarkAllReadButtonClick = function () {
		this.controller.markLocalNotificationsRead();
	};

	/**
	 * Extend the response to button click so we can also update the notification list.
	 *
	 * @param {boolean} isVisible The popup is visible
	 * @fires mw.echo.ui.NotificationBadgeWidget#finishLoading
	 */
	mw.echo.ui.NotificationBadgeWidget.prototype.onPopupToggle = function ( isVisible ) {
		if ( this.promiseRunning ) {
			return;
		}

		if ( !isVisible ) {
			this.notificationsWidget.resetInitiallyUnseenItems();
			return;
		}

		if ( this.hasRunFirstTime ) {
			// HACK: Clippable doesn't resize the clippable area when
			// it calculates the new size. Since the popup contents changed
			// and the popup is "empty" now, we need to manually set its
			// size to 1px so the clip calculations will resize it properly.
			// See bug report: https://phabricator.wikimedia.org/T110759
			this.popup.$clippable.css( 'height', '1px' );
			this.popup.clip();
		}

		this.pushPending();
		this.markAllReadButton.toggle( false );
		this.promiseRunning = true;

		// Always populate on popup open. The model and widget should handle
		// the case where the promise is already underway.
		this.controller.fetchLocalNotifications( this.hasRunFirstTime )
			.then(
				// Success
				() => {
					if ( this.popup.isVisible() ) {
						// Fire initialization hook
						mw.hook( 'ext.echo.popup.onInitialize' ).fire( this.manager.getTypeString(), this.controller );

						// Update seen time
						return this.controller.updateSeenTime();
					}
				},
				// Failure
				( errorObj ) => {
					if ( errorObj.errCode === 'notlogin-required' ) {
						// Login required message
						this.notificationsWidget.resetLoadingOption( mw.msg( 'echo-notification-loginrequired' ) );
					} else {
						// Generic API failure message
						this.notificationsWidget.resetLoadingOption( mw.msg( 'echo-api-failure' ) );
					}
				}
			)
			.then( this.emit.bind( this, 'finishLoading' ) )
			.always( () => {
				this.popup.clip();
				// Pop pending
				this.popPending();
				this.promiseRunning = false;
			} );
		this.hasRunFirstTime = true;
	};
}() );
