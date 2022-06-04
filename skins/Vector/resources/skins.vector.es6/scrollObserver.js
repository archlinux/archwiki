const
	SCROLL_HOOK = 'vector.page_title_scroll',
	SCROLL_CONTEXT_ABOVE = 'scrolled-above-page-title',
	SCROLL_CONTEXT_BELOW = 'scrolled-below-page-title',
	SCROLL_ACTION = 'scroll-to-top';

/**
 * Fire a hook to be captured by WikimediaEvents for scroll event logging.
 *
 * @param {string} direction the scroll direction
 */
function fireScrollHook( direction ) {
	if ( direction === 'down' ) {
		// @ts-ignore
		mw.hook( SCROLL_HOOK ).fire( { context: SCROLL_CONTEXT_BELOW } );
	} else {
		// @ts-ignore
		mw.hook( SCROLL_HOOK ).fire( {
			context: SCROLL_CONTEXT_ABOVE,
			action: SCROLL_ACTION
		} );
	}
}

/**
 * Create an observer for showing/hiding feature and for firing scroll event hooks.
 *
 * @param {Function} show functionality for when feature is visible
 * @param {Function} hide functionality for when feature is hidden
 * @return {IntersectionObserver}
 */
function initScrollObserver( show, hide ) {
	/* eslint-disable-next-line compat/compat */
	return new IntersectionObserver( function ( entries ) {
		if ( !entries[ 0 ].isIntersecting && entries[ 0 ].boundingClientRect.top < 0 ) {
			// Viewport has crossed the bottom edge of the target element.
			show();
		} else {
			// Viewport is above the bottom edge of the target element.
			hide();
		}
	} );
}

module.exports = {
	initScrollObserver,
	fireScrollHook
};
