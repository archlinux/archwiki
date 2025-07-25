'use strict';

/*!
 * VisualEditor DataModel MWReferenceNode class.
 *
 * @copyright 2011-2018 VisualEditor Team's Cite sub-team and others; see AUTHORS.txt
 * @license MIT
 */

/**
 * DataModel MediaWiki reference node.
 *
 * @constructor
 * @extends ve.dm.LeafNode
 * @mixes ve.dm.FocusableNode
 * @param {Object} [element] Reference to element in linear model
 */
ve.dm.MWReferenceNode = function VeDmMWReferenceNode() {
	// Parent constructor
	ve.dm.MWReferenceNode.super.apply( this, arguments );

	// Mixin constructors
	ve.dm.FocusableNode.call( this );

	// Event handlers
	this.connect( this, {
		root: 'onRoot',
		unroot: 'onUnroot',
		attributeChange: 'onAttributeChange'
	} );
};

/* Inheritance */

OO.inheritClass( ve.dm.MWReferenceNode, ve.dm.LeafNode );

OO.mixinClass( ve.dm.MWReferenceNode, ve.dm.FocusableNode );

/* Static members */

ve.dm.MWReferenceNode.static.name = 'mwReference';

ve.dm.MWReferenceNode.static.matchTagNames = null;

ve.dm.MWReferenceNode.static.matchRdfaTypes = [ 'mw:Extension/ref' ];

// Handle nodes with mw:Error as this probably just means the ref list doesn't exist (T299672)
ve.dm.MWReferenceNode.static.allowedRdfaTypes = [ 'dc:references', 'mw:Error' ];

ve.dm.MWReferenceNode.static.isContent = true;

ve.dm.MWReferenceNode.static.disallowedAnnotationTypes = [ 'link' ];

/**
 * Regular expression for parsing the listKey attribute
 *
 * Use [\s\S]* instead of .* to catch esoteric whitespace (T263698)
 *
 * @static
 * @property {RegExp}
 * @inheritable
 */
ve.dm.MWReferenceNode.static.listKeyRegex = /^(auto|literal)\/([\s\S]*)$/;

/**
 * @private
 * @param {ve.dm.InternalList} internalList
 * @param {string|null} [name]
 * @return {string}
 */
ve.dm.MWReferenceNode.static.makeListKey = function ( internalList, name ) {
	return name ?
		'literal/' + name :
		'auto/' + internalList.getNextUniqueNumber();
};

/**
 * Transform parsoid HTML DOM to constructor parameters for VE reference nodes.
 *
 * @param {Node[]} domElements DOM elements to convert
 * @param {ve.dm.Converter} converter
 * @return {Object|Array|null} Data element or array of linear model data, or null to alienate
 */
ve.dm.MWReferenceNode.static.toDataElement = function ( domElements, converter ) {
	function getReflistItemHtml( id ) {
		const elem = converter.getHtmlDocument().getElementById( id );
		return elem && elem.innerHTML;
	}

	const mwDataJSON = domElements[ 0 ].getAttribute( 'data-mw' );
	const mwData = mwDataJSON ? JSON.parse( mwDataJSON ) : {};
	const mwAttrs = mwData.attrs || {};
	const reflistItemId = ve.getProp( mwData, 'body', 'id' );
	const body = ve.getProp( mwData, 'body', 'html' ) ||
		( reflistItemId && getReflistItemHtml( reflistItemId ) ) ||
		'';
	const refGroup = mwAttrs.group || '';
	const listGroup = this.name + '/' + refGroup;
	const listKey = this.makeListKey( converter.internalList, mwAttrs.name );
	const { index, isNew } = converter.internalList.queueItemHtml( listGroup, listKey, body );

	if ( converter.isFromClipboard() && !( mwAttrs.name || body ) ) {
		// Pasted reference has neither a name nor body HTML, must have
		// come from Parsoid read mode directly. (T389518)
		return [];
	}

	const dataElement = {
		type: this.name,
		attributes: {
			mw: mwData,
			originalMw: mwDataJSON,
			listIndex: index,
			listGroup: listGroup,
			listKey: listKey,
			refGroup: refGroup,
			contentsUsed: body !== '' && isNew
		}
	};

	if ( mwData.mainRef && mw.config.get( 'wgCiteSubReferencing' ) ) {
		dataElement.attributes.extendsRef = this.makeListKey(
			converter.internalList,
			mwData.mainRef
		);
	}
	if ( reflistItemId ) {
		dataElement.attributes.refListItemId = reflistItemId;
	}
	return dataElement;
};

