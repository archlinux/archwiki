/* eslint-disable no-jquery/no-global-selector */
( function () {
	'use strict';

	// If MathPlayer is installed we show the MathML rendering.
	if ( navigator.userAgent.indexOf( 'MathPlayer' ) > -1 ) {
		$( '.mwe-math-mathml-a11y' ).removeClass( 'mwe-math-mathml-a11y' );
		$( '.mwe-math-fallback-image-inline, .mwe-math-fallback-image-display' ).css( 'display', 'none' );
		return;
	}

}() );
