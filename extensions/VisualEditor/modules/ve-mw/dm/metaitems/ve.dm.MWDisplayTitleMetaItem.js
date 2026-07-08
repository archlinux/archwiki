/*!
 * VisualEditor DataModel MWDisplayTitleMetaItem class.
 *
 * @copyright See AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * DataModel display title meta item.
 *
 * @class
 * @extends ve.dm.MetaItem
 * @constructor
 * @param {Object} element Reference to element in meta-linmod
 */
ve.dm.MWDisplayTitleMetaItem = function VeDmMWDisplayTitleMetaItem() {
	// Parent constructor
	ve.dm.MWDisplayTitleMetaItem.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( ve.dm.MWDisplayTitleMetaItem, ve.dm.MetaItem );

/* Static Properties */

ve.dm.MWDisplayTitleMetaItem.static.name = 'mwDisplayTitle';

ve.dm.MWDisplayTitleMetaItem.static.group = 'mwDisplayTitle';

ve.dm.MWDisplayTitleMetaItem.static.matchTagNames = [ 'span' ];

ve.dm.MWDisplayTitleMetaItem.static.matchRdfaTypes = [ 'mw:Transclusion' ];

ve.dm.MWDisplayTitleMetaItem.static.matchFunction = function ( domElement ) {
	const mwDataJSON = domElement.getAttribute( 'data-mw' ),
		mwData = mwDataJSON ? JSON.parse( mwDataJSON ) : {};
	return ve.getProp( mwData, 'parts', '0', 'template', 'target', 'function' ) === 'displaytitle' ||
		ve.getProp( mwData, 'parts', '0', 'parserfunction', 'target', 'key' ) === 'displaytitle';
};

ve.dm.MWDisplayTitleMetaItem.static.toDataElement = function ( domElements ) {
	const mwDataJSON = domElements[ 0 ].getAttribute( 'data-mw' ),
		mwData = mwDataJSON ? JSON.parse( mwDataJSON ) : {};
	const wt = ( ve.getProp( mwData, 'parts', '0', 'template', 'target', 'wt' ) || '' ) ||
		ve.getProp( mwData, 'parts', '0', 'parserfunction', 'params', '1', 'wt' );
	const params = ve.getProp( mwData, 'parts', '0', 'template', 'params' );
	const localizedPrefix = wt.split( ':' )[ 0 ];
	const content = wt.slice( localizedPrefix.length + 1 );
	const data = {
		type: this.name,
		attributes: { localizedPrefix, content }
	};
	if ( params ) {
		data.attributes.params = params;
	}
	return data;
};

ve.dm.MWDisplayTitleMetaItem.static.toDomElements = function ( dataElement, doc ) {
	const prefix = dataElement.attributes.localizedPrefix ||
			mw.config.get( 'wgVisualEditorConfig' ).displayTitlePrefix,
		displayTitle = dataElement.attributes.content,
		mwData = {
			parts: [
				{
					template: {
						target: {
							wt: prefix + ':' + displayTitle,
							function: 'displaytitle'
						}
					}
				}
			]
		};

	if ( dataElement.attributes.params ) {
		mwData.parts[ 0 ].template.params = dataElement.attributes.params;
	}

	if ( !dataElement.originalDomElementsHash ) {
		// If this is a new addition to the page, we need to enforce a newline:
		mwData.parts.push( '\n' );
	}

	const span = doc.createElement( 'span' );
	span.setAttribute( 'typeof', 'mw:Transclusion' );
	span.setAttribute( 'data-mw', JSON.stringify( mwData ) );
	span.setAttribute( 'about', '#mwt-ve-' + Math.floor( 1000000000 * Math.random() ) );
	return [ span ];
};

/* Registration */

ve.dm.modelRegistry.register( ve.dm.MWDisplayTitleMetaItem );
