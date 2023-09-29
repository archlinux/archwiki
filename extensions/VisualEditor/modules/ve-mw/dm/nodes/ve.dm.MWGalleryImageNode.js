/*!
 * VisualEditor DataModel MWGalleryImageNode class.
 *
 * @copyright 2016 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * DataModel MediaWiki gallery image node.
 *
 * @class
 * @extends ve.dm.BranchNode
 *
 * @constructor
 * @param {Object} [element] Reference to element in linear model
 * @param {ve.dm.Node[]} [children]
 */
ve.dm.MWGalleryImageNode = function VeDmMWGalleryImageNode() {
	// Parent constructor
	ve.dm.MWGalleryImageNode.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( ve.dm.MWGalleryImageNode, ve.dm.BranchNode );

/* Static members */

ve.dm.MWGalleryImageNode.static.name = 'mwGalleryImage';

ve.dm.MWGalleryImageNode.static.matchTagNames = [ 'li' ];

ve.dm.MWGalleryImageNode.static.childNodeTypes = [ 'mwGalleryImageCaption' ];

ve.dm.MWGalleryImageNode.static.matchFunction = function ( element ) {
	var parentTypeof = ( element.parentNode && element.parentNode.getAttribute( 'typeof' ) ) || '';
	return element.getAttribute( 'class' ) === 'gallerybox' &&
		parentTypeof.trim().split( /\s+/ ).indexOf( 'mw:Extension/gallery' ) !== -1;
};

ve.dm.MWGalleryImageNode.static.parentNodeTypes = [ 'mwGallery' ];

ve.dm.MWGalleryImageNode.static.toDataElement = function ( domElements, converter ) {
	// TODO: Improve handling of missing files. See 'isError' in MWBlockImageNode#toDataElement
	var li = domElements[ 0 ];
	var img = li.querySelector( 'img,audio,video,span[resource]' );
	var container = img.parentNode.parentNode;

	// Get caption (may be missing for mode="packed-hover" galleries)
	var captionNode = li.querySelector( '.gallerytext' );
	if ( captionNode ) {
		captionNode = captionNode.cloneNode( true );
		// If showFilename is 'yes', the filename is also inside the caption, so throw this out
		var filename = captionNode.querySelector( '.galleryfilename' );
		if ( filename ) {
			filename.remove();
		}
	}

	// FIXME: This should match Parsoid's WTUtils::textContentFromCaption,
	// which drops <ref>s
	var altFromCaption = captionNode ? captionNode.textContent.trim() : '';
	var altTextSame = img.hasAttribute( 'alt' ) && altFromCaption &&
		( img.getAttribute( 'alt' ).trim() === altFromCaption );

	var caption;
	if ( captionNode ) {
		caption = converter.getDataFromDomClean( captionNode, { type: 'mwGalleryImageCaption' } );
	} else {
		caption = [
			{ type: 'mwGalleryImageCaption' },
			{ type: 'paragraph', internal: { generated: 'wrapper' } },
			{ type: '/paragraph' },
			{ type: '/mwGalleryImageCaption' }
		];
	}

	var typeofAttrs = container.getAttribute( 'typeof' ).trim().split( /\s+/ );
	var errorIndex = typeofAttrs.indexOf( 'mw:Error' );
	var isError = errorIndex !== -1;
	var errorText = isError ? img.textContent : null;
	var width = img.getAttribute( isError ? 'data-width' : 'width' );
	var height = img.getAttribute( isError ? 'data-height' : 'height' );

	if ( isError ) {
		typeofAttrs.splice( errorIndex, 1 );
	}

	var types = ve.dm.MWImageNode.static.rdfaToTypes[ typeofAttrs[ 0 ] ];

	var dataElement = {
		type: this.name,
		attributes: {
			mediaClass: types.mediaClass,
			mediaTag: img.nodeName.toLowerCase(),
			resource: './' + mw.libs.ve.normalizeParsoidResourceName( img.getAttribute( 'resource' ) ),
			altText: img.getAttribute( 'alt' ),
			altTextSame: altTextSame,
			// 'src' for images, 'poster' for video/audio
			src: img.getAttribute( 'src' ) || img.getAttribute( 'poster' ),
			width: width !== null && width !== '' ? +width : null,
			height: height !== null && height !== '' ? +height : null,
			isError: isError,
			errorText: errorText
		}
	};

	return [ dataElement ]
		.concat( caption )
		.concat( { type: '/' + this.name } );
};

ve.dm.MWGalleryImageNode.static.toDomElements = function ( data, doc ) {
	// ImageNode:
	//   <li> li (gallerybox)
	//     <div> thumbDiv
	//       <span> container
	//         <a> a
	//           <img> img (or span if error)
	var model = data,
		attributes = model.attributes,
		li = doc.createElement( 'li' ),
		thumbDiv = doc.createElement( 'div' ),
		container = doc.createElement( 'span' ),
		a = doc.createElement( 'a' ),
		img = doc.createElement( attributes.isError ? 'span' : ( attributes.mediaTag || 'img' ) ),
		alt = attributes.altText;

	// FIXME: attributes.mediaTag and attributes.mediaClass aren't set after edit

	li.classList.add( 'gallerybox' );
	thumbDiv.classList.add( 'thumb' );
	container.setAttribute( 'typeof', ve.dm.MWImageNode.static.getRdfa(
		( attributes.mediaClass || 'File' ), 'none', attributes.isError
	) );

	// TODO: Support editing the link
	// FIXME: Dropping the href causes Parsoid to mark the node as wrapper modified,
	// making the whole gallery subtree edited, preventing selser.  When fixing,
	// preserving the imgWrapperClassAttr, as in the MW*ImageNodes, will also be
	// necessary.
	// a.setAttribute( 'href', attributes.src );

	img.setAttribute( 'resource', attributes.resource );
	if ( attributes.isError ) {
		img.classList.add( 'mw-broken-media' );
		var filename = mw.libs.ve.normalizeParsoidResourceName( attributes.resource || '' );
		img.appendChild( doc.createTextNode( attributes.errorText ? attributes.errorText : filename ) );
	} else {
		var srcAttr = ve.dm.MWImageNode.static.tagsToSrcAttrs[ img.nodeName.toLowerCase() ];
		img.setAttribute( srcAttr, attributes.src );
	}
	img.setAttribute( attributes.isError ? 'data-width' : 'width', attributes.width );
	img.setAttribute( attributes.isError ? 'data-height' : 'height', attributes.height );

	if ( typeof alt === 'string' && !attributes.altTextSame ) {
		img.setAttribute( 'alt', alt );
	}

	a.appendChild( img );
	container.appendChild( a );
	thumbDiv.appendChild( container );
	li.appendChild( thumbDiv );

	return [ li ];
};

ve.dm.MWGalleryImageNode.static.describeChange = function ( key ) {
	if ( key === 'altText' ) {
		// Parent method
		return ve.dm.MWGalleryImageNode.super.static.describeChange.apply( this, arguments );
	}
	// All other attributes are computed, or result in nodes being incomparable (`resource`)
	return null;
};

ve.dm.MWGalleryImageNode.static.isDiffComparable = function ( element, other ) {
	// Images with different src's shouldn't be diffed
	return element.type === other.type && element.attributes.resource === other.attributes.resource;
};

/* Methods */

/**
 * Get the image's caption node.
 *
 * @return {ve.dm.MWImageCaptionNode|null} Caption node, if present
 */
ve.dm.MWGalleryImageNode.prototype.getCaptionNode = function () {
	return this.children.length > 0 ? this.children[ 0 ] : null;
};

/* Registration */

ve.dm.modelRegistry.register( ve.dm.MWGalleryImageNode );
