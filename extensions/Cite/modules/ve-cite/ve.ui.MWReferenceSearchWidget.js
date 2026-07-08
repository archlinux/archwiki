'use strict';

/*!
 * VisualEditor UserInterface MWReferenceSearchWidget class.
 *
 * @copyright 2011-2018 VisualEditor Team's Cite sub-team and others; see AUTHORS.txt
 * @license MIT
 */

const MWDocumentReferences = require( './ve.dm.MWDocumentReferences.js' );
const MWReferenceKeyGenerator = require( './ve.dm.MWReferenceKeyGenerator.js' );
const MWReferenceModel = require( './ve.dm.MWReferenceModel.js' );
const MWReferenceResultWidget = require( './ve.ui.MWReferenceResultWidget.js' );

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
	this.internalList = null;
	this.docRefs = null;
	this.index = null;
	this.wasUsedActively = false;
	this.$overlay = config.$overlay;

	// Initialization
	this.$element.addClass( 've-ui-mwReferenceSearchWidget' );
	this.$results.on( 'scroll', this.trackActiveUsage.bind( this ) );
	this.getResults().connect( this, { choose: 'onChoose' } );

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

/* Methods */

ve.ui.MWReferenceSearchWidget.prototype.onQueryChange = function () {
	// Parent method
	ve.ui.MWReferenceSearchWidget.super.prototype.onQueryChange.call( this );

	// Populate
	const results = this.getResults();
	results.addItems( this.buildSearchResults( this.getQuery().getValue() ) );
	// When there is only 1 search result anyway, highlight it right away
	if ( results.getItemCount() === 1 ) {
		results.highlightItem( results.findFirstSelectableItem() );
	}
};

/**
 * @param {jQuery.Event} e Key down event
 */
ve.ui.MWReferenceSearchWidget.prototype.onQueryKeydown = function ( e ) {
	// When the user tries to tab into the list of search results, highlight the first
	if ( e.which === OO.ui.Keys.TAB && !e.shiftKey &&
		!this.results.isEmpty() &&
		!this.results.findHighlightedItem()
	) {
		this.results.highlightItem( this.results.findFirstSelectableItem() );
		e.preventDefault();
	}

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
 * @param {ve.dm.InternalList} internalList
 */
ve.ui.MWReferenceSearchWidget.prototype.setInternalList = function ( internalList ) {
	this.results.unselectItem();
	this.internalList = internalList;
	this.docRefs = MWDocumentReferences.static.refsForDoc( internalList.getDocument() );
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
	const listGroups = this.docRefs.getListGroupNames().sort();

	let index = [];
	for ( const listGroup of listGroups ) {
		const nodeGroup = this.internalList.getNodeGroup( listGroup );
		const reflistStructure = Object.values( new ve.dm.MWDataTransitionHelper().buildReflistNumbering( nodeGroup ) )
			.sort( ve.dm.MWGroupReferences.static.compareAsRefInfos );

		// TODO: Why not use the original group attribute? Is that outdated after an edit?
		// remove `mwReference/` prefix
		const group = listGroup.slice( 12 );

		index = index.concat( reflistStructure.map( ( refInfo ) => {
			const footnoteLabel = ( group + ' ' + refInfo.label ).trim();

			// TODO: Why not use the original name attribute? Is that outdated after an edit?
			const name = MWReferenceKeyGenerator.extractNameFromListKey( refInfo.internalListKey );

			let $refContent;
			// Make visible text, footnoteLabel and reference name searchable
			let refText = ( '[' + footnoteLabel + '] ' + name ).trim();

			const itemNode = this.internalList.getItemNode( refInfo.internalListIndex );
			if ( itemNode && itemNode.getLength() ) {
				$refContent = new ve.ui.MWPreviewElement( itemNode, { useView: true } ).$element;
				refText += ' ' + $refContent.text();
				// Make URLs searchable
				$refContent.find( 'a[href]' ).each( ( _, element ) => {
					refText += ' ' + element.getAttribute( 'href' );
				} );
			} else {
				$refContent = $( '<span>' )
					.addClass( 've-ce-mwReferencesListNode-muted' )
					.text( ve.msg( 'cite-ve-referenceslist-missingref-in-list' ) );
			}

			const refNode = nodeGroup.getFirstNodeByListIndex( refInfo.internalListIndex );
			const reference = refNode ?
				MWReferenceModel.static.newFromReferenceNode( refNode ) :
				MWReferenceModel.static.newFromMainNodeAttributes(
					this.internalList.getDocument(),
					listGroup,
					refInfo.internalListKey,
					refInfo.internalListIndex
				);

			return {
				$refContent,
				searchableText: refText.toLowerCase(),
				reference,
				footnoteLabel,
				name
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
 * @param {string} query
 * @return {MWReferenceResultWidget[]}
 */
ve.ui.MWReferenceSearchWidget.prototype.buildSearchResults = function ( query ) {
	query = query.trim().toLowerCase();
	const results = [];

	if ( !this.index ) {
		this.index = this.buildSearchIndex();
	}

	// Arbitraryly limits the prefix search to something between [1] and [9999]
	let prefix = /^\S{1,4}$/.test( query ) ? '[' + query + ']' : null;

	this.index.forEach( ( item ) => {
		if ( item.searchableText.includes( query ) ) {
			const result = new MWReferenceResultWidget( { item } );
			// Push a perfect prefix match to the very top
			if ( prefix && item.searchableText.startsWith( prefix ) ) {
				results.unshift( result );
				prefix = null;
			} else {
				results.push( result );
			}
		}
	} );

	return results;
};

module.exports = ve.ui.MWReferenceSearchWidget;
