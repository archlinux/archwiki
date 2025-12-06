const Column = require( './Column.js' );
const ColumnGroup = require( './ColumnGroup.js' );
const DataStore = require( './DataStore.js' );
const mwStorage = require( 'mediawiki.storage' ).local;
const TemplateDiscoveryConfig = require( '../config.json' );

/**
 * @class
 * @extends OO.ui.TabPanelLayout
 *
 * @constructor
 * @param {Object} [config] Configuration options.
 */
function CategoryBrowser( config ) {
	config = Object.assign( {
		expanded: false,
		label: mw.msg( 'templatedata-category-browser-header' )
	}, config );
	CategoryBrowser.super.call( this, 'category-browser', config );
	OO.ui.mixin.PendingElement.call( this, config );

	this.config = config;
	this.columns = [];

	this.storageKey = 'templatedata-categories-rootcat';
	this.rootCatSearch = new mw.widgets.TitleInputWidget( {
		namespace: mw.config.get( 'wgNamespaceIds' ).category,
		value: mwStorage.get( this.storageKey ) || TemplateDiscoveryConfig.categoryRootCat
	} );
	const rootCatButton = new OO.ui.ButtonWidget( { label: mw.msg( 'templatedata-category-switch-button' ) } );
	rootCatButton.connect( this, { click: this.loadRootTemplate } );
	const rootCatSearchField = new OO.ui.ActionFieldLayout(
		this.rootCatSearch,
		rootCatButton,
		{
			label: mw.msg( 'templatedata-category-switch-label' ),
			align: 'top'
		}
	);

	this.$colGroupContainer = $( '<div>' );
	this.$element.append( rootCatSearchField.$element, this.$colGroupContainer );
	this.$element.addClass( 'ext-templatedata-CategoryBrowser' );
}

/* Setup */

OO.inheritClass( CategoryBrowser, OO.ui.TabPanelLayout );
OO.mixinClass( CategoryBrowser, OO.ui.mixin.PendingElement );

/* Events */

/**
 * When a template is chosen from the list of favorites.
 *
 * @event choose
 * @param {Object} The template data of the chosen template.
 */

/* Methods */

/** @inheritDoc */
CategoryBrowser.prototype.setActive = function ( active ) {
	CategoryBrowser.super.prototype.setActive.call( this, active );
	if ( active ) {
		this.loadRootTemplate();
	}
};

CategoryBrowser.prototype.loadRootTemplate = function () {
	this.$colGroupContainer.empty();
	const dataStore = new DataStore();
	const columnGroup = new ColumnGroup( { dataStore: dataStore } );
	this.$colGroupContainer.append( columnGroup.$element );
	columnGroup.connect( this, { choose: ( templateData ) => {
		this.emit( 'choose', templateData );
	} } );
	const col1 = new Column( { dataStore: dataStore } );
	columnGroup.addColumn( col1 );
	const oneYear = 60 * 60 * 24 * 365;
	const rootCat = this.rootCatSearch.getValue().trim();
	if ( rootCat === TemplateDiscoveryConfig.categoryRootCat ) {
		mwStorage.remove( this.storageKey );
	} else {
		mwStorage.set( this.storageKey, rootCat, oneYear );
	}
	const catNsName = mw.config.get( 'wgFormattedNamespaces' )[ mw.config.get( 'wgNamespaceIds' ).category ];
	col1.loadItems( catNsName + ':' + rootCat ).then( () => {
		col1.focus();
	} );
};

module.exports = CategoryBrowser;
