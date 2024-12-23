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
	 * Lookup from parent listKey to subrefs.
	 *
	 * @member {Object.<string, ve.dm.MWReferenceNode[]>}
	 */
	this.subRefsByParent = {};

	/** @private */
	this.topLevelCounter = 1;
	/**
	 * InternalList node group, or null if no such group exists.
	 *
	 * @member {Object|null}
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
 * @param {Object} nodeGroup InternalList group object containing refs.
 * @return {ve.dm.MWGroupReferences}
 */
ve.dm.MWGroupReferences.static.makeGroupRefs = function ( nodeGroup ) {
	const result = new ve.dm.MWGroupReferences();
	result.nodeGroup = nodeGroup;

	( nodeGroup ? nodeGroup.indexOrder : [] )
		.map( ( index ) => nodeGroup.firstNodes[ index ] )
		// FIXME: debug null nodes
		.filter( ( node ) => node && !node.getAttribute( 'placeholder' ) )
		.forEach( ( node ) => {
			const listKey = node.getAttribute( 'listKey' );
			const extendsRef = node.getAttribute( 'extendsRef' );

			if ( !extendsRef ) {
				result.getOrAllocateTopLevelIndex( listKey );
			} else {
				result.addSubref( extendsRef, listKey, node );
			}
		} );

	return result;
};

/* Methods */

/**
 * @private
 * @param {string} listKey Full key for the top-level ref
 * @return {number[]} Allocated topLevelIndex
 */
ve.dm.MWGroupReferences.prototype.getOrAllocateTopLevelIndex = function ( listKey ) {
	if ( this.footnoteNumberLookup[ listKey ] === undefined ) {
		const number = this.topLevelCounter++;
		this.footnoteNumberLookup[ listKey ] = [ number, -1 ];
		this.footnoteLabelLookup[ listKey ] = ve.dm.MWDocumentReferences.static.contentLangDigits( number );
	}
	return this.footnoteNumberLookup[ listKey ][ 0 ];
};

/**
 * @private
 * @param {string} parentKey Full key of the parent reference
 * @param {string} listKey Full key of the subreference
 * @param {ve.dm.MWReferenceNode} subrefNode Subref to add to internal tracking
 */
ve.dm.MWGroupReferences.prototype.addSubref = function ( parentKey, listKey, subrefNode ) {
	if ( this.subRefsByParent[ parentKey ] === undefined ) {
		this.subRefsByParent[ parentKey ] = [];
	}
	this.subRefsByParent[ parentKey ].push( subrefNode );
	const subrefIndex = this.subRefsByParent[ parentKey ].length;

	const topLevelIndex = this.getOrAllocateTopLevelIndex( parentKey );
	this.footnoteNumberLookup[ listKey ] = [ topLevelIndex, subrefIndex ];
	this.footnoteLabelLookup[ listKey ] = ve.dm.MWDocumentReferences.static.contentLangDigits( topLevelIndex ) +
		// FIXME: RTL, and customization of the separator like with mw:referencedBy
		'.' + ve.dm.MWDocumentReferences.static.contentLangDigits( subrefIndex );
};

/**
 * Check whether the group has any references.
 *
 * @return {boolean}
 */
ve.dm.MWGroupReferences.prototype.isEmpty = function () {
	// Use an internal shortcut, otherwise we could do something like
	// !!nodes.indexOrder.length
	return this.topLevelCounter === 1;
};

/**
 * List all document references in the order they first appear, ignoring reuses
 * and placeholders.
 *
 * @return {ve.dm.MWReferenceNode[]}
 */
ve.dm.MWGroupReferences.prototype.getAllRefsInDocumentOrder = function () {
	return Object.keys( this.footnoteNumberLookup )
		.sort( ( aKey, bKey ) => this.footnoteNumberLookup[ aKey ][ 0 ] - this.footnoteNumberLookup[ bKey ][ 0 ] )
		.map( ( listKey ) => this.nodeGroup.keyedNodes[ listKey ] )
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
 * @param {string} key in listKey format
 * @return {ve.dm.MWReferenceNode|undefined}
 */
ve.dm.MWGroupReferences.prototype.getRefNode = function ( key ) {
	const keyedNodes = this.nodeGroup && this.nodeGroup.keyedNodes[ key ];
	return keyedNodes && keyedNodes[ 0 ];
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
	return ( this.nodeGroup && this.nodeGroup.keyedNodes[ key ] || [] )
		.filter( ( node ) => !node.getAttribute( 'placeholder' ) &&
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
	const mainRefsCount = mainRefs.length;

	const subrefs = this.getSubrefs( listKey );
	const subrefsCount = subrefs.length;

	const totalUsageCount = mainRefsCount + subrefsCount;

	return totalUsageCount;
};

/**
 * @param {string} parentKey parent ref key
 * @return {ve.dm.MWReferenceNode[]} List of subrefs for this parent
 */
ve.dm.MWGroupReferences.prototype.getSubrefs = function ( parentKey ) {
	return this.subRefsByParent[ parentKey ] || [];
};

/**
 * @deprecated TODO: push to presentation
 * @param {string} listKey full ref key
 * @return {string} rendered number label
 */
ve.dm.MWGroupReferences.prototype.getIndexLabel = function ( listKey ) {
	return this.footnoteLabelLookup[ listKey ];
};
