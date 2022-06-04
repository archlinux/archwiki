/* eslint-disable no-jquery/no-global-selector */
( function () {
	'use strict';

	// If MathPlayer is installed we show the MathML rendering.
	if ( navigator.userAgent.indexOf( 'MathPlayer' ) > -1 ) {
		$( '.mwe-math-mathml-a11y' ).removeClass( 'mwe-math-mathml-a11y' );
		$( '.mwe-math-fallback-image-inline, .mwe-math-fallback-image-display' ).css( 'display', 'none' );
		return;
	}

	// We verify whether SVG as <img> is supported and otherwise use the
	// PNG fallback. See https://github.com/Modernizr/Modernizr/blob/master/feature-detects/svg/asimg.js
	if ( !document.implementation.hasFeature( 'http://www.w3.org/TR/SVG11/feature#Image', '1.1' ) ) {
		$( '.mwe-math-fallback-image-inline, .mwe-math-fallback-image-display' ).each( function () {
			this.src = this.src.replace( 'media/math/render/svg/', 'media/math/render/png/' );
			this.src = this.src.replace( 'mode=mathml', 'mode=mathml-png' );
		} );
	}
}() );
