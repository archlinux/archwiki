/**
 * Notification badge for echo popup.
 *
 * @class
 *
 * @constructor
 * @param {Object} [config={}]
 * @param {jQuery} config.$badge the badge that was enhanced.
 * @param {string} [config.type] The notification types this button represents;
 *  'message', 'alert' or 'all'
 * @param {string} [config.numItems=0] The number of items that are in the button display
 * @param {string} [config.hasUnseen=false] There are unseen notifications of this type
 * @param {string} [config.convertedNumber] A converted version of the initial count
 */
mw.echo.ui.BadgeLinkController = function MwEchoUiBadgeLinkController( config ) {
	config = config || {};
	this.$badge = config.$badge;

	this.count = 0;
	this.type = config.type || 'alert';
	this.setCount( config.numItems || 0, config.convertedNumber );
	this.setHasUnseen( config.hasUnseen );
};

OO.initClass( mw.echo.ui.BadgeLinkController );

/**
 * @param {boolean} hasUnseen
 */
mw.echo.ui.BadgeLinkController.prototype.setHasUnseen = function ( hasUnseen ) {
	this.$badge
		.toggleClass( 'mw-echo-unseen-notifications', hasUnseen );
};

/**
 * Set the count labels for this button.
 *
 * @param {number} numItems Number of items
 * @param {string} [convertedNumber] Label of the button. Defaults to the default message
 *  showing the item number.
 */
mw.echo.ui.BadgeLinkController.prototype.setCount = function ( numItems, convertedNumber ) {
	convertedNumber = convertedNumber !== undefined ? convertedNumber : numItems;

	this.$badge
		.toggleClass( 'mw-echo-notifications-badge-all-read', !numItems )
		.toggleClass( 'mw-echo-notifications-badge-long-label', convertedNumber.length > 2 )
		.attr( 'data-counter-num', numItems )
		.attr( 'data-counter-text', convertedNumber );

	let $label = this.$badge.find( 'span' ).last();
	if ( $label.length === 0 ) {
		$label = this.$badge;
	}
	$label.text( mw.msg(
		// Messages that can be used here:
		// * echo-notification-notice
		// * echo-notification-alert
		// * echo-notification-all
		this.type === 'message' ?
			'echo-notification-notice' :
			'echo-notification-' + this.type,
		convertedNumber,
	) );

	if ( this.count !== numItems ) {
		this.count = numItems;

		// Fire badge count change hook
		mw.hook( 'ext.echo.badge.countChange' ).fire( this.type, this.count, convertedNumber );
	}
};
