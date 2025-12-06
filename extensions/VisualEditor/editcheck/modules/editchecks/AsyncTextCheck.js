/*!
 * EditCheck ContentBranchNode check class
 *
 * @copyright See AUTHORS.txt
 */

/**
 * Abstract class for async checks that run on the plain text of a ContentBranchNode.
 *
 * The check runs when selection focus changes branch node, or on pre-save. The subclass
 * implements `.checkAsync`. This is called with the ContentBranchNode's plain text
 * and returns an outcome, which gets processed by the subclass's `.newAction` method,
 * returning a new EditCheckAction if appropriate.
 *
 * @class
 * @constructor
 * @param {mw.editcheck.Controller} controller
 * @param {Object} [config]
 */
mw.editcheck.AsyncTextCheck = function MWAsyncTextCheck() {
	// Parent constructor
	mw.editcheck.AsyncTextCheck.super.apply( this, arguments );

	// Construct memoizeCheckAsync if running for the first time for this constructor
	if ( this.constructor.static.memoizedCheckAsync === null ) {
		this.constructor.static.memoizedCheckAsync = mw.editcheck.memoize(
			( text ) => this.constructor.static.checkAsync( text )
		);
	}
};

/* Inheritance */

OO.inheritClass( mw.editcheck.AsyncTextCheck, mw.editcheck.BaseEditCheck );

/* Static properties */

mw.editcheck.AsyncTextCheck.static.name = null;

mw.editcheck.AsyncTextCheck.static.canBeStale = true;

/**
 * This static method gets implemented inside the AsyncTextCheck constructor. It
 * memoizes the subclass's static method `checkAsync`.
 *
 * @static
 * @param {string} text The text to check
 * @return {Promise|any} The outcome of the check
 */
mw.editcheck.AsyncTextCheck.static.memoizedCheckAsync = null;

/**
 * Perform a possibly-asynchronous check on the plaintext of a ContentBranchNode.
 *
 * The check must be deterministic; i.e. it must always return the same value for the same
 * arguments. This allows the result to be memoized.
 *
 * @abstract
 * @static
 * @param {string} text The plaintext of the ContentBranchNode
 * @return {Promise|any} The outcome of the check, to be passed into #newAction
 */
mw.editcheck.AsyncTextCheck.static.checkAsync = null;

/* Methods */

/**
 * @param {string} listener Type of listener, such as 'onBeforeSave' or 'onBranchNodeChange'
 * @param {ve.dm.Surface} surfaceModel The surface
 * @return {Promise[]} An array of promises containing either an action or null
 */
mw.editcheck.AsyncTextCheck.prototype.handleListener = function ( listener, surfaceModel ) {
	const documentModel = surfaceModel.getDocument();
	const selection = surfaceModel.getSelection();
	// TODO let currentBranchNode = null;
	if ( selection instanceof ve.dm.LinearSelection && listener !== 'onBeforeSave' ) {
		// TODO currentBranchNode = surfaceModel.getDocument().getBranchNodeFromOffset( selection.getRange().to );
	}

	const actionPromises = [];
	this.getModifiedContentBranchNodes( documentModel ).forEach( ( node ) => {
		const nodeFragment = new ve.dm.SurfaceFragment( surfaceModel, new ve.dm.LinearSelection( node.getRange() ) );
		const range = node.getRange();
		if (
			( listener === 'onBeforeSave' && this.isTaggedRange( 'interacted', range ) ) ||
			// mw.editcheck.hasAddedContentFailingToneCheck wants to check for any violations, even dismissed ones.
			// Used for tagging.
			( listener !== 'onCheckAll' && this.isDismissedRange( range ) )
		) {
			return;
		}

		actionPromises.push( this.checkText( documentModel.data.getText( true, node.getRange() ) ).then( ( outcome ) => {
			if ( !outcome ) {
				return null;
			}
			return this.newAction( nodeFragment, outcome );
		} ) );
	} );
	return actionPromises;
};

/**
 * @inheritdoc
 */
mw.editcheck.AsyncTextCheck.prototype.onBeforeSave = function ( ...args ) {
	return this.handleListener( 'onBeforeSave', ...args );
};

/**
 * @inheritdoc
 */
mw.editcheck.AsyncTextCheck.prototype.onBranchNodeChange = function ( ...args ) {
	return this.handleListener( 'onBranchNodeChange', ...args );
};

/**
 * Build an action (or not), depending on the outcome of #checkAsync
 *
 * Caution: The ContentBranchNode may have changed while waiting for #checkAsync to settle.
 * For example, the fragment could be empty if the ContentBranchNode has been deleted.
 *
 * @param {ve.dm.SurfaceFragment} fragment Fragment whose range was the ContentBranchNode
 * @param {any} outcome The outcome returned by #checkAsync
 * @return {mw.editcheck.EditCheckAction|null} A new action if appropriate, else null
 */
mw.editcheck.AsyncTextCheck.prototype.newAction = null;

/**
 * Check provided text
 *
 * @param {string} text
 * @param {boolean} [bypass] Whether to bypass any memoized result
 * @return {Promise}
 */
mw.editcheck.AsyncTextCheck.prototype.checkText = function ( text, bypass ) {
	return Promise.resolve(
		this.constructor.static.memoizedCheckAsync( text, bypass )
	).then(
		( outcome ) => this.afterMemoized( outcome )
	);
};

/**
 * A filter to apply after the memoized call has occurred
 *
 * This is where instance-specific configuration would be applied
 *
 * @param {any} outcome The outcome returned by #checkAsync
 * @return {any}
 */
mw.editcheck.AsyncTextCheck.prototype.afterMemoized = function ( outcome ) {
	return outcome;
};

/**
 * @inheritdoc
 */
mw.editcheck.AsyncTextCheck.prototype.act = function ( choice, action, surface ) {
	this.tag( 'interacted', action );
	if ( choice === 'dismiss' ) {
		this.dismiss( action );
		// HACK: Recalculate check list
		this.controller.updateForListener( 'onBranchNodeChange' );
		return ve.createDeferred().resolve( { action: choice } ).promise();
	} else if ( choice === 'edit' && surface ) {
		return this.controller.closeDialog().then( () => {
			surface.getView().activate();
			action.fragments[ action.fragments.length - 1 ].collapseToEnd().select();
		} );
	}
};
