const utils = require( './utils.js' );

function init( $pageContainer ) {
	$pageContainer.find( '.ext-discussiontools-init-timestamplink' ).on( 'click', ( e ) => {
		if ( !utils.isUnmodifiedLeftClick( e ) ) {
			// Only handle unmodified left clicks
			return;
		}
		// Try to percent-decode the URL, so that non-Latin characters don't look so ugly (T357021)
		// Use currentTarget rather than target to avoid conflicts with userscripts that do their
		// own timestamp-wrapping. (T368701)
		let link = e.currentTarget.href;
		try {
			// decodeURI() may throw
			const decodedLink = decodeURI( link );
			// Check that the decoded URL is parsed to the same canonical URL
			// new URL() may throw
			if ( new URL( decodedLink ).toString() === link ) {
				link = decodedLink;
			}
		} catch ( err ) {}
		copyLink( link );
	} ).attr( 'data-event-name', 'discussiontools.permalink-copied' );
}

function copyLink( link ) {
	const $win = $( window );
	const scrollTop = $win.scrollTop();

	const $tmpInput = $( '<input>' )
		.val( link )
		.addClass( 'noime' )
		.css( {
			position: 'fixed',
			top: 0
		} )
		.appendTo( 'body' )
		.trigger( 'focus' );
	$tmpInput[ 0 ].setSelectionRange( 0, link.length );
	let copied;
	try {
		copied = document.execCommand( 'copy' );
	} catch ( err ) {
		copied = false;
	}
	if ( copied ) {
		mw.notify( mw.msg( 'discussiontools-permalink-comment-copied' ) );
	}
	$tmpInput.remove();

	// Restore scroll position, can be changed by setSelectionRange, or hash navigation
	function afterNextScroll() {
		// On desktop we can restore scroll immediately after the scroll
		// event, preventing a scroll flicker.
		$win.scrollTop( scrollTop );
		// On mobile, we need to wait another execution cycle (setTimeout)
		// before the scroll is rendered (and not requestAnimationFrame).
		setTimeout( () => {
			$win.scrollTop( scrollTop );
		} );
	}
	// Restore scroll position when the scroll event fires.
	// setTimeout does't reliably wait long enough for the native
	// scroll to happen.
	$win.one( 'scroll', afterNextScroll );
	// If we happened to be in the exact correct position, 'scroll' won't fire,
	// so clear the listener after a short delay
	setTimeout( () => {
		$win.off( 'scroll', afterNextScroll );
	}, 1000 );
}

module.exports = {
	init: init
};
