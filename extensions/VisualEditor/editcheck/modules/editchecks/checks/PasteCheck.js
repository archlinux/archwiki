/**
 * Edit check to detect pasted content, which is often a sign of copyright violation/plagiarism.
 *
 * @class
 * @extends mw.editcheck.BaseEditCheck
 *
 * @constructor
 * @param {mw.editcheck.Controller} controller
 * @param {Object} [config]
 * @param {boolean} [includeSuggestions=false]
 */
mw.editcheck.PasteCheck = function MWPasteCheck() {
	// Parent constructor
	mw.editcheck.PasteCheck.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( mw.editcheck.PasteCheck, mw.editcheck.BaseEditCheck );

/* Static properties */

mw.editcheck.PasteCheck.static.defaultConfig = ve.extendObject( {}, mw.editcheck.PasteCheck.super.static.defaultConfig, {
	showAsSuggestion: false,
	minimumCharacters: 50,
	ignoreQuotedContent: true
} );

mw.editcheck.PasteCheck.static.title = OO.ui.deferMsg( 'editcheck-copyvio-title' );

mw.editcheck.PasteCheck.static.description = ve.deferJQueryMsg( 'editcheck-copyvio-description' );

mw.editcheck.PasteCheck.static.prompt = OO.ui.deferMsg( 'editcheck-copyvio-prompt' );

mw.editcheck.PasteCheck.static.success = OO.ui.deferMsg( 'editcheck-copyvio-remove-notify' );

mw.editcheck.PasteCheck.static.name = 'paste';

mw.editcheck.PasteCheck.static.choices = [
	{
		action: 'keep',
		label: OO.ui.deferMsg( 'editcheck-copyvio-action-keep' )
	},
	{
		action: 'remove',
		label: OO.ui.deferMsg( 'editcheck-copyvio-action-remove' )
	}
];

mw.editcheck.PasteCheck.static.takesFocus = true;

mw.editcheck.PasteCheck.static.originalPasteLengths = {};

/**
 * Categories of paste sources that have a lower plagiarism risk
 *
 * @static
 * @property {string[]}
 */
mw.editcheck.PasteCheck.static.trustedPasteCategories = [
	'internal', // Another VE instance
	'wordProcessor', // Word, Google Docs, etc.
	'plain' // Plain text sources, e.g. Notepad, or copied as plain text
];

/* Methods */

mw.editcheck.PasteCheck.prototype.onDocumentChange = function ( surfaceModel ) {
	const pastesById = {};
	const doc = surfaceModel.getDocument();
	doc.getDocumentNode().getAnnotationRanges().forEach( ( annRange ) => {
		const annotation = annRange.annotation;
		if (
			annotation instanceof ve.dm.ImportedDataAnnotation && !(
				annotation.getAttribute( 'source' ) &&
				this.constructor.static.trustedPasteCategories.some(
					( category ) => annotation.getAttribute( 'source' ).categories.includes( category )
				)
			)
		) {
			const id = annotation.getAttribute( 'eventId' );
			if ( this.isDismissedId( id ) ) {
				return;
			}
			if ( !this.isRangeValid( annRange.range, doc ) ) {
				return;
			}
			pastesById[ id ] = pastesById[ id ] || [];
			pastesById[ id ].push( annRange.range );
		}
	} );
	return Object.keys( pastesById ).map( ( id ) => {
		const fragments = pastesById[ id ].map( ( range ) => surfaceModel.getLinearFragment( range ) );
		if ( !( id in mw.editcheck.PasteCheck.static.originalPasteLengths ) ) {
			const combinedLength = pastesById[ id ].reduce( ( sum, range ) => sum + range.getLength(), 0 );
			mw.editcheck.PasteCheck.static.originalPasteLengths[ id ] = combinedLength;
		}
		if ( mw.editcheck.PasteCheck.static.originalPasteLengths[ id ] < this.config.minimumCharacters ) {
			return null;
		}

		return new mw.editcheck.EditCheckAction( {
			fragments,
			id,
			check: this
		} );
	} ).filter( Boolean );
};

// TODO: enable this once issues editing content from pre-save are resolved (T407543)
// mw.editcheck.PasteCheck.prototype.onBeforeSave = mw.editcheck.PasteCheck.prototype.onDocumentChange;

mw.editcheck.PasteCheck.prototype.act = function ( choice, action, surface ) {
	switch ( choice ) {
		case 'keep':
			return action.widget.showFeedback( {
				description: ve.msg( 'editcheck-copyvio-keep-description' ),
				choices: [ 'wrote', 'permission', 'other' ].map(
					( key ) => ( {
						data: key,
						// Messages that can be used here:
						// * editcheck-copyvio-keep-wrote
						// * editcheck-copyvio-keep-permission
						// * editcheck-copyvio-keep-other
						label: ve.msg( 'editcheck-copyvio-keep-' + key )
					} ) )
			} ).then( ( reason ) => {
				this.dismiss( action );
				this.showSuccess( ve.msg( 'editcheck-copyvio-keep-notify' ) );
				return ve.createDeferred().resolve( { action: choice, reason } ).promise();
			} );
		case 'remove': {
			action.fragments.forEach( ( fragment ) => {
				fragment.removeContent();

				// If removal leaves an empty content branch node, then remove it too
				const range = fragment.getSelection().getCoveringRange();
				const node = surface.getModel().getDocument().getBranchNodeFromOffset( range.start );
				if ( node && node.canContainContent() && node.getRange().isCollapsed() ) {
					surface.getModel().getLinearFragment( node.getOuterRange() ).removeContent();
				}
			} );
			// If in pre-save mode, close the check dialog
			const closePromise = this.controller.inBeforeSave ? this.controller.closeDialog() : ve.createDeferred().resolve().promise();
			return closePromise.then( () => {
				// Auto-scrolling causes selection and focus changes...
				setTimeout( () => {
					action.fragments[ action.fragments.length - 1 ].select();
					surface.getView().focus();
				}, 500 );
				this.showSuccess();
			} );
		}
	}
};

/* Registration */

mw.editcheck.editCheckFactory.register( mw.editcheck.PasteCheck );
