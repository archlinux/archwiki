/**
 * Upgrades Echo for icon consistency.
 * Undos work inside Echo to replace our button.
 */
function init() {
	if ( document.querySelectorAll( '#pt-notifications-alert a, #pt-notifications-notice a' ).length !== 2 ) {
		return;
	}

	// @ts-ignore
	mw.hook( 'ext.echo.NotificationBadgeWidget.onInitialize' ).add( function ( badge ) {
		var $element = badge.$element;
		$element.addClass( 'mw-list-item' );

		var iconButtonClasses = 'mw-ui-button mw-ui-quiet mw-ui-icon mw-ui-icon-element ';
		if ( $element.attr( 'id' ) === 'pt-notifications-alert' ) {
			$element.children( 'a' ).addClass( iconButtonClasses + 'mw-ui-icon-bell' );
			$element.children( 'a' ).removeClass( 'oo-ui-icon-bell' );
		}
		if ( $element.attr( 'id' ) === 'pt-notifications-notice' ) {
			$element.children( 'a' ).addClass( iconButtonClasses + 'mw-ui-icon-tray' );
			$element.children( 'a' ).removeClass( 'oo-ui-icon-tray' );
		}
	} );
}
module.exports = init;
