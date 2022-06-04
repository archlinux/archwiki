/*!
 * VisualEditor UserInterface MWReferenceSearchWidget class.
 *
 * @copyright 2011-2018 VisualEditor Team's Cite sub-team and others; see AUTHORS.txt
 * @license MIT
 */

/**
 * Creates an ve.ui.MWReferenceSearchWidget object.
 *
 * @class
 * @extends OO.ui.SearchWidget
 *
 * @constructor
 * @param {Object} [config] Configuration options
 */
ve.ui.MWReferenceSearchWidget = function VeUiMWReferenceSearchWidget( config ) {
	// Configuration initialization
	config = ve.extendObject( {
		placeholder: ve.msg( 'cite-ve-reference-input-placeholder' )
	}, config );

	// Parent constructor
	ve.ui.MWReferenceSearchWidget.super.call( this, config );

	// Properties
	this.index = [];
	this.indexEmpty = true;
	this.built = false;

	// Initialization
	this.$element.addClass( 've-ui-mwReferenceSearchWidget' );
};

/* Inheritance */

OO.inheritClass( ve.ui.MWReferenceSearchWidget, OO.ui.SearchWidget );

/* Methods */

/**
 * Handle query change events.
 *
 * @method
 * @param {string} value New value
 */
ve.ui.MWReferenceSearchWidget.prototype.onQueryChange = function () {
	// Parent method
	ve.ui.MWReferenceSearchWidget.super.prototype.onQueryChange.call( this );

	// Populate
	this.addResults();
};

/**
 * Set the internal list and check if it contains any references
 *
 * @param {ve.dm.InternalList} internalList Internal list
 */
ve.ui.MWReferenceSearchWidget.prototype.setInternalList = function ( internalList ) {

	if ( this.results.findSelectedItem() ) {
		this.results.findSelectedItem().setSelected( false );
	}

	this.internalList = internalList;
	this.internalList.connect( this, { update: 'onInternalListUpdate' } );
	this.internalList.getListNode().connect( this, { update: 'onListNodeUpdate' } );

	var groups = internalList.getNodeGroups();
	var groupNames = Object.keys( groups );
	for ( var i = 0, iLen = groupNames.length; i < iLen; i++ ) {
		var groupName = groupNames[ i ];
		if ( groupName.lastIndexOf( 'mwReference/' ) !== 0 ) {
			continue;
		}
		if ( groups[ groupName ].indexOrder.length ) {
			this.indexEmpty = false;
			return;
		}
	}
	this.indexEmpty = true;
};

/**
 * Handle the updating of the InternalList object.
 *
 * This will occur after a document transaction.
 *
 * @method
 * @param {string[]} groupsChanged A list of groups which have changed in this transaction
 */
ve.ui.MWReferenceSearchWidget.prototype.onInternalListUpdate = function ( groupsChanged ) {
	for ( var i = 0, len = groupsChanged.length; i < len; i++ ) {
		if ( groupsChanged[ i ].indexOf( 'mwReference/' ) === 0 ) {
			this.built = false;
			break;
		}
	}
};

/**
 * Handle the updating of the InternalListNode.
 *
 * This will occur after changes to any InternalItemNode.
 *
 * @method
 */
ve.ui.MWReferenceSearchWidget.prototype.onListNodeUpdate = function () {
	this.built = false;
};

/**
 * Build a searchable index of references.
 *
 * @method
 */
ve.ui.MWReferenceSearchWidget.prototype.buildIndex = function () {
	var groups = this.internalList.getNodeGroups();

	if ( this.built ) {
		return;
	}

	var text;

	this.index = [];
	var groupNames = Object.keys( groups ).sort();

	for ( var i = 0, iLen = groupNames.length; i < iLen; i++ ) {
		var groupName = groupNames[ i ];
		if ( groupName.lastIndexOf( 'mwReference/' ) !== 0 ) {
			continue;
		}
		var group = groups[ groupName ];
		var firstNodes = group.firstNodes;
		var indexOrder = group.indexOrder;

		var n = 0;
		for ( var j = 0, jLen = indexOrder.length; j < jLen; j++ ) {
			var refNode = firstNodes[ indexOrder[ j ] ];
			// Exclude placeholder references
			if ( refNode.getAttribute( 'placeholder' ) ) {
				continue;
			}
			// Only increment counter for real references
			n++;
			var refModel = ve.dm.MWReferenceModel.static.newFromReferenceNode( refNode );
			var view = new ve.ui.MWPreviewElement(
				this.internalList.getItemNode( refModel.getListIndex() )
			);

			var refGroup = refModel.getGroup();
			var citation = ( refGroup && refGroup.length ? refGroup + ' ' : '' ) + n;
			// Use [\s\S]* instead of .* to catch esoteric whitespace (T263698)
			var matches = refModel.getListKey().match( /^literal\/([\s\S]*)$/ );
			var name = matches && matches[ 1 ] || '';
			// Hide previously auto-generated reference names
			if ( /^:[0-9]+$/.test( name ) ) {
				name = '';
			}

			// TODO: At some point we need to make sure this text is updated in
			// case the view node is still rendering. This shouldn't happen because
			// all references are supposed to be in the store and therefore are
			// immediately rendered, but we shouldn't trust that on principle to
			// account for edge cases.

			// Make visible text, citation and reference name searchable
			text = [ view.$element.text().toLowerCase(), citation, name ].join( ' ' );
			// Make URLs searchable
			// eslint-disable-next-line no-loop-func
			view.$element.find( 'a[href]' ).each( function () {
				text += ' ' + this.getAttribute( 'href' );
			} );

			this.index.push( {
				$element: view.$element,
				text: text,
				reference: refModel,
				citation: citation,
				name: name
			} );
		}
	}

	// Re-populate
	this.onQueryChange();

	this.built = true;
};

/**
 * Check whether buildIndex will create an empty index based on the current internalList.
 *
 * @return {boolean} Index is empty
 */
ve.ui.MWReferenceSearchWidget.prototype.isIndexEmpty = function () {
	return this.indexEmpty;
};

/**
 * Handle media query response events.
 *
 * @method
 */
ve.ui.MWReferenceSearchWidget.prototype.addResults = function () {
	var query = this.query.getValue().trim().toLowerCase(),
		items = [];

	for ( var i = 0, len = this.index.length; i < len; i++ ) {
		var item = this.index[ i ];
		if ( item.text.indexOf( query ) >= 0 ) {
			var $citation = $( '<div>' )
				.addClass( 've-ui-mwReferenceSearchWidget-citation' )
				.text( '[' + item.citation + ']' );
			var $name = $( '<div>' )
				.addClass( 've-ui-mwReferenceSearchWidget-name' )
				.text( item.name );
			items.push(
				new ve.ui.MWReferenceResultWidget( {
					data: item.reference,
					label: $citation.add( $name ).add( item.$element )
				} )
			);
		}
	}

	this.results.addItems( items );
};
