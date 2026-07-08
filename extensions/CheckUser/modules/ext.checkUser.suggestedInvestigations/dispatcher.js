'use strict';

( function () {
	switch ( mw.config.get( 'wgCanonicalSpecialPageName' ) ) {
		case 'SuggestedInvestigations':
			require( './SpecialSuggestedInvestigations.js' )( window );
			break;
	}

	require( './instrumentation.js' )();
}() );
