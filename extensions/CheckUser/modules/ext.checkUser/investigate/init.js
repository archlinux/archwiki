( function () {
	const addBlockForm = require( './blockform.js' ),
		setupTables = require( './tables.js' ),
		addCopyFeature = require( './copy.js' ),
		setUpResetGuidedTourLinks = require( './resetGuidedTour.js' );

	if ( $( '.ext-checkuser-investigate-subtitle-block-button' ).length > 0 ) {
		addBlockForm();
	}

	setupTables();

	if ( $( '.ext-checkuser-investigate-table-compare' ).length > 0 ) {
		addCopyFeature();
	}

	setUpResetGuidedTourLinks();

}() );
