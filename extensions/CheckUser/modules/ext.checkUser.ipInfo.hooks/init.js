( function () {
	/*
	 * Add Special:GlobalContributions link to IPInfo's infobox widget
	 * if target associated with the page is also supported by Special:GC:
	 * - Special:IPContributions (temporary accounts associated w/an IP address)
	 * - Special:Contributions or Special:DeletedContributions for temporary accounts
	 */
	const target = mw.config.get( 'wgRelevantUserName' );
	const pageName = mw.config.get( 'wgCanonicalSpecialPageName' );
	mw.hook( 'ext.ipinfo.infobox.widget' ).add( ( $info ) => {
		// Definition imported by module registration in RLRegisterModulesHandler
		// eslint-disable-next-line no-undef
		addSpecialGlobalContributionsLink( $info, target, pageName );
	} );
}() );
