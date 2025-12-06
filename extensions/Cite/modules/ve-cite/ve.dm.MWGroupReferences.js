'use strict';

/*!
 * @copyright 2024 VisualEditor Team's Cite sub-team and others; see AUTHORS.txt
 * @license MIT
 */

/**
 * Holds information about the refs from a single Cite group.
 *
 * This structure is persisted in memory until a document change affects a ref
 * tag from this group, at which point it will be fully recalculated.
 *
 * @constructor
 */
ve.dm.MWGroupReferences = function VeDmMWGroupReferences() {
	// Mixin constructors
	OO.EventEmitter.call( this );

	// Properties
	/**
	 * Lookup from listKey to a pair of integers which are the [major, minor] footnote numbers
	 * that will be rendered on the ref in some digit system.  Note that top-level refs always
	 * have minor number `-1`.
	 *
	 * @member {Object.<string, number[]>}
	 */
	this.footnoteNumberLookup = {};

	/**
	 * Lookup from listKey to a rendered footnote number or subref number like "1.2", in the
	 * local content language.
	 *
	 * FIXME: push labeling to presentation code and drop from here.
	 *
	 * @member {Object.<string, string>}
	 */
	this.footnoteLabelLookup = {};

	/**
	 * Lookup from a main reference's listKey to the corresponding sub-refs.
	 *
	 * @member {Object.<string, ve.dm.MWReferenceNode[]>}
	 * @private
	 */
	this.subRefsByMain = {};

	/** @private */
	this.topLevelCounter = 1;

	/**
	 * InternalList node group, or null if no such group exists.
	 *
	 * @member {ve.dm.InternalListNodeGroup|null}
	 * @private
	 */
	this.nodeGroup = null;
};

/* Inheritance */

OO.initClass( ve.dm.MWGroupReferences );

/* Static Methods */

/**
 * Rebuild information about this group of references.
 *
 * @param {ve.dm.InternalListNodeGroup|undefined} nodeGroup
 * @return {ve.dm.MWGroupReferences}
 */
ve.dm.MWGroupReferences.static.makeGroupRefs = function ( nodeGroup ) {
	const result = new ve.dm.MWGroupReferences();
	if ( !nodeGroup ) {
		return result;
	}
	result.nodeGroup = nodeGroup;

	nodeGroup.getFirstNodesInIndexOrder()
		.filter( ( node ) => !node.getAttribute( 'placeholder' ) )
		.forEach( ( node ) => {
			const listKey = node.getAttribute( 'listKey' );
			const mainRefKey = node.getAttribute( 'mainRefKey' );
			const groupItemIndex = ( mainRefKey ?
				result.addSubref( mainRefKey, listKey, node ) :
				[ result.getOrAllocateTopLevelIndex( listKey ), -1 ]
			);

			const reuseNodes = nodeGroup.getAllReuses( listKey );
			if ( reuseNodes ) {
				reuseNodes.forEach( ( refNode ) => refNode.setGroupIndex( groupItemIndex ) );
			}
		} );

	return result;
};

/* Methods */

/**
 * @private
 * @param {string} listKey Full key for the top-level ref
 * @return {number} Allocated topLevelIndex
 */
ve.dm.MWGroupReferences.prototype.getOrAllocateTopLevelIndex = function ( listKey ) {
	if ( !( listKey in this.footnoteNumberLookup ) ) {
		const number = this.topLevelCounter++;
		this.footnoteNumberLookup[ listKey ] = [ number, -1 ];
		this.footnoteLabelLookup[ listKey ] = ve.dm.MWDocumentReferences.static.contentLangDigits( number );
	}
	return this.footnoteNumberLookup[ listKey ][ 0 ];
};

/**
 * @private
 * @param {string} mainRefKey Full key of the main reference
 * @param {string} subRefKey Full key of the sub-reference
 * @param {ve.dm.MWReferenceNode} subRefNode Sub-reference to add to internal tracking
 * @return {number[]}
 */
ve.dm.MWGroupReferences.prototype.addSubref = function ( mainRefKey, subRefKey, subRefNode ) {
	if ( !( mainRefKey in this.subRefsByMain ) ) {
		this.subRefsByMain[ mainRefKey ] = [];
	}
	this.subRefsByMain[ mainRefKey ].push( subRefNode );
	const subRefIndex = this.subRefsByMain[ mainRefKey ].length;

	const topLevelIndex = this.getOrAllocateTopLevelIndex( mainRefKey );
	this.footnoteNumberLookup[ subRefKey ] = [ topLevelIndex, subRefIndex ];
	this.footnoteLabelLookup[ subRefKey ] = ve.dm.MWDocumentReferences.static.contentLangDigits( topLevelIndex ) +
		// FIXME: RTL, and customization of the separator like with mw:referencedBy
		'.' + ve.dm.MWDocumentReferences.static.contentLangDigits( subRefIndex );

	return this.footnoteNumberLookup[ subRefKey ];
};

