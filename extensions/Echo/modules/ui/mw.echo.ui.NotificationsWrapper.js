( function () {
	/**
	 * Wrapper for the notifications widget, for view outside the popup.
	 *
	 * @class
	 * @extends OO.ui.Widget
	 * @mixes OO.ui.mixin.PendingElement
	 *
	 * @constructor
	 * @param {mw.echo.Controller} controller Echo controller
	 * @param {mw.echo.dm.ModelManager} model Notifications model manager
	 * @param {Object} [config] Configuration object
	 * @param {jQuery} [config.$overlay] A jQuery element functioning as an overlay
	 *  for popups.
	 */
	mw.echo.ui.NotificationsWrapper = function MwEchoUiNotificationsWrapper( controller, model, config ) {
		config = config || {};

		// Parent constructor
		mw.echo.ui.NotificationsWrapper.super.call( this, config );

		// Mixin constructor
		OO.ui.mixin.PendingElement.call( this, config );

		this.controller = controller;
		this.model = model;

		this.notificationsWidget = new mw.echo.ui.NotificationsListWidget(
			this.controller,
			this.model,
			{
				$overlay: config.$overlay,
				types: this.controller.getTypes(),
				label: mw.msg( 'notifications' ),
				icon: 'bell'
			}
		);

		// Initialize
		this.$element
			.addClass( 'mw-echo-notificationsWrapper skin-invert' )
			.append( this.notificationsWidget.$element );
	};

	/* Initialization */

	OO.inheritClass( mw.echo.ui.NotificationsWrapper, OO.ui.Widget );
	OO.mixinClass( mw.echo.ui.NotificationsWrapper, OO.ui.mixin.PendingElement );

	/* Events */

	/**
	 * Notifications have successfully finished being processed and are fully loaded
	 *
	 * @event mw.echo.ui.NotificationsWrapper#finishLoading
	 */

	/* Methods */

	/**
	 * Populate the notifications panel
	 *
	 * @return {jQuery.Promise} A promise that is resolved when all notifications
	 *  were fetched from the API and added to the model and UI.
	 * @fires mw.echo.ui.NotificationsWrapper#finishLoading
	 */
	mw.echo.ui.NotificationsWrapper.prototype.populate = function () {
		this.pushPending();
		return this.controller.fetchLocalNotifications( true )
			.catch( ( errorObj ) => {
				if ( errorObj.errCode === 'notlogin-required' ) {
					// Login required message
					this.notificationsWidget.resetLoadingOption( mw.msg( 'echo-notification-loginrequired' ) );
				} else {
					// Generic API failure message
					this.notificationsWidget.resetLoadingOption( mw.msg( 'echo-api-failure' ) );
				}
			} )
			.always( () => {
				this.popPending();
				this.emit( 'finishLoading' );
				this.promiseRunning = false;
			} );
	};
}() );
