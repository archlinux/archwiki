const FavoriteButton = require( './FavoriteButton.js' );

mw.templateData = {
	config: require( './config.json' ),
	TemplateSearchLayout: require( './TemplateSearchLayout.js' ),
	FavoriteButton: FavoriteButton,
	FavoritesStore: require( './FavoritesStore.js' )
};

// If we're on the TemplateDiscovery special page
if ( mw.config.get( 'wgCanonicalSpecialPageName' ) === 'TemplateDiscovery' ) {
	// Only require this if we're on the special page
	const SpecialPage = require( './SpecialPage.js' );
	const specialPage = new SpecialPage();
	specialPage.init();
	return;
}
