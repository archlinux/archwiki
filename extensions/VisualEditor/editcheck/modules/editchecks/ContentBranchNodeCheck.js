/*!
 * VisualEditor ContentBranchNodeCheck class.
 *
 * @copyright See AUTHORS.txt
 */

/**
 * Abstract mixin for any Edit Check that works on a per-paragraph basis,
 * independent of the content in other nodes. It handles:
 * - checking paragraph-by-paragraph
 * - caching the results of each paragraph on a per-node basis
 * - filtering to show actions only for modified paragraphs
 * - filtering to show actions only for ranges that have not been dismissed
 *
 * @class
 * @abstract
 * @constructor
 */
mw.editcheck.ContentBranchNodeCheck = function MWContentBranchNodeCheck() {
};

/**
 * Find any ranges in the node that contain content that should trigger this edit check
 *
 * @abstract
 * @return {mw.editcheck.EditCheckAction[]|Promise[]|mw.editcheck.EditCheckAction|Promise} Action, or Promise which resolves with an Action or null
 */
mw.editcheck.ContentBranchNodeCheck.prototype.checkNode = null;

/**
 * Get actions to show when document changed
 *
 * @abstract
 * @param {ve.dm.Surface} surfaceModel
 * @return {Promise[]} Action, Promises that resolve with a mw.editcheck.EditCheckAction or null
 */
mw.editcheck.ContentBranchNodeCheck.prototype.onDocumentChange = function ( surfaceModel ) {
	const actions = [];
	const doc = surfaceModel.getDocument();
	const cacheKey = this.constructor.static.name + '#checkNode';

	const modified = this.getModifiedRanges( doc, false, false, false );

	doc.getNodesByType( ve.dm.ContentBranchNode ).forEach( ( node ) => {
		const nodeRange = node.getRange();
		// Short circuit on any unmodified node
		if ( !modified.some( ( modifiedRange ) => modifiedRange.overlapsRange( nodeRange ) ) ) {
			return;
		}
		// Perform simple check on the whole node and cache the result
		// The purpose of this approach is for checkNode to be simple to write and simple to cache
		const nodeActions = doc.getOrInsertCachedData(
			node,
			( () => this.checkNode( node, surfaceModel ) ),
			cacheKey
		).map( ( actionOrPromise ) => Promise.resolve( actionOrPromise ).then( ( action ) => {
			if ( action.isDismissed() ) {
				return null;
			}
			if ( !action.overlapsRanges( modified ) ) {
				// Filter out actions on unmodified ranges within the modified node
				// TODO: rewrite to ensure `modified` didn't change during async
				return null;
			}
			return action;
		} ) );
		actions.push( ...nodeActions );
	} );
	return actions;
};
