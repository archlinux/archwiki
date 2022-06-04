/*!
 * VisualEditor UserInterface MWParameterSearchWidget class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * Creates an ve.ui.MWParameterSearchWidget object.
 *
 * @class
 * @extends OO.ui.SearchWidget
 *
 * @constructor
 * @param {ve.dm.MWTemplateModel} template Template model
 * @param {Object} [config] Configuration options
 * @cfg {number|null} [limit=3] Limit on the number of initial options to show, null to show all
 * @cfg {boolean} [showAll=false] If the results should be initially expanded, ignoring the limit
 */
ve.ui.MWParameterSearchWidget = function VeUiMWParameterSearchWidget( template, config ) {
	// Configuration initialization
	config = ve.extendObject( {
		placeholder: ve.msg( 'visualeditor-parameter-input-placeholder' ),
		limit: 3
	}, config );

	// Parent constructor
	ve.ui.MWParameterSearchWidget.super.call( this, config );

	// Properties
	this.template = template;
	this.index = [];
	this.showAll = !!config.showAll;
	this.limit = config.limit || null;

	// Events
	this.template.connect( this, { add: 'buildIndex', remove: 'buildIndex' } );
	this.getResults().connect( this, { choose: 'onSearchResultsChoose' } );

	// Initialization
	this.$element.addClass( 've-ui-mwParameterSearchWidget' );
	this.query.$input.attr( 'aria-label', ve.msg( 'visualeditor-parameter-input-placeholder' ) );
	this.buildIndex();
};

/* Inheritance */

OO.inheritClass( ve.ui.MWParameterSearchWidget, OO.ui.SearchWidget );

/* Events */

/**
 * @event choose
 * @param {string|null} name Parameter name or null if no item is selected
 */

/* Methods */

/**
 * Handle select widget select events.
 *
 * @param {string} value New value
 */
ve.ui.MWParameterSearchWidget.prototype.onQueryChange = function () {
	// Parent method
	ve.ui.MWParameterSearchWidget.super.prototype.onQueryChange.call( this );

	// Populate
	this.addResults();
};

/**
 * Handle SelectWidget choose events.
 *
 * @param {OO.ui.OptionWidget} item Selected item
 * @fires choose
 * @fires showAll
 */
ve.ui.MWParameterSearchWidget.prototype.onSearchResultsChoose = function ( item ) {
	if ( item instanceof ve.ui.MWParameterResultWidget ) {
		this.emit( 'choose', item.getData().name );
	} else if ( item instanceof ve.ui.MWMoreParametersResultWidget ) {
		this.showAll = true;
		this.addResults();
		this.emit( 'showAll' );
	}
};

/**
 * Build a searchable index of parameters.
 */
ve.ui.MWParameterSearchWidget.prototype.buildIndex = function () {
	var spec = this.template.getSpec(),
		knownParams = spec.getKnownParameterNames();

	this.index.length = 0;
	for ( var i = 0; i < knownParams.length; i++ ) {
		var name = knownParams[ i ];
		// Skip parameters already in use
		if ( this.template.hasParameter( name ) ) {
			continue;
		}
		var label = spec.getParameterLabel( name ),
			aliases = spec.getParameterAliases( name ),
			description = spec.getParameterDescription( name );

		this.index.push( {
			// Query information
			text: [ label, description ].join( ' ' ).toLowerCase(),
			names: [ name ].concat( aliases ).join( '|' ).toLowerCase(),
			// Display information
			name: name,
			label: label,
			aliases: aliases,
			description: description,
			deprecated: spec.isParameterDeprecated( name )
		} );
	}

	// Re-populate
	this.onQueryChange();
};

/**
 * Handle media query response events.
 */
ve.ui.MWParameterSearchWidget.prototype.addResults = function () {
	var textMatch, nameMatch, remainder,
		exactMatch = false,
		value = this.query.getValue().trim().replace( /[={|}]+/g, '' ),
		query = value.toLowerCase(),
		hasQuery = !!query.length,
		items = [];

	this.results.clearItems();

	for ( var i = 0; i < this.index.length; i++ ) {
		var item = this.index[ i ];
		if ( hasQuery ) {
			textMatch = item.text.indexOf( query ) !== -1;
			nameMatch = item.names.indexOf( query ) !== -1;
		}
		if ( !hasQuery || textMatch || nameMatch ) {
			// Only show exact matches for deprecated params
			if ( item.deprecated && query !== item.name && item.aliases.indexOf( query ) === -1 ) {
				continue;
			}
			items.push( new ve.ui.MWParameterResultWidget( { data: item } ) );
			if ( hasQuery && nameMatch && item.names.split( '|' ).indexOf( query ) !== -1 ) {
				exactMatch = true;
			}
		}
		if ( !hasQuery && !this.showAll && items.length >= this.limit ) {
			remainder = this.index.length - i;
			break;
		}
	}

	if ( hasQuery && !exactMatch && value.length && !this.template.hasParameter( value ) ) {
		items.unshift( new ve.ui.MWParameterResultWidget( {
			data: {
				name: value,
				label: value,
				aliases: [],
				isUnknown: true
			}
		} ) );
	}

	if ( !items.length ) {
		items.push( new ve.ui.MWNoParametersResultWidget( {
			data: {},
			disabled: true
		} ) );
	} else if ( remainder ) {
		items.push( new ve.ui.MWMoreParametersResultWidget( {
			data: { remainder: remainder }
		} ) );
	}

	this.results.addItems( items );
	if ( hasQuery ) {
		this.results.highlightItem( this.results.findFirstSelectableItem() );
	}
};
