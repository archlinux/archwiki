var
	STICKY_HEADER_ID = 'vector-sticky-header',
	STICKY_HEADER_VISIBLE_CLASS = 'vector-sticky-header-visible',
	FIRST_HEADING_ID = 'firstHeading';

/**
 * Makes sticky header functional for modern Vector.
 *
 * @param {HTMLElement} header
 * @param {HTMLElement} stickyIntersection
 */
function makeStickyHeaderFunctional( header, stickyIntersection ) {
	/* eslint-disable-next-line compat/compat */
	var stickyObserver = new IntersectionObserver( function ( entries ) {
		if ( !entries[ 0 ].isIntersecting && entries[ 0 ].boundingClientRect.top < 0 ) {
			// Viewport has crossed the bottom edge of firstHeading so show sticky header.
			// eslint-disable-next-line mediawiki/class-doc
			header.classList.add( STICKY_HEADER_VISIBLE_CLASS );
		} else {
			// Viewport is above the bottom edge of firstHeading so hide sticky header.
			// eslint-disable-next-line mediawiki/class-doc
			header.classList.remove( STICKY_HEADER_VISIBLE_CLASS );
		}
	} );

	stickyObserver.observe( stickyIntersection );
}

module.exports = function initStickyHeader() {
	var header = /** @type {HTMLElement} */ ( document.getElementById( STICKY_HEADER_ID ) ),
		stickyIntersection = /** @type {HTMLElement} */ ( document.getElementById(
			FIRST_HEADING_ID
		) );

	if ( !(
		stickyIntersection &&
		header &&
		'IntersectionObserver' in window ) ) {
		return;
	}

	makeStickyHeaderFunctional( header, stickyIntersection );
};
