/*!
 * Add file description links to ImageMap images.
 */
( function () {
	mw.hook( 'wikipage.content' ).add( ( $content ) => {
		$content.find(
			'figure[class*="mw-ext-imagemap-desc-"] > :not(figcaption) .mw-file-element'
		).each( function () {
			const resource = this.getAttribute( 'resource' );
			if ( !resource ) {
				return;
			}
			const inner = this.parentNode;
			inner.classList.add( 'mw-ext-imagemap-inner' );
			const desc = this.ownerDocument.createElement( 'a' );
			desc.setAttribute( 'href', resource );
			desc.classList.add( 'mw-ext-imagemap-desc-link' );
			inner.appendChild( desc );
		} );
	} );
}() );
