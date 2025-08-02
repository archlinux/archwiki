function resetGuidedTour( clickedLink ) {
	const api = new mw.Api();
	const redirectTarget = clickedLink.attr( 'href' );
	const options = { 'checkuser-investigate-tour-seen': null };
	if ( clickedLink.hasClass( 'ext-checkuser-investigate-reset-form-guided-tour' ) ) {
		// Only reset the form guided tour if specifically requested.
		options[ 'checkuser-investigate-form-tour-seen' ] = null;
	}
	api.saveOptions( options ).then( () => {
		// Now that the preference is saved, refresh the page so that the
		// ResourceLoader modules get loaded and the tour gets shown.
		window.location.href = redirectTarget;
	} );
}

/**
 * Sets up event listeners for the links that reset the guided tours.
 */
function setUpResetGuidedTourLinks() {
	$( '.ext-checkuser-investigate-reset-guided-tour' ).on( 'click', function ( event ) {
		event.preventDefault();
		resetGuidedTour( $( this ) );
	} );
}

module.exports = setUpResetGuidedTourLinks;
