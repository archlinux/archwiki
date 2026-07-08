'use strict';

const MWDataTransitionHelper = require( './ve.dm.MWDataTransitionHelper.js' );

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
	 * @member {Object.<string, ve.dm.MWDataTransitionHelper.RefInfo>}
	 * @private
	 */
	this.calculatedNumbering = {};

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
	if ( nodeGroup ) {
		result.nodeGroup = nodeGroup;
		result.calculatedNumbering = new MWDataTransitionHelper().buildReflistNumbering( nodeGroup );
	}
	return result;
};

/**
 * Comparator for sorting references into reflist order, given two RefInfo objects
 *
 * @param {ve.dm.MWDataTransitionHelper.RefInfo} a Left term
 * @param {ve.dm.MWDataTransitionHelper.RefInfo} b Right term
 * @return {number} according to {@link Array.sort}
 */
ve.dm.MWGroupReferences.static.compareAsRefInfos = function ( a, b ) {
	return ( a.topLevelNumber - b.topLevelNumber ) ||
		( ( a.subrefNumber || 0 ) - ( b.subrefNumber || 0 ) );
};

/* Methods */

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
 * Return document nodes for each usage of a ref listIndex.  This excludes usages
 * under the `<references>` section, so note that nested references won't behave
 * as expected.  The reflist item for a ref is not counted as a reference,
 * either.
 *
 * FIXME: Implement backlinks from within a nested ref within the footnote body.
 *
 * @param {number} listIndex
 * @return {ve.dm.MWReferenceNode[]}
 */
ve.dm.MWGroupReferences.prototype.getRefUsages = function ( listIndex ) {
	return this.getAllReusesByListIndex( listIndex )
		.filter( ( node ) => !node.getAttribute( 'placeholder' ) &&
		// FIXME: Couldn't resolve this so far because of a circular dependency!
				!node.findParent( ve.dm.MWReferencesListNode )
		);
};

/**
 * Temporary helper as long as the upstream {@link ve.dm.InternalListNodeGroup} doesn't have a
 * better method for this.
 *
 * @deprecated use {@link ve.dm.InternalListNodeGroup.getAllReusesByListIndex} instead
 * @param {number} listIndex
 * @return {ve.dm.MWReferenceNode[]}
 */
ve.dm.MWGroupReferences.prototype.getAllReusesByListIndex = function ( listIndex ) {
	return ( this.nodeGroup && this.nodeGroup.getAllReusesByListIndex( listIndex ) ) || [];
};

/**
 * Get the total number of usages for a reference, including sub-references.
 *
 * @param {number} listIndex
 * @return {number} Total usage count of main refs and subrefs
 */
ve.dm.MWGroupReferences.prototype.getTotalUsageCount = function ( listIndex ) {
	const mainRefs = this.getRefUsages( listIndex );
	let usageCount = mainRefs.length;

	Object.values( this.calculatedNumbering ).forEach( ( refInfo ) => {
		if ( refInfo.mainListIndex === listIndex ) {
			usageCount += this.getRefUsages( refInfo.internalListIndex ).length;
		}
	} );

	return usageCount;
};

/**
 * Lookup from listIndex to a rendered footnote number or subref number like "1.2", in the
 * local content language.
 *
 * @deprecated TODO: push to presentation
 * @param {number} listIndex
 * @return {string} rendered number label
 */
ve.dm.MWGroupReferences.prototype.getIndexLabel = function ( listIndex ) {
	return ( this.calculatedNumbering[ listIndex ] ? this.calculatedNumbering[ listIndex ].label : '…' );
};

module.exports = ve.dm.MWGroupReferences;
