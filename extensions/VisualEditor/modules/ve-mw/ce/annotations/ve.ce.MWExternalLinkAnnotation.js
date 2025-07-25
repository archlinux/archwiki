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
	if ( relValues.includes( 'mw:WikiLink/Interwiki' ) ) {
		this.$anchor.addClass( 'extiw' );
	} else {
		this.$anchor.addClass( 'external' );
	}
};

/* Inheritance */

OO.inheritClass( ve.ce.MWExternalLinkAnnotation, ve.ce.LinkAnnotation );

/* Static Properties */

ve.ce.MWExternalLinkAnnotation.static.name = 'link/mwExternal';

/* Registration */

ve.ce.annotationFactory.register( ve.ce.MWExternalLinkAnnotation );
