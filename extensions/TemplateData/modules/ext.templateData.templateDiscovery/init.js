const SpecialPage = require( './SpecialPage.js' );

mw.templateData = {
	config: require( './config.json' ),
	TemplateSearchLayout: require( './TemplateSearchLayout.js' ),
	FavoriteButton: require( './FavoriteButton.js' ),
	FavoritesStore: require( './FavoritesStore.js' )
};

// If we're on the TemplateDiscovery special page
if ( mw.config.get( 'wgCanonicalSpecialPageName' ) === 'TemplateDiscovery' ) {
	const specialPage = new SpecialPage();
	specialPage.init();
}
