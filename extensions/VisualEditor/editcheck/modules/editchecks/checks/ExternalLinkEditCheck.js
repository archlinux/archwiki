/**
 * Edit check to detect external links in the article body
 *
 * @class
 * @extends mw.editcheck.LinkEditCheck
 *
 * @constructor
 * @param {mw.editcheck.Controller} controller
 * @param {Object} [config]
 * @param {boolean} [includeSuggestions=false]
 */
mw.editcheck.ExternalLinkEditCheck = function MWExternalLinkEditCheck() {
	// Parent constructor
	mw.editcheck.ExternalLinkEditCheck.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( mw.editcheck.ExternalLinkEditCheck, mw.editcheck.LinkEditCheck );

OO.mixinClass( mw.editcheck.ExternalLinkEditCheck, mw.editcheck.ContentBranchNodeCheck );

/* Static properties */

mw.editcheck.ExternalLinkEditCheck.static.defaultConfig = ve.extendObject( {}, mw.editcheck.ExternalLinkEditCheck.super.static.defaultConfig, {
	showAsCheck: false
} );

mw.editcheck.ExternalLinkEditCheck.static.name = 'externalLink';

mw.editcheck.ExternalLinkEditCheck.static.title = OO.ui.deferMsg( 'editcheck-external-link-title' );

mw.editcheck.ExternalLinkEditCheck.static.description = ve.deferJQueryMsg( 'editcheck-external-link-description' );

mw.editcheck.ExternalLinkEditCheck.static.choices = [
	{
		action: 'remove',
		label: OO.ui.deferMsg( 'editcheck-action-remove-link' )
	},
	{
		action: 'dismiss',
		label: OO.ui.deferMsg( 'ooui-dialog-process-dismiss' )
	}
];

mw.editcheck.ExternalLinkEditCheck.static.linkClasses = [ ve.dm.MWExternalLinkAnnotation ];

/* Methods */

mw.editcheck.ExternalLinkEditCheck.prototype.checkNode = function ( node, surfaceModel ) {
	const ranges = node.getAnnotationRanges().filter(
		( annRange ) => annRange.annotation.name === ve.dm.MWExternalLinkAnnotation.static.name
	);
	const actionPromises = ranges.map( ( annRange ) => {
		const href = annRange.annotation.getAttribute( 'href' );
		return this.controller.target.isInterwikiUrl( href ).then( ( isInterwiki ) => {
			if ( isInterwiki ) {
				// Ignore interwiki links
				return null;
			}
			return this.buildActionFromLinkRange( annRange.range, surfaceModel );
		} );
	} );
	return actionPromises;
};

mw.editcheck.ExternalLinkEditCheck.prototype.act = function ( choice, action, surface ) {
	if ( choice === 'remove' ) {
		action.fragments.forEach( ( fragment ) => {
			fragment.annotateContent( 'clear', ve.ce.MWExternalLinkAnnotation.static.name );
		} );
		action.select( surface, true );
		return;
	}
	// Parent method
	return mw.editcheck.ExternalLinkEditCheck.super.prototype.act.apply( this, arguments );
};

/* Registration */

mw.editcheck.editCheckFactory.register( mw.editcheck.ExternalLinkEditCheck );
