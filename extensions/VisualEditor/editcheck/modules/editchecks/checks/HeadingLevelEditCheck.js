/**
 * Edit check to detect headings that skip levels
 *
 * @class
 * @extends mw.editcheck.BaseEditCheck
 *
 * @constructor
 * @param {mw.editcheck.Controller} controller
 * @param {Object} [config]
 * @param {boolean} [includeSuggestions=false]
 */
mw.editcheck.HeadingLevelEditCheck = function MWHeadingLevelEditCheck() {
	// Parent constructor
	mw.editcheck.HeadingLevelEditCheck.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( mw.editcheck.HeadingLevelEditCheck, mw.editcheck.BaseEditCheck );

/* Static properties */

mw.editcheck.HeadingLevelEditCheck.static.defaultConfig = ve.extendObject( {}, mw.editcheck.HeadingLevelEditCheck.super.static.defaultConfig, {
	showAsCheck: false
} );

mw.editcheck.HeadingLevelEditCheck.static.name = 'headingLevel';

mw.editcheck.HeadingLevelEditCheck.static.title = OO.ui.deferMsg( 'editcheck-headinglevel-title' );

mw.editcheck.HeadingLevelEditCheck.static.description = ve.deferJQueryMsg( 'editcheck-headinglevel-description' );

mw.editcheck.HeadingLevelEditCheck.static.choices = [
	{
		action: 'fix',
		label: OO.ui.deferMsg( 'editcheck-headinglevel-action-adjust' ),
		icon: 'edit'
	},
	{
		action: 'dismiss',
		label: OO.ui.deferMsg( 'ooui-dialog-process-dismiss' )
	}
];

/* Methods */

mw.editcheck.HeadingLevelEditCheck.prototype.onDocumentChange = function ( surfaceModel ) {
	const modified = this.getModifiedContentRanges( surfaceModel.getDocument() );
	let previousLevel = null;
	const actions = [];
	// Rather than looking at every modified range and deciding whether it
	// includes a heading, it's a lot more efficient to just check every
	// heading:
	surfaceModel.documentModel.getNodesByType( ve.dm.MWHeadingNode, true ).forEach( ( heading ) => {
		const level = heading.getAttribute( 'level' );
		if ( previousLevel !== null && level ) {
			if ( ( level - previousLevel ) > 1 ) {
				// Note: Turning existing content into a heading just replaces
				// the paragraph with a mwHeading, so it's outside the node's
				// range, and the modification will be two ranges replacing
				// the opening/closing tags.
				const range = heading.getOuterRange();
				if ( !this.isDismissedRange( range ) && modified.some( ( modifiedRange ) => modifiedRange.touchesRange( range ) ) ) {
					actions.push( new mw.editcheck.EditCheckAction( {
						// But the better range for display is the content range:
						fragments: [ surfaceModel.getLinearFragment( heading.getRange() ) ],
						check: this
					} ) );
				}
			}
		}
		previousLevel = level;
	} );
	return actions;
};

mw.editcheck.HeadingLevelEditCheck.prototype.act = function ( choice, action, surface ) {
	if ( choice === 'fix' ) {
		action.fragments.forEach( ( fragment ) => {
			const heading = surface.getModel().documentModel.getNearestNodeMatching(
				'mwHeading',
				// Note: we set a limit of 1 here because otherwise this will turn around
				// to keep looking when it hits the document boundary:
				fragment.getSelection().getCoveringRange().start - 1, -1, 1
			);
			if ( heading ) {
				fragment.convertNodes( 'mwHeading',
					{ level: heading.getAttribute( 'level' ) + 1 }
				);
			}
		} );
		action.select( surface, true );
		return;
	}
	// Parent method
	return mw.editcheck.HeadingLevelEditCheck.super.prototype.act.apply( this, arguments );
};

/* Registration */

mw.editcheck.editCheckFactory.register( mw.editcheck.HeadingLevelEditCheck );
