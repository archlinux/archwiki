/*
 * Special:Invesitgate form guided tour
 */
( function ( gt ) {
	if ( mw.config.get( 'wgCanonicalSpecialPageName' ) !== 'Investigate' ) {
		return;
	}

	if ( $( '#targets' ).length === 0 ) {
		return;
	}

	const tour = new gt.TourBuilder( {
		name: 'checkuserinvestigateform',
		shouldLog: true,
		isSinglePage: false
	} );

	tour.firstStep( {
		name: 'targets',
		titlemsg: 'checkuser-investigate-tour-targets-title',
		description: mw.message( 'checkuser-investigate-tour-targets-desc', mw.config.get( 'wgCheckUserInvestigateMaxTargets' ) ).parse(),
		attachTo: '#targets',
		position: 'bottom',
		closeOnClickOutside: false,
		overlay: true,
		onHide: function () {
			// Api.saveOption will save a string instead of a bool. :(
			( new mw.Api() ).saveOption( 'checkuser-investigate-form-tour-seen', 1 );
		},
		buttons: [
			{
				action: 'end'
			}
		]
	} );

}( mw.guidedTour ) );
