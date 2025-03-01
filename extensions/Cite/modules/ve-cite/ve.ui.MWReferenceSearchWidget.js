'use strict';

/*!
 * VisualEditor UserInterface MWReferenceSearchWidget class.
 *
 * @copyright 2011-2018 VisualEditor Team's Cite sub-team and others; see AUTHORS.txt
 * @license MIT
 */

/**
 * Creates an ve.ui.MWReferenceSearchWidget object.
 *
 * @constructor
 * @extends OO.ui.SearchWidget
 * @param {Object} config Configuration options
 * @param {jQuery} config.$overlay Layer to render reuse submenu outside of the parent dialog
 * @property {Object[]|null} index Null when the index needs to be rebuild
 */
ve.ui.MWReferenceSearchWidget = function VeUiMWReferenceSearchWidget( config ) {
	// Configuration initialization
	config = ve.extendObject( {
		placeholder: ve.msg( 'cite-ve-reference-input-placeholder' )
	}, config );

	// Parent constructor
	ve.ui.MWReferenceSearchWidget.super.call( this, config );

	// Properties
	this.docRefs = null;
	this.index = null;
	this.wasUsedActively = false;
	this.$overlay = config.$overlay;

	// Initialization
	this.$element.addClass( 've-ui-mwReferenceSearchWidget' );
	this.$results.on( 'scroll', this.trackActiveUsage.bind( this ) );
	this.getResults().connect( this, { choose: 'onChoose' } );

	// FIXME: T375053 Should just be temporary to test some UI changes
	if ( mw.config.get( 'wgCiteBookReferencing' ) ) {
		this.$element.addClass( 've-ui-mwReferenceSearchReuseHacks' );
	}
};

/* Inheritance */

OO.inheritClass( ve.ui.MWReferenceSearchWidget, OO.ui.SearchWidget );

/* Events */

/**
 * User chose a ref for reuse
 *
 * @event ve.ui.MWReferenceSearchWidget#reuse
 * @param {ve.dm.MWReferenceModel} ref
 */

/**
 * Extends popup menu item was clicked
 *
 * @event ve.ui.MWReferenceSearchWidget#extends
 * @param {ve.dm.MWReferenceModel} ref
 */

/* Methods */

ve.ui.MWReferenceSearchWidget.prototype.onQueryChange = function () {
	// Parent method
	ve.ui.MWReferenceSearchWidget.super.prototype.onQueryChange.call( this );

	// Populate
	this.getResults().addItems( this.buildSearchResults( this.getQuery().getValue() ) );
};

/**
 * @param {jQuery.Event} e Key down event
 */
ve.ui.MWReferenceSearchWidget.prototype.onQueryKeydown = function ( e ) {
	// Parent method
	ve.ui.MWReferenceSearchWidget.super.prototype.onQueryKeydown.call( this, e );

	this.trackActiveUsage();
};

ve.ui.MWReferenceSearchWidget.prototype.clearSearch = function () {
	this.getQuery().setValue( '' );
	this.wasUsedActively = false;
};

/**
 * Track when the user looks for references to reuse using scrolling or filtering results
 */
ve.ui.MWReferenceSearchWidget.prototype.trackActiveUsage = function () {
	if ( this.wasUsedActively ) {
		return;
	}

	// https://phabricator.wikimedia.org/T362347
	ve.track( 'activity.reference', { action: 'reuse-dialog-use' } );
	this.wasUsedActively = true;
};

ve.ui.MWReferenceSearchWidget.prototype.onChoose = function ( item ) {
	this.emit( 'reuse', item.getData() );
};

/**
 * Set the internal list and check if it contains any references
 *
 * @param {ve.dm.MWDocumentReferences} docRefs handle to all refs in the original document
 */
ve.ui.MWReferenceSearchWidget.prototype.setDocumentRefs = function ( docRefs ) {
	this.results.unselectItem();

	this.docRefs = docRefs;
};

/**
 * Set the internal list and check if it contains any references
 *
 * @deprecated use #setDocumentRefs instead.
 * @param {ve.dm.InternalList} internalList
 */
ve.ui.MWReferenceSearchWidget.prototype.setInternalList = function ( internalList ) {
	this.setDocumentRefs( ve.dm.MWDocumentReferences.static.refsForDoc( internalList.getDocument() ) );
};

/**
 * Manually re-build the index and re-populate the list of search results.
 */
ve.ui.MWReferenceSearchWidget.prototype.buildIndex = function () {
	this.index = null;
	this.onQueryChange();
};

/**
 * @private
 * @return {Object[]}
 */
