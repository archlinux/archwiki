'use strict';

/*!
 * @copyright 2026 VisualEditor Team's Cite sub-team and others; see AUTHORS.txt
 * @license MIT
 */

/**
 * A facade providing a safe interface to wrap methods using a reference's listIndex
 * each with a fallback using listKey and listGroup.
 *
 * @constructor
 */
ve.dm.MWDataTransitionHelper = function VeDmMWDataTransitionHelper() {
};

/**
 * @typedef {Object} ve.dm.MWDataTransitionHelper.RefInfo
 * @property {number} internalListIndex list index of the ve.dm.InternalItemNode
 *   in a ve.dm.InternalList.
 * @property {number} [mainListIndex] List index of a sub-reference's parent, or
 *   omitted for a main reference.
 * @property {number} topLevelNumber Main footnote number.  For a sub-reference,
 *   this is the number of the parent.
 * @property {number} [subrefNumber] Sub-reference footnote number, or omitted
 *   for a main reference.
 * @property {string} label Rendered full footnote number, in the wiki's content
 *   language script.
 * @property {Array.<ve.dm.MWDataTransitionHelper.RefInfo>} [subrefs] Only
 *   included in the "buildReflistStructure" output flavor. This is a list of
 *   sub-references on a main ref, in document order.
 */

/**
 * @param {ve.dm.InternalListNodeGroup|undefined} nodeGroup
 * @return {Object.<string, ve.dm.MWDataTransitionHelper.RefInfo>} footnote number lookup
 */
ve.dm.MWDataTransitionHelper.prototype.buildReflistNumbering = function ( nodeGroup ) {
	const footnoteNumberLookup = {};
	const subRefsByMain = {};
	let topLevelCounter = 1;

	const getOrAllocateTopLevelNumber = function ( mainListKey, listIndex ) {
		if ( !( listIndex in footnoteNumberLookup ) ) {
			const number = topLevelCounter++;
			footnoteNumberLookup[ listIndex ] = {
				// TODO: Can we eventually phase the string listKey out?
				internalListKey: mainListKey,
				internalListIndex: listIndex,
				topLevelNumber: number,
				label: ve.dm.MWDocumentReferences.static.contentLangDigits( number )
			};
		}
		return footnoteNumberLookup[ listIndex ].topLevelNumber;
	};

	const addSubref = function ( mainListKey, mainListIndex, subRefIndex, subRefNode ) {
		if ( !( mainListIndex in subRefsByMain ) ) {
			subRefsByMain[ mainListIndex ] = [];
		}
		subRefsByMain[ mainListIndex ].push( subRefNode );
		const subRefPos = subRefsByMain[ mainListIndex ].length;

		const topLevelNumber = getOrAllocateTopLevelNumber( mainListKey, mainListIndex );
		footnoteNumberLookup[ subRefIndex ] = {
			internalListIndex: subRefIndex,
			mainListIndex: mainListIndex,
			topLevelNumber: topLevelNumber,
			subrefNumber: subRefPos,
			label: ve.dm.MWDocumentReferences.static.contentLangDigits( topLevelNumber ) +
				// FIXME: RTL, and customization of the separator like with mw:referencedBy
				'.' + ve.dm.MWDocumentReferences.static.contentLangDigits( subRefPos )
		};

		return footnoteNumberLookup[ subRefIndex ];
	};

	if ( nodeGroup ) {
		nodeGroup.getFirstNodesInIndexOrder()
			.filter( ( node ) => !node.getAttribute( 'placeholder' ) )
			.forEach( ( node ) => {
				const listIndex = node.getAttribute( 'listIndex' );
				const mainListKey = node.getAttribute( 'mainListKey' ) || node.getAttribute( 'listKey' );
				const mainListIndex = node.getAttribute( 'mainListIndex' );
				if ( mainListIndex !== undefined ) {
					addSubref( mainListKey, mainListIndex, listIndex, node );
				} else {
					getOrAllocateTopLevelNumber( mainListKey, listIndex );
				}
			} );
	}

	return footnoteNumberLookup;
};

/**
 * @param {ve.dm.InternalListNodeGroup|undefined} nodeGroup
 * @return {Array.<ve.dm.MWDataTransitionHelper.RefInfo>} List of main refs in
 *   document order, including `subrefs` field containing the sub-references
 *   under that main reference.
 */
ve.dm.MWDataTransitionHelper.prototype.buildReflistStructure = function ( nodeGroup ) {
	const footnoteNumberLookup = this.buildReflistNumbering( nodeGroup );

	// Get just the top-level refs.
	const topLevelIndexes = Object.keys( footnoteNumberLookup )
		.filter( ( listIndex ) => footnoteNumberLookup[ listIndex ].subrefNumber === undefined );

	// Build an array of top-level refs, and include their subrefs. Sort in footnote number order.
	const nestedRefs = [];

	topLevelIndexes
		.sort( ( a, b ) => footnoteNumberLookup[ a ].topLevelNumber - footnoteNumberLookup[ b ].topLevelNumber )
		.forEach( ( mainListIndex ) => {
			const subrefs =
			Object.keys( footnoteNumberLookup )
				// Get all subrefs of one main ref.
				.filter( ( listIndex ) => footnoteNumberLookup[ listIndex ].mainListIndex === Number( mainListIndex ) )
				// Put them in number order.
				.sort( ( a, b ) => footnoteNumberLookup[ a ].subrefNumber - footnoteNumberLookup[ b ].subrefNumber )
				// Get the lookup object for each subref.
				.map( ( subrefIndex ) => footnoteNumberLookup[ subrefIndex ] );

			nestedRefs.push( ve.extendObject( {}, footnoteNumberLookup[ mainListIndex ], { subrefs } ) );
		} );
	return nestedRefs;
};

module.exports = ve.dm.MWDataTransitionHelper;
