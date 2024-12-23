const BODY_NOTIFICATIONS_REVEAL_CLASS = 'navigation-enabled secondary-navigation-enabled';

/**
 * Wire up the main menu
 *
 * @ignore
 */
function init() {

	// See I09c27a084100b223662f84de6cbe01bebe1fe774
	// will trigger every time the Echo notification is opened or closed.
	// This controls the drawer like behaviour of notifications
	// on tablet in mobile mode.
	mw.hook( 'echo.mobile' ).add( ( isOpen ) => {
		$( document.body ).toggleClass( BODY_NOTIFICATIONS_REVEAL_CLASS, isOpen );
	} );
}

module.exports = {
	init
};