/**
 * Check whether the group has any references.
 *
 * @deprecated use {@link ve.dm.InternalListNodeGroup.isEmpty} instead
 * @return {boolean}
 */
ve.dm.MWGroupReferences.prototype.isEmpty = function () {
	return !this.nodeGroup || this.nodeGroup.isEmpty();
};

/**
 * List all document references in the order they first appear, ignoring reuses
 * and placeholders.
 *
 * @return {ve.dm.MWReferenceNode[]}
 */
ve.dm.MWGroupReferences.prototype.getAllRefsInReflistOrder = function () {
	return Object.keys( this.footnoteNumberLookup )
		.sort( ( aKey, bKey ) => this.footnoteNumberLookup[ aKey ][ 0 ] - this.footnoteNumberLookup[ bKey ][ 0 ] )
		.map( ( listKey ) => this.nodeGroup.getAllReuses( listKey ) )
		.filter( ( nodes ) => !!nodes )
		.map( ( nodes ) => nodes[ 0 ] );
};

/**
 * List all reference listKeys in the order they appear in the reflist including
 * named refs, unnamed refs, and those that don't resolve
 *
 * @return {string[]} Reference listKeys
 */
ve.dm.MWGroupReferences.prototype.getTopLevelKeysInReflistOrder = function () {
	// FIXME: This should use this.nodeGroup.getKeysInIndexOrder(), but tests fail. Why?
	return Object.keys( this.footnoteNumberLookup )
		.sort( ( aKey, bKey ) => this.footnoteNumberLookup[ aKey ][ 0 ] - this.footnoteNumberLookup[ bKey ][ 0 ] )
		// TODO: Function could be split here, if a use case is found for a list of
		// all numbers including subrefs.
		.filter( ( listKey ) => this.footnoteNumberLookup[ listKey ][ 1 ] === -1 );
};

/**
 * Return the defining reference node for this key
 *
 * @see #getInternalModelNode
 *
 * @deprecated use {@link ve.dm.InternalListNodeGroup.getFirstNode} instead
 * @param {string} key in listKey format
 * @return {ve.dm.MWReferenceNode|undefined}
 */
ve.dm.MWGroupReferences.prototype.getRefNode = function ( key ) {
	return this.nodeGroup && this.nodeGroup.getFirstNode( key );
};

/**
 * Return the internalList internal item if it exists.
 *
 * @see #getRefNode
 *
 * @param {string} key in listKey format
 * @return {ve.dm.InternalItemNode|undefined}
 */
ve.dm.MWGroupReferences.prototype.getInternalModelNode = function ( key ) {
	const ref = this.getRefNode( key );
	return ref && ref.getInternalItem();
};

/**
 * Return document nodes for each usage of a ref key.  This excludes usages
 * under the `<references>` section, so note that nested references won't behave
 * as expected.  The reflist item for a ref is not counted as a reference,
 * either.
 *
 * FIXME: Implement backlinks from within a nested ref within the footnote body.
 *
 * @param {string} key in listKey format
 * @return {ve.dm.MWReferenceNode[]}
 */
ve.dm.MWGroupReferences.prototype.getRefUsages = function ( key ) {
	return ( this.nodeGroup && this.nodeGroup.getAllReuses( key ) || [] )
		.filter( ( node ) => !node.getAttribute( 'placeholder' ) &&
		// FIXME: Couldn't resolve this so far because of a circular dependency!
				!node.findParent( ve.dm.MWReferencesListNode )
		);
};

/**
 * Get the total number of usages for a reference, including sub-references.
 *
 * @param {string} listKey Full key of the reference
 * @return {number} Total usage count of main refs and subrefs
 */
ve.dm.MWGroupReferences.prototype.getTotalUsageCount = function ( listKey ) {
	const mainRefs = this.getRefUsages( listKey );
	let usageCount = mainRefs.length;

	this.getSubrefs( listKey ).forEach( ( node ) => {
		usageCount += this.getRefUsages( node.getAttribute( 'listKey' ) ).length;
	} );

	return usageCount;
};

/**
 * @param {string} mainRefKey
 * @return {ve.dm.MWReferenceNode[]} List of subrefs for this parent not including re-uses
 */
ve.dm.MWGroupReferences.prototype.getSubrefs = function ( mainRefKey ) {
	return this.subRefsByMain[ mainRefKey ] || [];
};

/**
 * @deprecated TODO: push to presentation
 * @param {string} listKey full ref key
 * @return {string|undefined} rendered number label
 */
ve.dm.MWGroupReferences.prototype.getIndexLabel = function ( listKey ) {
	return this.footnoteLabelLookup[ listKey ];
};

module.exports = ve.dm.MWGroupReferences;
