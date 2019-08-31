/**
 * Timeless-specific scripts
 */
$( function () {

	/**
	 * Focus on search box when 'Tab' key is pressed once
	 */
	$( '#searchInput' ).attr( 'tabindex', $( document ).lastTabIndex() + 1 );

	/**
	 * Add offset for # links to work around fixed header on desktop
	 * Apparently can't use CSS solutions due to highlighting of Cite links and similar. (T162649)
	 *
	 * Based on https://stackoverflow.com/questions/10732690/#answer-29853395
	 */
	function adjustAnchor() {
		var mobileCutoffWidth = 850,
			$anchor = $( ':target' ),
			fixedElementHeight = $( '#mw-header-container' ).outerHeight() + 15;

		if ( $( window ).width() > mobileCutoffWidth && $anchor.length > 0 ) {
			$( 'html, body' ).stop();
			window.scrollTo( 0, $anchor.offset().top - fixedElementHeight );
		}
	}

	$( window ).on( 'hashchange load', function () {
		adjustAnchor();
	} );
} );