ve.dm.MWReferenceNode.static.toDomElements = function ( dataElement, doc, converter ) {
	const isForClipboard = converter.isForClipboard();
	const el = doc.createElement( 'sup' );

	el.setAttribute( 'typeof', 'mw:Extension/ref' );

	const mwData = dataElement.attributes.mw ? ve.copy( dataElement.attributes.mw ) : {};
	mwData.name = 'ref';

	if ( isForClipboard || converter.isForParser() ) {
		// This call rebuilds the document tree if it isn't built already (e.g. on a
		// document slice), so only use when necessary (i.e. not in preview mode)
		const itemNode = converter.internalList.getItemNode( dataElement.attributes.listIndex );
		const itemNodeRange = itemNode.getRange();

		const nodesWithSameKey = converter.internalList
			.getNodeGroup( dataElement.attributes.listGroup )
			.keyedNodes[ dataElement.attributes.listKey ] || [];

		const name = this.generateName( dataElement, converter, nodesWithSameKey );
		if ( name !== undefined ) {
			ve.setProp( mwData, 'attrs', 'name', name );
		}

		if ( dataElement.attributes.extendsRef ) {
			// this is always either the literal name that was already there or the
			// auto generated literal from above
			ve.setProp( mwData, 'mainRef', name );

			if ( !ve.getProp( mwData, 'attrs', 'details' ) ) {
				// make sure Parsoid recognizes the ref as a subref, the details content will be
				// set by Parsoid from the bodyContent in body.html
				ve.setProp( mwData, 'attrs', 'details', 'true' );
			}
		}

		// TODO: Apply isBodyContentSet logic to isMainRefBodyWithDetails
		const isBodyContentSet = this.isBodyContentSet( dataElement, nodesWithSameKey );
		const shouldGetBodyContent = this.shouldGetBodyContent( dataElement, nodesWithSameKey );

		// Add reference content to data-mw.
		if ( shouldGetBodyContent && !isBodyContentSet ) {
			// get the current content html of the node
			const currentHtmlWrapper = doc.createElement( 'div' );
			converter.getDomSubtreeFromData(
				itemNode.getDocument().getFullData( itemNodeRange, 'roundTrip' ),
				currentHtmlWrapper
			);

			// get the original content html of the node
			const originalHtmlWrapper = doc.createElement( 'div' );
			const originalHtml = ve.getProp( mwData, 'body', 'html' ) ||
				( ve.getProp( mwData, 'body', 'id' ) !== undefined && itemNode.getAttribute( 'originalHtml' ) ) ||
				'';
			originalHtmlWrapper.innerHTML = originalHtml;

			// Only set body.html if current and original are actually different,
			// or we are writing the clipboard for use in another VE instance
			if ( isForClipboard || !originalHtmlWrapper.isEqualNode( currentHtmlWrapper ) ) {
				ve.setProp( mwData, 'body', 'html', currentHtmlWrapper.innerHTML );
			}
		}

		// If we have no internal item data for this reference, don't let it get pasted into
		// another VE document. T110479
		if ( isForClipboard && itemNodeRange.isCollapsed() ) {
			el.setAttribute( 'data-ve-ignore', 'true' );
		}

		// Set or clear group
		if ( dataElement.attributes.refGroup !== '' ) {
			ve.setProp( mwData, 'attrs', 'group', dataElement.attributes.refGroup );
		} else if ( mwData.attrs ) {
			delete mwData.attrs.group;
		}
	}

	// If mwAttr and originalMw are the same, use originalMw to prevent reserialization,
	// unless we are writing the clipboard for use in another VE instance
	// Reserialization has the potential to reorder keys and so change the DOM unnecessarily
	const originalMw = dataElement.attributes.originalMw;
	if ( converter.isForParser() && originalMw && ve.compare( mwData, JSON.parse( originalMw ) ) ) {
		el.setAttribute( 'data-mw', originalMw );

		// Return the original DOM elements if possible
		if ( dataElement.originalDomElementsHash !== undefined ) {
			return ve.copyDomElements(
				converter.getStore().value( dataElement.originalDomElementsHash ), doc );
		}
	} else {
		let stringifiedMwData = JSON.stringify( mwData );
		if ( isForClipboard ) {
			// T382858: Ensure data-mw attribute wouldn't be removed by DOMPurify on paste.
			// DOMPurify forbids '</style' in the body of attributes to avoid mXSS
			// attacks. Since we know it's JSON, we can encode it with JS unicode escape
			// codes to let the sanitization code do its job without breaking nodes.
			// JSON.parse( '"\\u003C/style"' ) returns '</style', so we don't need
			// to modify the paste handler.
			stringifiedMwData = stringifiedMwData.replace( /<\/style/g, '\\u003C/style' );
		}
		el.setAttribute( 'data-mw', stringifiedMwData );

		// HTML for the external clipboard, it will be ignored by the converter
		const $link = $( '<a>', doc )
			.attr( 'data-mw-group', this.getGroup( dataElement ) || null );
		$( el ).addClass( 'mw-ref reference' ).html(
			$link.append( $( '<span>', doc )
				.addClass( 'mw-reflink-text' )
				.html( this.getIndexLabel( dataElement, converter.internalList ) )
			)
		);
	}

	return [ el ];
};

