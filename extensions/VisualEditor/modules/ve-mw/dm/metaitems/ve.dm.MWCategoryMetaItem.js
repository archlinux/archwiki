/*!
 * VisualEditor DataModel MWCategoryMetaItem class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * DataModel category meta item.
 *
 * @class
 * @extends ve.dm.MetaItem
 * @constructor
 * @param {Object} element Reference to element in meta-linmod
 */
ve.dm.MWCategoryMetaItem = function VeDmMWCategoryMetaItem() {
	// Parent constructor
	ve.dm.MWCategoryMetaItem.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( ve.dm.MWCategoryMetaItem, ve.dm.MetaItem );

/* Static Properties */

ve.dm.MWCategoryMetaItem.static.name = 'mwCategory';

ve.dm.MWCategoryMetaItem.static.group = 'mwCategory';

ve.dm.MWCategoryMetaItem.static.matchTagNames = [ 'link' ];

ve.dm.MWCategoryMetaItem.static.matchRdfaTypes = [ 'mw:PageProp/Category' ];

ve.dm.MWCategoryMetaItem.static.toDataElement = function ( domElements ) {
	// Parsoid: LinkHandlerUtils::serializeAsWikiLink
	var href = domElements[ 0 ].getAttribute( 'href' ),
		titleAndFragment = href.match( /^(.*?)(?:#(.*))?\s*$/ );
	return {
		type: this.name,
		attributes: {
			category: mw.libs.ve.parseParsoidResourceName( titleAndFragment[ 1 ] ).title,
			sortkey: titleAndFragment[ 2 ] ? decodeURIComponent( titleAndFragment[ 2 ] ) : ''
		}
	};
};

ve.dm.MWCategoryMetaItem.static.toDomElements = function ( dataElement, doc ) {
	var domElement = doc.createElement( 'link' ),
		category = dataElement.attributes.category || '',
		sortkey = dataElement.attributes.sortkey || '';
	domElement.setAttribute( 'rel', 'mw:PageProp/Category' );

	// Parsoid: WikiLinkHandler::renderCategory
	var href = mw.libs.ve.encodeParsoidResourceName( category );
	if ( sortkey !== '' ) {
		href += '#' + sortkey.replace( /[%? [\]#|<>]/g, function ( match ) {
			return encodeURIComponent( match );
		} );
	}

	domElement.setAttribute( 'href', href );
	return [ domElement ];
};

/* Registration */

ve.dm.modelRegistry.register( ve.dm.MWCategoryMetaItem );
