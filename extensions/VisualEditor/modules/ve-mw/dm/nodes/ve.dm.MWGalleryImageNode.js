/*!
 * VisualEditor DataModel MWGalleryImageNode class.
 *
 * @copyright See AUTHORS.txt
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
	const parentTypeof = ( element.parentNode && element.parentNode.getAttribute( 'typeof' ) ) || '';
	return element.getAttribute( 'class' ) === 'gallerybox' &&
		parentTypeof.trim().split( /\s+/ ).includes( 'mw:Extension/gallery' );
};

ve.dm.MWGalleryImageNode.static.parentNodeTypes = [ 'mwGallery' ];

ve.dm.MWGalleryImageNode.static.preserveHtmlAttributes = function ( attribute ) {
	const attributes = [ 'typeof', 'class', 'src', 'resource', 'width', 'height', 'href', 'rel', 'alt', 'data-mw' ];
	return !attributes.includes( attribute );
};
// By handling our own children we ensure that original DOM attributes
// are deep copied back by the converter (in renderHtmlAttributeList)
ve.dm.MWGalleryImageNode.static.handlesOwnChildren = true;

// This should be kept in sync with Parsoid's WTUtils::textContentFromCaption
// which drops <ref>s and metadata tags
ve.dm.MWGalleryImageNode.static.textContentFromCaption = function textContentFromCaption( node ) {
	const metaDataTags = [ 'base', 'link', 'meta', 'noscript', 'script', 'style', 'template', 'title' ];
	let content = '';
	let c = node.firstChild;
	while ( c ) {
		if ( c.nodeName === '#text' ) {
			content += c.nodeValue;
		} else if (
			c instanceof HTMLElement &&
			( !metaDataTags.includes( c.nodeName.toLowerCase() ) ) &&
			!/\bmw:Extension\/ref\b/.test( c.getAttribute( 'typeOf' ) )
		) {
			content += textContentFromCaption( c );
		}
		c = c.nextSibling;
	}
	return content;
};

ve.dm.MWGalleryImageNode.static.toDataElement = function ( domElements, converter ) {
	// TODO: Improve handling of missing files. See 'isError' in MWBlockImageNode#toDataElement
	const li = domElements[ 0 ];
	const img = li.querySelector( '.mw-file-element' );
	const imgWrapper = img.parentNode;
	const container = imgWrapper.parentNode;

	// Get caption (may be missing for mode="packed-hover" galleries)
	let captionNode = li.querySelector( '.gallerytext' );
	if ( captionNode ) {
		captionNode = captionNode.cloneNode( true );
		// If showFilename is 'yes', the filename is also inside the caption, so throw this out
		const filename = captionNode.querySelector( '.galleryfilename' );
		if ( filename ) {
			filename.remove();
		}
	}

	// For video thumbnails, the `alt` attribute is only in the data-mw of the container (see: T348703)
	const mwDataJSON = container.getAttribute( 'data-mw' );
	const mwData = mwDataJSON ? JSON.parse( mwDataJSON ) : {};
	const mwAttribs = mwData.attribs || [];
	let containerAlt;
	for ( let i = mwAttribs.length - 1; i >= 0; i-- ) {
		if ( mwAttribs[ i ][ 0 ] === 'alt' && mwAttribs[ i ][ 1 ].txt ) {
			containerAlt = mwAttribs[ i ][ 1 ].txt;
			break;
		}
	}

	const altPresent = img.hasAttribute( 'alt' ) || containerAlt !== undefined;
	let altText = null;
	if ( altPresent ) {
		altText = img.hasAttribute( 'alt' ) ? img.getAttribute( 'alt' ) : containerAlt;
	}

	const altFromCaption = captionNode ?
		ve.dm.MWGalleryImageNode.static.textContentFromCaption( captionNode ).trim() : '';
	const altTextSame = altPresent && altFromCaption &&
		( altText.trim() === altFromCaption );

	let caption;
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

	const typeofAttrs = container.getAttribute( 'typeof' ).trim().split( /\s+/ );
	const errorIndex = typeofAttrs.indexOf( 'mw:Error' );
	const isError = errorIndex !== -1;
	const errorText = isError ? img.textContent : null;
	const width = img.getAttribute( isError ? 'data-width' : 'width' );
	const height = img.getAttribute( isError ? 'data-height' : 'height' );

	if ( isError ) {
		typeofAttrs.splice( errorIndex, 1 );
	}

	const types = ve.dm.MWImageNode.static.rdfaToTypes[ typeofAttrs[ 0 ] ];

	let href = imgWrapper.getAttribute( 'href' );
	if ( href ) {
		// Convert absolute URLs to relative if the href refers to a page on this wiki.
		// Otherwise Parsoid generates |link= options for copy-pasted images (T193253).
		const targetData = mw.libs.ve.getTargetDataFromHref( href, converter.getTargetHtmlDocument() );
		if ( targetData.isInternal ) {
			href = mw.libs.ve.encodeParsoidResourceName( targetData.title );
		}
	}

	const dataElement = {
		type: this.name,
		attributes: {
			mediaClass: types.mediaClass,
			mediaTag: img.nodeName.toLowerCase(),
			resource: img.getAttribute( 'resource' ),
			altText,
			altTextSame,
			href,
			// 'src' for images, 'poster' for video/audio
			src: img.getAttribute( 'src' ) || img.getAttribute( 'poster' ),
			width: width !== null && width !== '' ? +width : null,
			height: height !== null && height !== '' ? +height : null,
			isError,
			errorText,
			mw: mwData,
			imageClassAttr: img.getAttribute( 'class' ),
			imgWrapperClassAttr: imgWrapper.getAttribute( 'class' )
		}
	};

	return [].concat(
		dataElement,
		caption,
		{ type: '/' + this.name }
	);
};

ve.dm.MWGalleryImageNode.static.toDomElements = function ( data, doc, converter ) {
	// ImageNode:
	//   <li> li (gallerybox)
	//     <div> thumbDiv
	//       <span> container
	//         <a> a
	//           <img> img (or span if error)
	const model = data[ 0 ],
		attributes = model.attributes,
		li = doc.createElement( 'li' ),
		thumbDiv = doc.createElement( 'div' ),
		container = doc.createElement( 'span' ),
		imgWrapper = doc.createElement( attributes.href ? 'a' : 'span' ),
		img = doc.createElement( attributes.isError ? 'span' : attributes.mediaTag ),
		alt = attributes.altText,
		mwData = ve.copy( attributes.mw ) || {};

	li.classList.add( 'gallerybox' );
	thumbDiv.classList.add( 'thumb' );
	container.setAttribute( 'typeof', ve.dm.MWImageNode.static.getRdfa(
		attributes.mediaClass, 'none', attributes.isError
	) );

	if ( attributes.href ) {
		imgWrapper.setAttribute( 'href', attributes.href );
	}

	if ( attributes.imageClassAttr ) {
		// eslint-disable-next-line mediawiki/class-doc
		img.className = attributes.imageClassAttr;
	}

	if ( attributes.imgWrapperClassAttr ) {
		// eslint-disable-next-line mediawiki/class-doc
		imgWrapper.className = attributes.imgWrapperClassAttr;
	}

	img.setAttribute( 'resource', attributes.resource );
	if ( attributes.isError ) {
		const filename = mw.libs.ve.normalizeParsoidResourceName( attributes.resource || '' );
		img.appendChild( doc.createTextNode( attributes.errorText ? attributes.errorText : filename ) );
	} else {
		const srcAttr = ve.dm.MWImageNode.static.tagsToSrcAttrs[ img.nodeName.toLowerCase() ];
		img.setAttribute( srcAttr, attributes.src );
	}
	img.setAttribute( attributes.isError ? 'data-width' : 'width', attributes.width );
	img.setAttribute( attributes.isError ? 'data-height' : 'height', attributes.height );

	imgWrapper.appendChild( img );
	container.appendChild( imgWrapper );
	thumbDiv.appendChild( container );
	li.appendChild( thumbDiv );

	const captionData = data.slice( 1, -1 );
	const captionWrapper = doc.createElement( 'div' );
	converter.getDomSubtreeFromData( captionData, captionWrapper );
	while ( captionWrapper.firstChild ) {
		li.appendChild( captionWrapper.firstChild );
	}
	const captionText = ve.dm.MWGalleryImageNode.static.textContentFromCaption( li ).trim();

	if ( img.nodeName.toLowerCase() === 'img' ) {
		if ( attributes.altTextSame && captionText ) {
			img.setAttribute( 'alt', captionText );
		} else if ( typeof alt === 'string' ) {
			img.setAttribute( 'alt', alt );
		}
	} else {
		let mwAttribs = mwData.attribs || [];
		mwAttribs = mwAttribs.filter(
			( attr ) => attr[ 0 ] !== 'alt'
		);
		// Parsoid only sets an alt in the data-mw.attribs if it's explicit
		// in the source
		if ( !attributes.altTextSame && typeof alt === 'string' ) {
			mwAttribs.push( [ 'alt', { txt: alt } ] );
		}
		if ( mwData.attribs || mwAttribs.length ) {
			mwData.attribs = mwAttribs;
		}
	}

	if ( !ve.isEmptyObject( mwData ) ) {
		container.setAttribute( 'data-mw', JSON.stringify( mwData ) );
	}

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
