/**
 * Make sure that clicking outside a menu closes it.
 */
function closeDropdownsOnClickOutside() {
	$( document.body ).on( 'click', function ( ev ) {
		var $closestPortlet = $( ev.target ).closest( '.mw-portlet' );
		// Uncheck (close) any menus that are open.
		// eslint-disable-next-line no-jquery/no-global-selector
		$( '.vector-menu-checkbox:checked' ).not(
			$closestPortlet.find( '.vector-menu-checkbox' )
		).prop( 'checked', false );
	} );
}

module.exports = function dropdownMenus() {
	closeDropdownsOnClickOutside();
};