ve.ui.MWReferenceSearchWidget.prototype.buildSearchIndex = function () {
	const groupNames = this.docRefs.getAllGroupNames().sort();

	// FIXME: Temporary hack, to be removed soon
	// eslint-disable-next-line no-jquery/no-class-state
	const filterExtends = this.$element.hasClass( 've-ui-citoidInspector-extends' );

	let index = [];
	for ( let i = 0; i < groupNames.length; i++ ) {
		const groupName = groupNames[ i ];
		if ( groupName.indexOf( 'mwReference/' ) !== 0 ) {
			// FIXME: Should be impossible to reach
			continue;
		}
		const groupRefs = this.docRefs.getGroupRefs( groupName );
		const flatNodes = groupRefs.getAllRefsInDocumentOrder()
			.filter( ( node ) => !filterExtends || !node.getAttribute( 'extendsRef' ) );

		index = index.concat( flatNodes.map( ( node ) => {
			const listKey = node.getAttribute( 'listKey' );
			// remove `mwReference/` prefix
			const group = groupName.slice( 12 );
			const footnoteNumber = this.docRefs.getIndexLabel( group, listKey );
			const footnoteLabel = ( group ? group + ' ' : '' ) + footnoteNumber;

			// Use [\s\S]* instead of .* to catch esoteric whitespace (T263698)
			const matches = listKey.match( /^literal\/([\s\S]*)$/ );
			const name = matches && matches[ 1 ] || '';

			let $refContent;
			// Make visible text, footnoteLabel and reference name searchable
			let refText = ( footnoteLabel + ' ' + name ).toLowerCase();
			const itemNode = groupRefs.getInternalModelNode( listKey );
			if ( itemNode.length ) {
				$refContent = new ve.ui.MWPreviewElement( itemNode, { useView: true } ).$element;
				refText = $refContent.text().toLowerCase() + ' ' + refText;
				// Make URLs searchable
				$refContent.find( 'a[href]' ).each( ( k, element ) => {
					refText += ' ' + element.getAttribute( 'href' );
				} );
			} else {
				$refContent = $( '<span>' )
					.addClass( 've-ce-mwReferencesListNode-muted' )
					.text( ve.msg( 'cite-ve-referenceslist-missingref-in-list' ) );
			}

			return {
				$refContent: $refContent,
				searchableText: refText,
				// TODO: return a simple node
				reference: ve.dm.MWReferenceModel.static.newFromReferenceNode( node ),
				footnoteLabel: footnoteLabel,
				isSubRef: !!node.getAttribute( 'extendsRef' ),
				name: name
			};
		} ) );
	}

	return index;
};

/**
 * @return {boolean} Index is empty
 */
ve.ui.MWReferenceSearchWidget.prototype.isIndexEmpty = function () {
	return !this.docRefs.hasRefs();
};

/**
 * @private
 * @param {ve.dm.MWReferenceModel} ref
 * @return {OO.ui.ButtonMenuSelectWidget}
 */
ve.ui.MWReferenceSearchWidget.prototype.buildReuseOptionsMenu = function ( ref ) {
	// TODO: Select the row on menu button click, so we don't have to wire ref
	// through the closure.
	const reuseOptionsMenu = new OO.ui.ButtonMenuSelectWidget( {
		classes: [ 've-ui-mwReferenceResultsReuseOptions' ],
		framed: false,
		icon: 'ellipsis',
		// TODO: The […] button should have its own title, see T375053
		title: '',
		invisibleLabel: true,
		menu: {
			classes: [ 've-ui-mwReferenceResultsReuseOptionsItem' ],
			horizontalPosition: 'end',
			items: [
				new OO.ui.MenuOptionWidget( {
					data: 'reuse',
					label: ve.msg( 'cite-ve-dialog-reference-useexisting-long-tool' )
				} ),
				new OO.ui.MenuOptionWidget( {
					data: 'extends',
					label: ve.msg( 'cite-ve-dialog-reference-extend-long-tool' )
				} )
			]
		},
		// FIXME: Overlay clips to the dialog, should be full-screen.
		$overlay: this.$overlay
	} );

	// Hack to prevent a menu button click from being handled by the top-level
	// select item.
	reuseOptionsMenu.$element.on( 'mousedown', ( e ) => {
		e.stopPropagation();
	} );

	reuseOptionsMenu.getMenu().on( 'choose', ( menuOption ) => {
		// Emit 'reuse' or 'extends' events, with the chosen ref.
		this.emit( menuOption.getData(), ref );
	} );

	return reuseOptionsMenu;
};

/**
 * @private
 * @param {string} query
 * @return {ve.ui.MWReferenceResultWidget[]}
 */
ve.ui.MWReferenceSearchWidget.prototype.buildSearchResults = function ( query ) {
	query = query.trim().toLowerCase();
	const results = [];

	if ( !this.index ) {
		this.index = this.buildSearchIndex();
	}

	for ( let i = 0; i < this.index.length; i++ ) {
		const item = this.index[ i ];
		if ( item.searchableText.indexOf( query ) >= 0 ) {
			results.push(
				new ve.ui.MWReferenceResultWidget( {
					item: item,
					reuseMenu: item.isSubRef ? undefined : this.buildReuseOptionsMenu( item.reference )
				} )
			);
		}
	}

	return results;
};
