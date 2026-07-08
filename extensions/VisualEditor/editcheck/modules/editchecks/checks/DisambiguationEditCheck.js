/**
 * Edit check to detect links to disambiguation pages
 *
 * @class
 * @extends mw.editcheck.LinkEditCheck
 *
 * @constructor
 * @param {mw.editcheck.Controller} controller
 * @param {Object} [config]
 * @param {boolean} [includeSuggestions=false]
 */
mw.editcheck.DisambiguationEditCheck = function MWDisambiguationEditCheck() {
	// Parent constructor
	mw.editcheck.DisambiguationEditCheck.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( mw.editcheck.DisambiguationEditCheck, mw.editcheck.LinkEditCheck );

OO.mixinClass( mw.editcheck.DisambiguationEditCheck, mw.editcheck.ContentBranchNodeCheck );

/* Static properties */

mw.editcheck.DisambiguationEditCheck.static.defaultConfig = ve.extendObject( {}, mw.editcheck.DisambiguationEditCheck.super.static.defaultConfig, {
	showAsCheck: false
} );

mw.editcheck.DisambiguationEditCheck.static.name = 'disambiguation';

mw.editcheck.DisambiguationEditCheck.static.title = OO.ui.deferMsg( 'editcheck-disambiguation-title' );

mw.editcheck.DisambiguationEditCheck.static.description = ve.deferJQueryMsg( 'editcheck-disambiguation-description' );

mw.editcheck.DisambiguationEditCheck.static.choices = [
	{
		action: 'edit',
		label: OO.ui.deferMsg( 'editcheck-action-update-link' )
	},
	{
		action: 'dismiss',
		label: OO.ui.deferMsg( 'ooui-dialog-process-dismiss' )
	}
];

mw.editcheck.DisambiguationEditCheck.static.linkClasses = [ ve.dm.MWInternalLinkAnnotation ];

/* Methods */

mw.editcheck.DisambiguationEditCheck.prototype.checkNode = function ( node, surfaceModel ) {
	const checkDisambig = ( annotation ) => ve.init.platform.linkCache.get(
		annotation.getAttribute( 'lookupTitle' )
	).then( ( linkData ) => !!( linkData && linkData.disambiguation ) );

	return node.getAnnotationRanges()
		.filter( ( annRange ) => annRange.annotation.name === ve.dm.MWInternalLinkAnnotation.static.name )
		// Links to sections of disambiguation pages are deliberately specific, so ignore them
		.filter( ( annRange ) => !annRange.annotation.getFragment() )
		.map( ( annRange ) => checkDisambig( annRange.annotation ).then( ( isDisambig ) => isDisambig ?
			this.buildActionFromLinkRange( annRange.range, surfaceModel ) : null
		) );
};

mw.editcheck.DisambiguationEditCheck.prototype.act = function ( choice, action, surface ) {
	if ( choice === 'edit' ) {
		action.select( surface );
		surface.executeCommand( 'link' );
		return;
	}
	// Parent method
	return mw.editcheck.DisambiguationEditCheck.super.prototype.act.apply( this, arguments );
};

/* Registration */

mw.editcheck.editCheckFactory.register( mw.editcheck.DisambiguationEditCheck );
