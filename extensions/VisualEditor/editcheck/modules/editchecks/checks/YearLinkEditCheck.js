/*
 * YearLinkEditCheck
 *
 * Warns when an internal link points to a year page but the
 * link label text is a different year. This often happens when
 * users update years in the article text but forget to update
 * the link target.
 *
 * @class
 * @extends mw.editcheck.LinkEditCheck
 *
 * @constructor
 * @param {mw.editcheck.Controller} controller
 * @param {Object} [config]
 * @param {boolean} [includeSuggestions=false]
 */
mw.editcheck.YearLinkEditCheck = function MWYearLinkEditCheck() {
	mw.editcheck.YearLinkEditCheck.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( mw.editcheck.YearLinkEditCheck, mw.editcheck.LinkEditCheck );

OO.mixinClass( mw.editcheck.YearLinkEditCheck, mw.editcheck.ContentBranchNodeCheck );

/* Static properties */

mw.editcheck.YearLinkEditCheck.static.defaultConfig = ve.extendObject( {}, mw.editcheck.YearLinkEditCheck.super.static.defaultConfig, {
	showAsCheck: false
} );

mw.editcheck.YearLinkEditCheck.static.name = 'yearLink';

mw.editcheck.YearLinkEditCheck.static.title = OO.ui.deferMsg( 'editcheck-yearlink-title' );

mw.editcheck.YearLinkEditCheck.static.description = ve.deferJQueryMsg( 'editcheck-yearlink-description' );

mw.editcheck.YearLinkEditCheck.static.choices = [
	{
		action: 'useTarget',
		// Example value for Special:EditChecks, actual value is set later
		label: OO.ui.deferMsg( 'editcheck-yearlink-action-use', '1999' )
	},
	{
		action: 'useLabel',
		// Example value for Special:EditChecks, actual value is set later
		label: OO.ui.deferMsg( 'editcheck-yearlink-action-use', '2003' )
	},
	{
		action: 'dismiss',
		label: OO.ui.deferMsg( 'ooui-dialog-process-dismiss' )
	}
];

mw.editcheck.YearLinkEditCheck.static.linkClasses = [ ve.dm.MWInternalLinkAnnotation ];

/* Methods */

/**
 * Extract a single year from the given text.
 *
 * @param {string} text
 * @param {boolean} [disallowExtraText=false] Disallow other text around the year, e.g. "1999" not "1999 in film"
 * @return {string|null} The year found, or null if there isn't exactly one valid year
 */
mw.editcheck.YearLinkEditCheck.prototype.matchSingleYear = function ( text, disallowExtraText = false ) {
	const matches = disallowExtraText ?
		text.match( /^\d{3,4}$/g ) :
		text.match( /\b\d{3,4}\b/g );
	return matches && matches.length === 1 ? matches[ 0 ] : null;
};

mw.editcheck.YearLinkEditCheck.prototype.checkNode = function ( node, surfaceModel ) {
	const ranges = node.getAnnotationRanges().filter(
		( annRange ) => annRange.annotation.name === ve.dm.MWInternalLinkAnnotation.static.name
	);
	const actions = ranges.map( ( annRange ) => {
		const title = mw.Title.newFromText( annRange.annotation.getDisplayTitle() );
		if ( !title ) {
			return null;
		}

		const target = title.getMainText();
		// Check target is a 3 or 4-digit number (a year)
		const targetYear = this.matchSingleYear( target, true );
		if ( !targetYear ) {
			return null;
		}

		// Check label contains one 3 or 4-digit number (a year),
		// e.g. "1999" or "2003 in film", but not "1999-2003"
		const fragment = surfaceModel.getLinearFragment( annRange.range );
		const labelYear = this.matchSingleYear( fragment.getText() );
		if ( !labelYear ) {
			return null;
		}

		// If label and target years are the same, there's no issue
		if ( labelYear === targetYear ) {
			return null;
		}

		const choices = ve.copy( mw.editcheck.YearLinkEditCheck.static.choices );
		choices[ 0 ].label = ve.msg( 'editcheck-yearlink-action-use', targetYear );
		choices[ 1 ].label = ve.msg( 'editcheck-yearlink-action-use', labelYear );

		return this.buildActionFromLinkRange( annRange.range, surfaceModel, { choices } );
	} );
	return actions;
};

mw.editcheck.YearLinkEditCheck.prototype.act = function ( choice, action, surface ) {
	const fragment = action.fragments[ 0 ];
	const linkAnnotation = this.getLinkFromFragment( fragment );
	const title = mw.Title.newFromText( linkAnnotation.getDisplayTitle() );
	const target = title.getMainText();
	const text = fragment.getText();

	switch ( choice ) {
		case 'useTarget': {
			// Replace the year in the link label with the year from the target page,
			// e.g. [[1999|2003]] becomes [[1999]]
			// or [[1999|films of 2003]] becomes [[1999|films of 1999]]
			const targetYear = this.matchSingleYear( target );
			fragment.insertContent(
				text.replace( /\b\d{3,4}\b/, targetYear ),
				true
			);
			action.select( surface, true );
			return;
		}

		case 'useLabel': {
			// Replace the year in the link target with the year from the label,
			// e.g. [[1999|2003]] becomes [[2003]]
			// or [[1999|films of 2003]] becomes [[2003|films of 2003]]
			const labelYear = this.matchSingleYear( text );
			const link = ve.dm.MWInternalLinkAnnotation.static.newFromTitle(
				mw.Title.newFromText(
					target.replace( /\b\d{3,4}\b/, labelYear ),
					title.getNamespaceId()
				)
			);
			for ( const linkClass of this.constructor.static.linkClasses ) {
				fragment.annotateContent( 'clear', linkClass.static.name );
			}
			fragment.annotateContent( 'set', link );
			action.select( surface, true );
			return;
		}
	}
	// Parent method
	return mw.editcheck.YearLinkEditCheck.super.prototype.act.apply( this, arguments );
};

/* Registration */

mw.editcheck.editCheckFactory.register( mw.editcheck.YearLinkEditCheck );