/***
 * Check if a previous node with the same key has already set the content.
 * If so, we don't overwrite the content of this node.
 *
 * @static
 * @param {Object} dataElement
 * @param {ve.dm.Node[]} nodesWithSameKey
 * @return {boolean}
 * */
ve.dm.MWReferenceNode.static.isBodyContentSet = function ( dataElement, nodesWithSameKey ) {
	if ( !dataElement.attributes.contentsUsed ) {
		return false;
	}
	for ( let i = 0; i < nodesWithSameKey.length; i++ ) {
		// Check if the node is the same as the one we are checking
		if (
			ve.compare(
				this.getInstanceHashObject( nodesWithSameKey[ i ].element ),
				this.getInstanceHashObject( dataElement )
			)
		) {
			break;
		}

		if ( nodesWithSameKey[ i ].element.attributes.contentsUsed ) {
			return true;
		}
	}

	return false;
};

/***
 * Check if the reference should get the body content. Especially if there's no other reference
 * that defined the body content with the key.
 *
 * @static
 * @param {Object} dataElement
 * @param {ve.dm.Node[]} nodesWithSameKey
 * @return {boolean}
 * */
ve.dm.MWReferenceNode.static.shouldGetBodyContent = function ( dataElement, nodesWithSameKey ) {
	// if the reference defined the body content, it should be stored there again
	// a sub-ref should always get the body content, it's needed for the details attribute
	if ( dataElement.attributes.extendsRef || dataElement.attributes.contentsUsed ) {
		return true;
	}

	// only the first reference should get the body content
	if ( !nodesWithSameKey ||
		!ve.compare(
			this.getInstanceHashObject( dataElement ),
			this.getInstanceHashObject( nodesWithSameKey[ 0 ].element )
		) ) {
		return false;
	}

	// check if there's another reference that defined the body content
	// As this is keyedNodes[0] we can start at 1
	for ( let i = 1; i < nodesWithSameKey.length; i++ ) {
		if ( nodesWithSameKey[ i ].element.attributes.contentsUsed ) {
			return false;
		}
	}

	return true;
};

/**
 * Generate the name for a given reference
 *
 * @static
 * @param {Object} dataElement
 * @param {ve.dm.Converter} converter
 * @param {ve.dm.Node[]} nodesWithSameKey
 * @return {string|undefined} literal or auto generated name
 */
ve.dm.MWReferenceNode.static.generateName = function ( dataElement, converter, nodesWithSameKey ) {
	const mainRefKey = dataElement.attributes.extendsRef || dataElement.attributes.listKey;
	const keyParts = mainRefKey.match( this.listKeyRegex );

	// use literal name
	if ( keyParts[ 1 ] === 'literal' ) {
		return keyParts[ 2 ];
	}

	// use auto generated name
	if ( dataElement.attributes.extendsRef ||
		nodesWithSameKey.length > 1 ||
		this.hasSubRefs( dataElement, converter )
	) {
		return converter.internalList.getUniqueListKey(
			dataElement.attributes.listGroup,
			mainRefKey,
			'literal/:'
		).slice( 'literal/'.length );
	}
};

/**
 * @static
 * @param {Object} dataElement
 * @param {ve.dm.Converter} converter
 * @return {boolean}
 */
ve.dm.MWReferenceNode.static.hasSubRefs = function ( dataElement, converter ) {
	if ( dataElement.attributes.extendsRef ) {
		return false;
	}

	const subRefs = converter.internalList.getNodeGroup( dataElement.attributes.listGroup )
		.firstNodes.filter(
			( node ) => node.element.attributes.extendsRef === dataElement.attributes.listKey
		);

	return subRefs.length > 0;
};

