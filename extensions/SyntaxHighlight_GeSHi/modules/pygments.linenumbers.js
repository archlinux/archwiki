$( function () {

	var lastLine;

	function onHashChange() {
		var line = mw.util.getTargetFromFragment();

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
