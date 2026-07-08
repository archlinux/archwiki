/**
 * LinkEditCheck
 *
 * @class
 * @abstract
 * @extends mw.editcheck.BaseEditCheck
 *
 * @constructor
 * @param {mw.editcheck.Controller} controller
 * @param {Object} [config]
 * @param {boolean} [includeSuggestions=false]
 */
mw.editcheck.LinkEditCheck = function MWLinkEditCheck() {
	// Parent constructor
	mw.editcheck.LinkEditCheck.super.apply( this, arguments );

	this.matchAnnotationByView = ( annView ) => {
		const annModel = annView.getModel();
		return this.constructor.static.linkClasses.some( ( linkClass ) => annModel instanceof linkClass );
	};
};

/* Inheritance */

OO.inheritClass( mw.editcheck.LinkEditCheck, mw.editcheck.BaseEditCheck );

/* Static properties */

/**
 * @static
 * @property {Function[]} linkClasses List of link types to check for,
 *  e.g. `[ ve.dm.MWInternalLinkAnnotation ]`
 */
mw.editcheck.LinkEditCheck.static.linkClasses = null;

/* Methods */

/**
 * Get modified link annotation ranges in the document
 *
 * @param {ve.ui.SurfaceModel} surfaceModel
 * @return {ve.dm.LinearData.AnnotationRange[]} Annotation ranges, containing a link annotation and its range
 */
mw.editcheck.LinkEditCheck.prototype.getModifiedLinkRanges = function ( surfaceModel ) {
	return this.getModifiedAnnotationRanges(
		surfaceModel.getDocument(),
		this.constructor.static.linkClasses.map( ( linkClass ) => linkClass.static.name )
	);
};

/**
 * Build an EditCheckAction from a link range
 *
 * @param {ve.Range} range
 * @param {ve.ui.SurfaceModel} surfaceModel
 * @param {Object} [extraConfig] Extra configuration for the EditCheckAction
 * @return {mw.editcheck.EditCheckAction}
 */
mw.editcheck.LinkEditCheck.prototype.buildActionFromLinkRange = function ( range, surfaceModel, extraConfig ) {
	return new mw.editcheck.EditCheckAction( Object.assign( {
		fragments: [ surfaceModel.getLinearFragment( range ) ],
		focusAnnotation: this.matchAnnotationByView,
		check: this
	}, extraConfig ) );
};

/**
 * Get the link annotation from a fragment
 *
 * @param {ve.dm.LinearFragment} fragment
 * @return {ve.dm.LinkAnnotation|null} The link annotation, or null if none found
 */
mw.editcheck.LinkEditCheck.prototype.getLinkFromFragment = function ( fragment ) {
	for ( const linkClass of this.constructor.static.linkClasses ) {
		const linkAnnotation = fragment.getAnnotations().getAnnotationsByName( linkClass.static.name ).get( 0 );
		if ( linkAnnotation ) {
			return linkAnnotation;
		}
	}

	return null;
};

/**
 * Select the link annotation in the fragment
 *
 * @param {ve.dm.LinearFragment} fragment
 * @param {ve.ui.Surface} surface
 */
mw.editcheck.LinkEditCheck.prototype.selectAnnotation = function ( fragment, surface ) {
	setTimeout( () => {
		fragment.select();
		surface.getView().selectAnnotation( this.matchAnnotationByView );
	}, 100 );
};
