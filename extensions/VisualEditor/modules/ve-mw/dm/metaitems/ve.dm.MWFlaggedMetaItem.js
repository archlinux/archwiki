/*!
 * VisualEditor DataModel MWFlaggedMetaItem class.
 *
 * @copyright See AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * DataModel flagged meta item abstract (for pairs of meta items).
 *
 * @class
 * @abstract
 * @extends ve.dm.MetaItem
 * @constructor
 * @param {Object} [element] Reference to element in meta-linmod
 */
ve.dm.MWFlaggedMetaItem = function VeDmMWFlaggedMetaItem() {
	// Parent constructor
	ve.dm.MWFlaggedMetaItem.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( ve.dm.MWFlaggedMetaItem, ve.dm.MetaItem );

/* Static Properties */

/* No name/group/matchRdfaTypes, as this is not a valid meta item, just an abstract class. */

ve.dm.MWFlaggedMetaItem.static.matchTagNames = [ 'meta' ];

ve.dm.MWFlaggedMetaItem.static.toDataElement = function ( domElements ) {
	let property = domElements[ 0 ].getAttribute( 'property' );

	if ( !property || !this.matchRdfaTypes.includes( property ) ) {
		// Fallback to first match if somehow unset
		property = this.matchRdfaTypes[ 0 ];
	}

	return { type: this.name, attributes: { property: property } };
};

ve.dm.MWFlaggedMetaItem.static.toDomElements = function ( dataElement, doc, converter ) {
	let domElement;
	let property = OO.getProp( dataElement, 'attributes', 'property' );

	if ( !property || !this.matchRdfaTypes.includes( property ) ) {
		// Fallback to first item if somehow unset
		property = this.matchRdfaTypes[ 0 ];
	}
	if ( converter.isForPreview() ) {
		domElement = doc.createElement( 'div' );
		domElement.innerText = property;
	} else {
		domElement = doc.createElement( 'meta' );
		domElement.setAttribute( 'property', property );
	}
	return [ domElement ];
};

/* No registration, as this is not a valid meta item, just an abstract class. */
