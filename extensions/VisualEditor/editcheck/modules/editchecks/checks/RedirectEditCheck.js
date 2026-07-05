/**
 * Edit check to detect links to redirect pages
 *
 * @class
 * @extends mw.editcheck.LinkEditCheck
 *
 * @constructor
 * @param {mw.editcheck.Controller} controller
 * @param {Object} [config]
 * @param {boolean} [includeSuggestions=false]
 */
mw.editcheck.RedirectEditCheck = function MWRedirectEditCheck() {
	// Parent constructor
	mw.editcheck.RedirectEditCheck.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( mw.editcheck.RedirectEditCheck, mw.editcheck.LinkEditCheck );

OO.mixinClass( mw.editcheck.RedirectEditCheck, mw.editcheck.ContentBranchNodeCheck );

/* Static properties */

mw.editcheck.RedirectEditCheck.static.defaultConfig = ve.extendObject( {}, mw.editcheck.RedirectEditCheck.super.static.defaultConfig, {
	showAsCheck: false,
	showAsSuggestion: false
} );

mw.editcheck.RedirectEditCheck.static.title = 'Link to the final page';

mw.editcheck.RedirectEditCheck.static.name = 'redirect';

mw.editcheck.RedirectEditCheck.static.description = 'This link points to a redirect page. Link to the final page instead.';

// HACK: Use plain string above so Special:EditChecks can parse.
const description = mw.editcheck.RedirectEditCheck.static.description;
mw.editcheck.RedirectEditCheck.static.description = () => $( $.parseHTML( description ) );

mw.editcheck.RedirectEditCheck.static.choices = [
	{
		action: 'fix',
		label: 'Update link'
	},
	{
		action: 'dismiss',
		label: OO.ui.deferMsg( 'ooui-dialog-process-dismiss' )
	}
];

mw.editcheck.RedirectEditCheck.static.linkClasses = [ ve.dm.MWInternalLinkAnnotation ];

/* Methods */

mw.editcheck.RedirectEditCheck.prototype.checkNode = function ( node, surfaceModel ) {
	const checkRedirect = ( annotation ) => ve.init.platform.linkCache.get(
		annotation.getAttribute( 'lookupTitle' )
	).then( ( linkData ) => !!( linkData && linkData.redirect ) );

	const ranges = node.getAnnotationRanges()
		// exclude links where the link target matches the link text
		.filter( ( annRange ) => {
			if ( annRange.annotation.name !== ve.dm.MWInternalLinkAnnotation.static.name ) {
				return false;
			}
			const labelTitle = mw.Title.newFromText( surfaceModel.getLinearFragment( annRange.range ).getText() );
			if ( !labelTitle ) {
				// Label isn't a valid title, so can't be equal to the target.
				return true;
			}
			const title = mw.Title.newFromText( annRange.annotation.getDisplayTitle() );
			// Normalise titles, and compare. If the titles are equal, or the target is a
			// prefix of the label, then it's likely this is indented to produce compact
			// wikitext, e.g. [[redirect]] or [[redirect]]s
			return !labelTitle.getPrefixedText().startsWith( title.getPrefixedText() );
		} );
	const actionsPromises = ranges.map( ( annRange ) => checkRedirect( annRange.annotation )
		.then( ( isRedirect ) => isRedirect ?
			this.buildActionFromLinkRange( annRange.range, surfaceModel ) : null
		)
	);
	return actionsPromises;
};

mw.editcheck.RedirectEditCheck.prototype.act = function ( choice, action, surface ) {
	if ( choice === 'fix' ) {
		const fragment = action.fragments[ 0 ];
		const linkAnnotation = this.getLinkFromFragment( fragment );

		if ( !linkAnnotation ) {
			return;
		}

		return surface.getTarget().getContentApi().get( {
			action: 'query',
			titles: linkAnnotation.getAttribute( 'lookupTitle' ),
			redirects: true
		} ).then( ( data ) => {
			const targetTitle = ve.getProp( data, 'query', 'redirects', 0, 'to' );
			if ( targetTitle ) {
				const newLinkAnnotation = ve.dm.MWInternalLinkAnnotation.static.newFromTitle(
					new mw.Title( targetTitle )
				);
				fragment
					.annotateContent( 'clear', linkAnnotation )
					.annotateContent( 'add', newLinkAnnotation );

				action.select( surface, true );
			}
		} );
	}
	// Parent method
	return mw.editcheck.RedirectEditCheck.super.prototype.act.apply( this, arguments );
};

/* Registration */

mw.editcheck.editCheckFactory.register( mw.editcheck.RedirectEditCheck );
