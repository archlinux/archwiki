$( function () {

	var lastLine;

	function onHashChange() {
		var id = location.hash.slice( 1 ),
			// Don't assume location.hash will be parseable as a selector (T271572)
			// and avoid warning when id is empty (T272844)
			line = id ? document.getElementById( id ) : null;

		if ( lastLine ) {
			lastLine.classList.remove( 'hll' );
		}

		// Support IE 11, Edge 14, Safari 7: Can't use unprefixed Element.matches('… *') yet.
		if ( !line || !$( line ).closest( '.mw-highlight' ).length ) {
			// Matched ID wasn't in a highlight block
			lastLine = null;
			return;
		}

		line.classList.add( 'hll' );
		lastLine = line;
	}

	window.addEventListener( 'hashchange', onHashChange );

	// Check hash on load
	onHashChange();

}() );
