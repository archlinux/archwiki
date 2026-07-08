/*!
 * Pre-init client utilities.
 *
 * @copyright See AUTHORS.txt
 */

mw.libs.ve = mw.libs.ve || {};

/**
 * Smoothly scroll the window to a specific vertical position
 *
 * @param {number} scrollTop Vertical position to scroll to
 */
mw.libs.ve.smoothScrollTo = function ( scrollTop ) {
	// Support for CSS `scroll-behavior: smooth;` and JS `window.scroll( { behavior: 'smooth' } )`
	// is correlated:
	// * https://caniuse.com/css-scroll-behavior
	// * https://caniuse.com/mdn-api_window_scroll_options_behavior_parameter
	const supportsSmoothScroll = 'scrollBehavior' in document.documentElement.style;
	if ( supportsSmoothScroll ) {
		window.scroll( {
			top: scrollTop,
			behavior: 'smooth'
		} );
	} else {
		// Support: Safari < 15.4, Chrome < 61
		let scrollContainer;
		if ( OO && OO.ui ) {
			// Use getRootScrollableElement if OOUI has loaded
			// Support: Chrome < 60
			scrollContainer = OO.ui.Element.static.getRootScrollableElement( document.body );
		} else {
			scrollContainer = document.documentElement;
		}

		$( scrollContainer ).animate( { scrollTop } );
	}
};