ve.dm.MWReferenceNode.static.remapInternalListIndexes = function (
	dataElement, mapping, internalList
) {
	// Remap listIndex
	dataElement.attributes.listIndex = mapping[ dataElement.attributes.listIndex ];

	// Remap listKey if it was automatically generated
	const listKeyParts = dataElement.attributes.listKey.match( this.listKeyRegex );
	if ( listKeyParts[ 1 ] === 'auto' ) {
		dataElement.attributes.listKey = this.makeListKey( internalList );
	}
};

ve.dm.MWReferenceNode.static.remapInternalListKeys = function ( dataElement, internalList ) {
	let suffix = '';
	// Try name, name2, name3, ... until unique
	while ( internalList.keys.includes( dataElement.attributes.listKey + suffix ) ) {
		suffix = suffix ? suffix + 1 : 2;
	}
	if ( suffix ) {
		dataElement.attributes.listKey += suffix;
	}
};

/**
 * Gets the group for the reference
 *
 * @static
 * @param {Object} dataElement Element data
 * @return {string} Group
 */
ve.dm.MWReferenceNode.static.getGroup = function ( dataElement ) {
	return dataElement.attributes.refGroup;
};

/**
 * Gets the index label for the reference
 *
 * @static
 * @param {Object} dataElement Element data
 * @param {ve.dm.InternalList} internalList Internal list
 * @return {string} Reference label as HTML
 */
ve.dm.MWReferenceNode.static.getIndexLabel = function ( dataElement, internalList ) {
	const refGroup = dataElement.attributes.refGroup;
	const indexNumber = dataElement.attributes.placeholder ? '…' :
		ve.dm.MWReferenceNode.static.findIndexNumber( dataElement, internalList );
	const label = ( refGroup ? refGroup + ' ' : '' ) + indexNumber;

	return `<span class="cite-bracket">[</span>${ label }<span class="cite-bracket">]</span>`;
};

/**
 * TODO: replace with a simple property
 *
 * @private
 * @param {Object} dataElement data for the node to be looked up
 * @param {ve.dm.InternalList} internalList document internalList
 * @return {string} footnote number ready for rendering
 */
ve.dm.MWReferenceNode.static.findIndexNumber = function ( dataElement, internalList ) {
	return ve.getProp( dataElement, 'internal', 'overrideIndex' ) ||
		ve.dm.MWDocumentReferences.static.refsForDoc( internalList.getDocument() )
			.getIndexLabel( dataElement.attributes.refGroup, dataElement.attributes.listKey );
};

/**
 * @override
 * @see ve.dm.Node
 */
ve.dm.MWReferenceNode.static.cloneElement = function () {
	const clone = ve.dm.MWReferenceNode.super.static.cloneElement.apply( this, arguments );
	delete clone.attributes.contentsUsed;
	delete clone.attributes.mw;
	delete clone.attributes.originalMw;
	// HACK: Generate a fake hash so this element is never instance comparable to other elements
	// Without originalMw this hash will not get used in toDomElements
	clone.originalDomElementsHash = Math.random();
	return clone;
};

/**
 * @override
 * @see ve.dm.LeafNode
 */
ve.dm.MWReferenceNode.static.getHashObject = function ( dataElement ) {
	// Consider all references in the same group to be comparable:
	// References can't be usefully compared statically, as they are mostly
	// defined by the contents of their internal item, which exists
	// elsewhere in the document.
	// For diffing, comparing reference indexes is not useful as
	// they are auto-generated, and the reference list diff is
	// already handled separately, so will show moves etc.
	//
	// If you need to compare references with the same name, use
	// #getInstanceHashObject
	return {
		type: dataElement.type,
		attributes: {
			listGroup: dataElement.attributes.listGroup
		}
	};
};

/**
 * Get a hash unique to this instance of the reference
 *
 * As #getHashObject has been simplified to make re-used references
 * all equal (to support visual diffing), provide access to a more
 * typical hash that can be used to compare instances of reference
 * which have the same "name".
 *
 * @param {Object} dataElement
 * @return {Object}
 */
ve.dm.MWReferenceNode.static.getInstanceHashObject = function () {
	return ve.dm.MWReferenceNode.super.static.getHashObject.apply( this, arguments );
};

/**
 * @override
 * @see ve.dm.Model
 */
