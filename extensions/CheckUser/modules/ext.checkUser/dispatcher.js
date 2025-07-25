( function () {
	// Include resources for specific special pages
	switch ( mw.config.get( 'wgCanonicalSpecialPageName' ) ) {
		case 'Investigate':
			require( './investigate/init.js' );
			break;
		case 'InvestigateBlock':
			require( './investigateblock/investigateblock.js' );
			break;
		case 'CheckUser': {
			require( './cidr/cidr.js' );
			require( './checkuser/getUsersBlockForm.js' )();
			const CheckUserHelper = require( './checkuser/checkUserHelper/init.js' );
			CheckUserHelper.init();
			break;
		}
		case 'CheckUserLog':
			require( './checkuserlog/highlightScroll.js' );
			break;
	}
}() );
