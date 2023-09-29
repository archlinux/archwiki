module.exports = function () {
	mw.hook( 'wikipage.watchlistChange' ).add(
		function ( /** @type {boolean} */ isWatched, /** @type {string} */ expiry ) {
			var watchElement = document.querySelectorAll( '#ca-watch a, #ca-unwatch a' )[ 0 ];
			if ( !watchElement ) {
				return;
			}
			watchElement.classList.remove(
				// Vector attaches two icon classes to the element.
				// Remove the mw-ui-icon one rather than managing both.
				'mw-ui-icon-star',
				'mw-ui-icon-unStar',
				'mw-ui-icon-wikimedia-unStar',
				'mw-ui-icon-wikimedia-star',
				'mw-ui-icon-wikimedia-halfStar'
			);
			if ( isWatched ) {
				if ( expiry === 'infinity' ) {
					watchElement.classList.add( 'mw-ui-icon-wikimedia-unStar' );
				} else {
					watchElement.classList.add( 'mw-ui-icon-wikimedia-halfStar' );
				}
			} else {
				watchElement.classList.add( 'mw-ui-icon-wikimedia-star' );
			}
		}
	);
};
