const TemplateMenuItem = require( './TemplateMenuItem.js' );
const FavoritesStore = require( './FavoritesStore.js' );
const CCR_PROVIDER = 'TemplateData-FeaturedTemplates';
const CCR_PROVIDER_VERSION = '1.0.0';

/**
 * @class
 * @extends OO.ui.TabPanelLayout
 *
 * @constructor
 * @param {Object} [config] Configuration options.
 * @param {FavoritesStore} config.favoritesStore
 */
function FeaturedTemplatesList( config ) {
	config = Object.assign( {
		expanded: false,
		label: mw.msg( 'templatedata-featured-list-header' )
	}, config );
	FeaturedTemplatesList.super.call( this, 'featured-templates-list', config );
	this.$element.addClass( 'ext-templatedata-TemplateList' );
	this.config = config;
	this.menu = new OO.ui.PanelLayout( { expanded: false } );

	mw.user.getRights().then( ( rights ) => {
		// If the user has the 'editsitejson' right, show the configuration link
		if ( rights.includes( 'editsitejson' ) ) {
			const $configLinkWrapper = $( '<div>' );
			$configLinkWrapper.addClass( 'ext-templatedata-TemplateList-configLink' );
			const configLink = new OO.ui.ButtonWidget( {
				href: mw.util.getUrl( 'Special:CommunityConfiguration/' + CCR_PROVIDER ),
				label: mw.msg( 'templatedata-featured-list-config-link' ),
				framed: false,
				flags: [ 'progressive' ]
			} );
			$configLinkWrapper.append( configLink.$element );
			this.$element.append( $configLinkWrapper );
		}
	} );

	getCommunityConfiguration().then( ( ccData ) => {
		this.tabItem.$element.attr( 'title', mw.message( 'templatedata-featured-list-help' ).escaped() );
		if ( ccData.communityconfiguration &&
			ccData.communityconfiguration.data ) {
			if ( !ccData.communityconfiguration.data.FeaturedTemplates ) {
				this.menu.$element.append( $( '<p>' )
					.addClass( 'ext-templatedata-TemplateList-empty' )
					.text( mw.msg( 'templatedata-featured-list-empty' ) ) );
			} else {
				const featuredTemplates = ccData.communityconfiguration.data.FeaturedTemplates;
				if ( featuredTemplates.length === 0 ) {
					this.menu.$element.append( $( '<p>' )
						.addClass( 'ext-templatedata-TemplateList-empty' )
						.text( mw.msg( 'templatedata-featured-list-empty' ) ) );
				} else {
					// Query the API for the template data
					getTemplateData( featuredTemplates[ 0 ].titles ).then( ( templatesdata ) => {
						if ( templatesdata.pages === undefined ) {
							return;
						}
						for ( const pageId of Object.keys( templatesdata.pages ) ) {
							const page = templatesdata.pages[ pageId ];
							// Add `pageId` to the template data
							page.pageId = pageId;
							if ( page.missing ) {
								// TODO: Handle this?
								return;
							}
							this.addRowToList( page );
						}
					} );
				}
			}
			this.$element.append( this.menu.$element );
		}
	} );
}

/**
 * Get the community configuration for the featured templates.
 *
 * @return {Promise}
 */
function getCommunityConfiguration() {
	return new mw.Api().get( {
		action: 'query',
		meta: 'communityconfiguration',
		formatversion: 2,
		ccrprovider: CCR_PROVIDER,
		ccrassertversion: CCR_PROVIDER_VERSION
	} );
}

/**
 * Get the template data for a given set of templates.
 *
 * @param {array} templates Template titles.
 * @return {Promise}
 */
function getTemplateData( templates ) {
	return new mw.Api().get( {
		action: 'templatedata',
		includeMissingTitles: 1,
		titles: templates.join( '|' ),
		lang: mw.config.get( 'wgUserLanguage' ),
		redirects: 1,
		formatversion: 2
	} );
}

/* Setup */

OO.inheritClass( FeaturedTemplatesList, OO.ui.TabPanelLayout );

/* Events */

/**
 * When a template is chosen from the list of favorites.
 *
 * @event choose
 * @param {Object} The template data of the chosen template.
 */

/* Methods */

FeaturedTemplatesList.prototype.onChoose = function ( templateData ) {
	this.emit( 'choose', templateData );
};

/**
 * Add a template to the list of featured templates.
 *
 * @param {Object} data
 */
FeaturedTemplatesList.prototype.addRowToList = function ( data ) {
	const templateNsId = mw.config.get( 'wgNamespaceIds' ).template;
	const searchResultConfig = {
		data: data,
		label: mw.Title.newFromText( data.title ).getRelativeText( templateNsId ),
		description: data.description
	};
	const templateMenuItem = new TemplateMenuItem( searchResultConfig, this.config.favoritesStore );
	templateMenuItem.connect( this, { choose: 'onChoose' } );
	this.menu.$element.append( templateMenuItem.$element );
};

module.exports = FeaturedTemplatesList;
