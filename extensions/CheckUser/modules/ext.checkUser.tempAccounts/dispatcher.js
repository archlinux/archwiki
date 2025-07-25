( function () {
	// Include resources for specific special pages
	switch ( mw.config.get( 'wgCanonicalSpecialPageName' ) ) {
		case 'Block':
			require( './SpecialBlock.js' ).onLoad();
			break;
		case 'Recentchanges':
		case 'Watchlist':
			require( './initOnHook.js' )();
			break;
		case 'Contributions':
			if ( mw.config.get( 'wgRelevantUserName' ) &&
				mw.util.isTemporaryUser( mw.config.get( 'wgRelevantUserName' ) ) ) {
				require( './SpecialContributions.js' )( document, 'Contributions' );
			}
			break;
		case 'DeletedContributions':
			if ( mw.config.get( 'wgRelevantUserName' ) &&
				mw.util.isTemporaryUser( mw.config.get( 'wgRelevantUserName' ) ) ) {
				require( './SpecialContributions.js' )( document, 'DeletedContributions' );
			}
			break;
		case 'IPContributions': {
			// wgRelevantUserName is `null` if a range is the target
			// so check the variable passed from SpecialIPContributions instead.
			const ipRangeTarget = mw.config.get( 'wgIPRangeTarget' );

			// Only trigger if the target is an IP range. A single IP target doesn't
			// need IP reveal buttons.
			if ( ipRangeTarget &&
				mw.util.isIPAddress( ipRangeTarget, true ) &&
				!mw.util.isIPAddress( ipRangeTarget ) ) {
				require( './initOnLoad.js' )();
			}
			break;
		}
	}

	// Include resources for all but a few specific special pages
	// and for non-special pages that load this module
	let excludePages = [
		'IPContributions',
		'GlobalContributions',
		'Contributions',
		'Recentchanges',
		'Watchlist'
	];
	excludePages = excludePages.concat( mw.config.get( 'wgCheckUserSpecialPagesWithoutIPRevealButtons', [] ) );
	if (
		!mw.config.get( 'wgCanonicalSpecialPageName' ) ||
		!excludePages.includes( mw.config.get( 'wgCanonicalSpecialPageName' ) )
	) {
		require( './initOnLoad.js' )();
	}

	// Include auto-reveal on every page, if user has the right to auto-reveal
	if ( mw.config.get( 'wgCheckUserTemporaryAccountAutoRevealAllowed' ) ) {
		require( './autoReveal.js' )();
	}
}() );