ve.dm.MWReferenceNode.static.describeChange = function ( key, change ) {
	if ( key === 'refGroup' ) {
		if ( !change.from ) {
			return ve.htmlMsg( 'cite-ve-changedesc-ref-group-to', this.wrapText( 'ins', change.to ) );
		} else if ( !change.to ) {
			return ve.htmlMsg( 'cite-ve-changedesc-ref-group-from', this.wrapText( 'del', change.from ) );
		} else {
			return ve.htmlMsg(
				'cite-ve-changedesc-ref-group-both',
				this.wrapText( 'del', change.from ),
				this.wrapText( 'ins', change.to )
			);
		}
	}
};

/* Methods */

/**
 * Don't allow reference nodes to be edited if we can't find their content.
 *
 * @override
 * @see ve.dm.Model
 */
ve.dm.MWReferenceNode.prototype.isEditable = function () {
	const internalItem = this.getInternalItem();
	return internalItem && internalItem.getLength() > 0;
};

/**
 * Gets the internal item node associated with this node
 *
 * @return {ve.dm.InternalItemNode} Item node
 */
ve.dm.MWReferenceNode.prototype.getInternalItem = function () {
	return this.getDocument().getInternalList().getItemNode( this.getAttribute( 'listIndex' ) );
};

/**
 * Gets the index for the reference
 *
 * @return {number} Index
 */
ve.dm.MWReferenceNode.prototype.getIndex = function () {
	return this.constructor.static.getIndex( this.element, this.getDocument().getInternalList() );
};

/**
 * Gets the group for the reference
 *
 * @return {string} Group
 */
ve.dm.MWReferenceNode.prototype.getGroup = function () {
	return this.constructor.static.getGroup( this.element );
};

/**
 * Gets the index label for the reference
 *
 * @return {string} Reference label
 */
ve.dm.MWReferenceNode.prototype.getIndexLabel = function () {
	return this.constructor.static.getIndexLabel(
		this.element, this.getDocument().getInternalList() );
};

/**
 * FIXME: This will be replaced by a simple property.
 *
 * @return {string} Footnote number ready for rendering
 */
ve.dm.MWReferenceNode.prototype.getIndexNumber = function () {
	return this.constructor.static.findIndexNumber(
		this.element,
		this.getDocument().getInternalList()
	);
};

/**
 * Handle the node being attached to the root
 */
ve.dm.MWReferenceNode.prototype.onRoot = function () {
	this.addToInternalList();
};

/**
 * Handle the node being detached from the root
 *
 * @param {ve.dm.DocumentNode} oldRoot Old document root
 */
ve.dm.MWReferenceNode.prototype.onUnroot = function ( oldRoot ) {
	if ( this.getDocument().getDocumentNode() === oldRoot ) {
		this.removeFromInternalList();
	}
};

/**
 * Register the node with the internal list
 */
ve.dm.MWReferenceNode.prototype.addToInternalList = function () {
	if ( this.getRoot() === this.getDocument().getDocumentNode() ) {
		const attributes = this.element.attributes;
		this.registeredListGroup = attributes.listGroup;
		this.registeredListKey = attributes.listKey;
		this.registeredListIndex = attributes.listIndex;
		this.getDocument().getInternalList().addNode(
			this.registeredListGroup,
			this.registeredListKey,
			this.registeredListIndex,
			this
		);
	}
};

/**
 * Unregister the node from the internal list
 */
ve.dm.MWReferenceNode.prototype.removeFromInternalList = function () {
	if ( !this.registeredListGroup ) {
		// Don't try to remove if we haven't been added in the first place.
		return;
	}
	this.getDocument().getInternalList().removeNode(
		this.registeredListGroup,
		this.registeredListKey,
		this.registeredListIndex,
		this
	);
};

ve.dm.MWReferenceNode.prototype.onAttributeChange = function ( key, _from, to ) {
	if ( key === 'placeholder' ) {
		this.getDocument().getInternalList().markGroupAsChanged( this.registeredListGroup );
	}
	if (
		( key !== 'listGroup' && key !== 'listKey' ) ||
		( key === 'listGroup' && this.registeredListGroup === to ) ||
		( key === 'listKey' && this.registeredListKey === to )
	) {
		return;
	}

	// Need the old list keys and indexes, so we register them in addToInternalList
	// They've already been updated in this.element.attributes before this code runs
	this.removeFromInternalList();
	this.addToInternalList();
};

/* Registration */

ve.dm.modelRegistry.register( ve.dm.MWReferenceNode );
