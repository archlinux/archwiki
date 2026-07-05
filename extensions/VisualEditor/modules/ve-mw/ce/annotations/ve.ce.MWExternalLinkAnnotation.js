/*!
 * VisualEditor ContentEditable MWExternalLinkAnnotation class.
 *
 * @copyright See AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * ContentEditable MediaWiki external link annotation.
 *
 * @class
 * @extends ve.ce.LinkAnnotation
 * @constructor
 * @param {ve.dm.MWExternalLinkAnnotation} model Model to observe
 * @param {ve.ce.ContentBranchNode} [parentNode] Node rendering this annotation
 * @param {Object} [config] Configuration options
 */
ve.ce.MWExternalLinkAnnotation = function VeCeMWExternalLinkAnnotation( model ) {
	// Parent constructor
	ve.ce.MWExternalLinkAnnotation.super.apply( this, arguments );

	// DOM changes
	const rel = model.getAttribute( 'rel' ) || '';
	const relValues = rel.split( /\s+/ );
	// If link comes from Parsoid with a specific rel value, apply the corresponding class.
	if ( relValues.includes( 'mw:WikiLink/Interwiki' ) ) {
		this.$anchor.addClass( 'extiw' );
	} else if ( relValues.includes( 'mw:ExtLink' ) ) {
		this.$anchor.addClass( 'external' );
	} else {
		// ...otherwise check the URL (newly created links)
		ve.init.target.isInterwikiUrl( model.getAttribute( 'href' ) ).then( ( isInterwiki ) => {
			this.$anchor.addClass( isInterwiki ? 'extiw' : 'external' );
		} );
	}
};

/* Inheritance */

OO.inheritClass( ve.ce.MWExternalLinkAnnotation, ve.ce.LinkAnnotation );

/* Static Properties */

ve.ce.MWExternalLinkAnnotation.static.name = 'link/mwExternal';

/* Registration */

ve.ce.annotationFactory.register( ve.ce.MWExternalLinkAnnotation );
