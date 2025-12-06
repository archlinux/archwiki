const CategoryBrowser = require( './categories/CategoryBrowser.js' );
const FavoritesStore = require( './FavoritesStore.js' );
const SearchWidget = require( './SearchWidget.js' );
const TemplateList = require( './TemplateList.js' );
const mwConfig = require( './mwConfig.json' );
const FeaturedTemplatesList = require( './FeaturedTemplatesList.js' );
const TemplateDiscoveryConfig = require( './config.json' );
const mwStorage = require( 'mediawiki.storage' ).local;

/**
 * @class
 * @extends OO.ui.PanelLayout
 *
 * @constructor
 * @param {Object} [config] Configuration options.
 */
function TemplateSearchLayout( config ) {
	config = Object.assign( {
		padded: true,
		expanded: false
	}, config );
	TemplateSearchLayout.super.call( this, config );

	const favoritesStore = new FavoritesStore();

	this.searchWidget = new SearchWidget( { favoritesStore: favoritesStore } );
	this.searchWidget.connect( this, {
		choose: 'onAddTemplate'
	} );
	this.searchWidget.getMenu().connect( this, { choose: 'onAddTemplate' } );

	const field = new OO.ui.FieldLayout(
		this.searchWidget,
		{
			classes: [ 'ext-templatedata-search-field' ],
			label: mw.msg( 'templatedata-search-description' ),
			align: 'top'
		}
	);

	// By design, this is a tab bar with just a single tab.
	const tabLayout = new OO.ui.IndexLayout( {
		expanded: false,
		framed: false,
		classes: [ 'ext-templatedata-search-tabs' ]
	} );

	const templateList = new TemplateList( { favoritesStore: favoritesStore } );
	templateList.connect( this, { choose: 'onAddTemplate' } );
	tabLayout.addTabPanels( [ templateList ] );

	if ( mwConfig.TemplateDataEnableCategoryBrowser ||
		( new URLSearchParams( window.location.search ) ).get( 'enablediscovery' ) === '1'
	) {
		const categoryBrowser = new CategoryBrowser();
		categoryBrowser.connect( this, { choose: 'onAddTemplate' } );
		tabLayout.addTabPanels( [ categoryBrowser ] );
	}

	// Check if CommunityConfiguration is available
	// and if TemplateDataEnableFeaturedTemplates is true
	if ( TemplateDiscoveryConfig.communityConfigurationLoaded &&
		mwConfig.TemplateDataEnableFeaturedTemplates
	) {
		const featuredTemplatesList = new FeaturedTemplatesList(
			{
				favoritesStore: favoritesStore
			}
		);
		featuredTemplatesList.connect( this, { choose: 'onAddTemplate' } );
		tabLayout.addTabPanels( [ featuredTemplatesList ] );
	}

	this.storageKey = 'templatedata-discovery-tab';
	this.setupTabStorage( tabLayout );

	this.$element
		.addClass( 'ext-templatedata-search' )
		.append( $( '<div>' )
			.append(
				field.$element,
				tabLayout.$element
			)
		);
}

/* Setup */

OO.inheritClass( TemplateSearchLayout, OO.ui.PanelLayout );

/* Events */

/**
 * When a template is choosen by searching or other means.
 *
 * @event choose
 * @param {Object} The template data of the chosen template.
 */

/* Methods */

/**
 * @fires choose
 * @param {Object} data The texmplate data of the choosen template
 */
TemplateSearchLayout.prototype.onAddTemplate = function ( data ) {
	if ( !data ) {
		return;
	}
	this.emit( 'choose', data );
};

TemplateSearchLayout.prototype.focus = function () {
	this.searchWidget.$input.trigger( 'focus' );
};

/**
 * Keep track of the currently-selected tab in a localStorage item,
 * and restore it when loading the widget.
 *
 * @private
 * @param {OO.ui.IndexLayout} tabLayout
 */
TemplateSearchLayout.prototype.setupTabStorage = function ( tabLayout ) {
	// If there's a stored tab name, switch to it.
	tabLayout.connect( this, { set: 'onSetTab' } );
	const storedTab = mwStorage.get( this.storageKey );
	if ( storedTab && tabLayout.getTabPanel( storedTab ) ) {
		tabLayout.setTabPanel( storedTab );
	}
};

/**
 * @param {OO.ui.TabPanelLayout} tabPanel
 */
TemplateSearchLayout.prototype.onSetTab = function ( tabPanel ) {
	mwStorage.set( this.storageKey, tabPanel.getName() );
};

module.exports = TemplateSearchLayout;
