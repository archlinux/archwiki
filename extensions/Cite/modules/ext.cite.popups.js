( function ( mw, $ ) {
	'use strict';

	mw.hook( 'wikipage.content' ).add( function ( $content ) {
		$content.find( '.biblio-cite-link,sup.reference a' ).tooltip( {
			bodyHandler: function () {
				return $content.find( '#' + this.hash.substr( 1 ) + ' > .reference-text' )
					.html();
			},
			showURL: false
		} );
	} );
} )( mediaWiki, jQuery );
