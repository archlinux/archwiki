'use strict';

/*!
 * VisualEditor DataModel MWReferenceNode class.
 *
 * @copyright 2011-2018 VisualEditor Team's Cite sub-team and others; see AUTHORS.txt
 * @license MIT
 */

const MWDocumentReferences = require( './ve.dm.MWDocumentReferences.js' );

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
 * @param {ve.dm.ModelFromDomConverter} converter
 * @return {Object|Array|null} Data element or array of linear model data, or null to alienate
 */
ve.dm.MWReferenceNode.static.toDataElement = function ( domElements, converter ) {
	function getReflistItemHtml( id ) {
		const elem = converter.getHtmlDocument().getElementById( id );
		return elem && elem.innerHTML;
	}

	function getReflistItemGroup( id ) {
		const elem = converter.getHtmlDocument().getElementById( id );
		return elem && elem.getAttribute( 'data-mw-group' );
	}

	const mwDataJSON = domElements[ 0 ].getAttribute( 'data-mw' );
	const mwData = mwDataJSON ? JSON.parse( mwDataJSON ) : {};
	const mwAttrs = mwData.attrs || {};
	const reflistItemId = ve.getProp( mwData, 'body', 'id' );
	const body = ve.getProp( mwData, 'body', 'html' ) ||
		( reflistItemId && getReflistItemHtml( reflistItemId ) ) ||
		'';
	const refGroup = mwAttrs.group ||
		( reflistItemId && getReflistItemGroup( reflistItemId ) ) ||
		'';
	const listGroup = this.name + '/' + refGroup;
	const refName = ( mwData.mainRef ? null : mwAttrs.name );
	const listKey = this.makeListKey( converter.internalList, refName );
	const { index, isNew } = converter.internalList.queueItemHtml( listGroup, listKey, body );

	if ( converter.isFromClipboard() && !( refName || body ) ) {
		// Pasted reference has neither a name nor body HTML, must have
		// come from Parsoid read mode directly. (T389518)
		return [];
	}

	// Sub-refs will always get body content for the details attribute so we use contentsUsed to
	// store if they had main content in the main+details case
	const contentsUsed = !!( mwData.mainRef ? mwData.isSubRefWithMainBody : isNew && body );

	const dataElement = {
		type: this.name,
		attributes: {
			mw: mwData,
			originalMw: mwDataJSON,
			listIndex: index,
			listGroup,
			listKey,
			refGroup,
			contentsUsed
		}
	};

	if ( mwData.mainRef && mw.config.get( 'wgCiteSubReferencing' ) ) {
		dataElement.attributes.mainRefKey = this.makeListKey(
			converter.internalList,
			mwData.mainRef
		);
	}
	if ( reflistItemId ) {
		dataElement.attributes.refListItemId = reflistItemId;
	}
	return dataElement;
};

/**
 * Transform reference data elements from the linear model to HTML DOM elements as input for
 * the Parsoid parser.
 *
 * @param {Object} dataElement
 * @param {HTMLDocument} doc
 * @param {ve.dm.DomFromModelConverter} converter
 * @return {HTMLElement[]}
 */
ve.dm.MWReferenceNode.static.toDomElements = function ( dataElement, doc, converter ) {
	const isForClipboard = converter.isForClipboard();
	const internalList = converter.internalList;
	const attributes = dataElement.attributes;
	const domElement = doc.createElement( 'sup' );

	domElement.setAttribute( 'typeof', 'mw:Extension/ref' );

	const mwData = attributes.mw ? ve.copy( attributes.mw ) : {};
	const originalMw = attributes.originalMw;
	const originalMwData = originalMw ? JSON.parse( originalMw ) : {};
	mwData.name = 'ref';

	if ( isForClipboard || converter.isForParser() ) {
		// This call rebuilds the document tree if it isn't built already (e.g. on a
		// document slice), so only use when necessary (i.e. not in preview mode)
		const itemNode = internalList.getItemNode( attributes.listIndex );
		const itemNodeRange = itemNode.getRange();

		const nodeGroup = internalList.getNodeGroup( attributes.listGroup );
		const nodesWithSameKey = nodeGroup.getAllReuses( attributes.listKey ) || [];

		const name = this.generateName( attributes, internalList, nodesWithSameKey );
		if ( name !== undefined ) {
			ve.setProp( mwData, 'attrs', 'name', name );
		}

		// Node is a sub-ref
		if ( attributes.mainRefKey ) {
			// this is always either the literal name that was already there or the
			// auto generated literal from above
			ve.setProp( mwData, 'mainRef', name );

			if ( !ve.getProp( mwData, 'attrs', 'details' ) ) {
				// Make sure Parsoid recognizes the ref as a sub-ref, the details content will be
				// set by Parsoid from the bodyContent in body.html
				ve.setProp( mwData, 'attrs', 'details', '1' );
			}

			// Check if this sub-ref should get a synthetic main body
			const syntheticMainRefId = this.shouldLinkSyntheticMainRef( dataElement, nodeGroup );
			if ( syntheticMainRefId ) {
				ve.setProp( mwData, 'isSubRefWithMainBody', '1' );
				ve.setProp( mwData, 'mainBody', syntheticMainRefId );
			}
		}

		// FIXME: Merge if sub-refs should get main content vs main refs getting body content
		const shouldGetMainContent = this.shouldGetMainContent( dataElement, nodeGroup );

		// Add reference content to data-mw.
		if ( attributes.mainRefKey ||
			( !this.isBodyContentSet( dataElement, nodesWithSameKey ) && shouldGetMainContent )
		) {
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
			domElement.setAttribute( 'data-ve-ignore', '' );
		}

		// Set or clear group
		if ( attributes.refGroup !== '' &&
			// List defined references that had no group before should not save their group T400596
			!( attributes.refListItemId && !ve.getProp( originalMwData, 'attrs', 'group' ) )
		) {
			ve.setProp( mwData, 'attrs', 'group', attributes.refGroup );
		} else if ( mwData.attrs ) {
			delete mwData.attrs.group;
		}
	}

	// If mwAttr and originalMw are the same, use originalMw to prevent reserialization,
	// unless we are writing the clipboard for use in another VE instance
	// Reserialization has the potential to reorder keys and so change the DOM unnecessarily
	if ( converter.isForParser() && originalMw && ve.compare( mwData, originalMwData ) ) {
		domElement.setAttribute( 'data-mw', originalMw );

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
		domElement.setAttribute( 'data-mw', stringifiedMwData );

		// HTML for the external clipboard, it will be ignored by the converter
		const $link = $( '<a>', doc )
			.attr( 'data-mw-group', this.getGroup( dataElement ) || null );
		$( domElement ).addClass( 'mw-ref reference' ).append(
			$link.append( $( '<span>', doc )
				.addClass( 'mw-reflink-text' )
				.append( this.getFormattedRefLinkLabel( dataElement, internalList ) )
			)
		);
	}

	return [ domElement ];
};

/***
 * Check if a previous node with the same key has already set the content.
 * If so, we don't overwrite the content of this node.
 *
 * @private
 * @static
 * @param {Object} dataElement
 * @param {ve.dm.Node[]} nodesWithSameKey
 * @return {boolean}
 * */
ve.dm.MWReferenceNode.static.isBodyContentSet = function ( dataElement, nodesWithSameKey ) {
	// Sub-refs can't set body content for other sub-refs so we can bail out early here
	if ( !dataElement.attributes.contentsUsed || dataElement.attributes.mainRefKey ) {
		return false;
	}

	const current = this.getInstanceHashObject( dataElement );
	for ( let i = 0; i < nodesWithSameKey.length; i++ ) {
		// Stop at the current node, we are only interested in earlier nodes
		if ( ve.compare( current, this.getInstanceHashObject( nodesWithSameKey[ i ].element ) ) ) {
			break;
		}

		// Yes, an earlier node is already marked as holding the content
		if ( nodesWithSameKey[ i ].getAttribute( 'contentsUsed' ) ) {
			return true;
		}
	}

	return false;
};

/***
 * Check if a sub reference node should be linked with the body content of a synthetic main node.
 * This only needs to happen in cases where the body can't move to another main ref.
 *
 * @private
 * @static
 * @param {Object} dataElement
 * @param {ve.dm.InternalListNodeGroup} nodeGroup
 * @return {string|false} the reflistItemId of the main to link to or false if not applicable
 * */
ve.dm.MWReferenceNode.static.shouldLinkSyntheticMainRef = function ( dataElement, nodeGroup ) {
	const attributes = dataElement.attributes;
	const mainRefKey = ve.getProp( attributes, 'mainRefKey' );
	const siblingSubRefs = this.getSubRefs( mainRefKey, nodeGroup );
	const isFirstNode = ve.compare(
		this.getInstanceHashObject( dataElement ),
		this.getInstanceHashObject( siblingSubRefs[ 0 ].element )
	);

	// Bail out when the current sub-ref is not the first, only the first should get linked
	if ( !isFirstNode ||
		// Bail out when the current sub-ref already has the main body
		ve.getProp( attributes, 'mw', 'isSubRefWithMainBody' )
	) {
		return false;
	}

	const mainNodes = nodeGroup.getAllReuses( mainRefKey );
	if (
		// bail out if there are other main refs that could get the content
		!mainNodes ||
		mainNodes.length > 1 ||
		// bail out if there's no synthetic main ref to link
		!ve.getProp( mainNodes[ 0 ].getAttribute( 'mw' ), 'isSyntheticMainRef' )
	) {
		return false;
	}

	// mainNodes[ 0 ] is a synthetic main ref, check if there's no other sub-ref after the first
	// that's linked
	if ( !siblingSubRefs.slice( 1 ).some(
		( node ) => ve.getProp( node.getAttribute( 'mw' ), 'isSubRefWithMainBody' )
	) ) {
		return mainNodes[ 0 ].getAttribute( 'refListItemId' );
	}

	return false;
};

/***
 * Check if the node is already storing the body content.  Returns false for unused
 * synthetic main refs.
 *
 * @private
 * @static
 * @param {Object} attributes
 * @param {ve.dm.InternalListNodeGroup} nodeGroup
 * @return {boolean}
 * */
ve.dm.MWReferenceNode.static.doesHoldBodyContent = function ( attributes, nodeGroup ) {
	// Trivial handling for normal main refs
	if ( !ve.getProp( attributes, 'mw', 'isSyntheticMainRef' ) ) {
		return ve.getProp( attributes, 'contentsUsed' );
	}

	const mainRefKey = ve.getProp( attributes, 'listKey' );
	// Sub-refs cannot have reuses, that's why using only the firstNodes is safe
	return nodeGroup.firstNodes.some(
		// Is there a sub-ref (mainRefKey exists) for the same main ref (mainRefKey is the same)
		// that already holds the main body?
		( node ) => ve.getProp( node.getAttribute( 'mw' ), 'isSubRefWithMainBody' ) &&
			node.getAttribute( 'mainRefKey' ) === mainRefKey
	);
};

/***
 * Check if the node should get the body content.  Either it had it before, is the last remaining
 * reuse or is the first node and get's it because no other node holds it.
 *
 * @private
 * @static
 * @param {Object} dataElement
 * @param {ve.dm.InternalListNodeGroup} nodeGroup
 * @return {boolean}
 * */
ve.dm.MWReferenceNode.static.shouldGetMainContent = function ( dataElement, nodeGroup ) {
	const attributes = dataElement.attributes;
	const mainContentKey = attributes.listKey;
	const mainReuses = nodeGroup.getAllReuses( mainContentKey ) || [];

	// If the reference already stored the main content before, it should be stored there again
	if ( attributes.contentsUsed ||
		// If this node is the only one it should always get the main content
		mainReuses.length <= 1
	) {
		return true;
	}

	// The node might be applicable for getting the main content but we only want to move the
	// content to the first node in the document
	const isFirstNode = ve.compare(
		this.getInstanceHashObject( dataElement ),
		this.getInstanceHashObject( mainReuses[ 0 ].element )
	);
	return isFirstNode &&
		// We only want to give this node the main content if there's no other main node after the
		// first that holds it already.
		!mainReuses.slice( 1 ).some(
			( node ) => this.doesHoldBodyContent( node.getAttributes(), nodeGroup )
		);
};

/**
 * Generate the name for a given reference
 *
 * @private
 * @static
 * @param {Object} attributes
 * @param {ve.dm.InternalList} internalList
 * @param {ve.dm.Node[]} nodesWithSameKey
 * @return {string|undefined} literal or auto generated name
 */
ve.dm.MWReferenceNode.static.generateName = function ( attributes, internalList, nodesWithSameKey ) {
	const listKey = attributes.mainRefKey || attributes.listKey;
	const keyParts = this.listKeyRegex.exec( listKey );

	// use literal name
	if ( keyParts && keyParts[ 1 ] === 'literal' ) {
		return keyParts[ 2 ];
	}

	// use auto generated name
	if ( attributes.mainRefKey ||
		nodesWithSameKey.length > 1 ||
		this.hasSubRefs( attributes, internalList )
	) {
		return internalList.getNodeGroup( attributes.listGroup ).getUniqueListKey(
			listKey,
			'literal/:'
		).slice( 'literal/'.length );
	}
};

/**
 * @private
 * @static
 * @param {string} mainRefKey
 * @param {ve.dm.InternalListNodeGroup} nodeGroup
 * @return {ve.dm.Node[]}
 */
ve.dm.MWReferenceNode.static.getSubRefs = function ( mainRefKey, nodeGroup ) {
	// Sub-refs cannot have reuses, that's why using only the firstNodes is safe
	return nodeGroup.getFirstNodesInIndexOrder().filter(
		( node ) => node.element.attributes.mainRefKey === mainRefKey
	);
};

/**
 * @private
 * @static
 * @param {Object} attributes
 * @param {ve.dm.InternalList} internalList
 * @return {boolean}
 */
ve.dm.MWReferenceNode.static.hasSubRefs = function ( attributes, internalList ) {
	// A sub-ref cannot have sub-refs, bail out fast for performance reasons
	return !attributes.mainRefKey &&
		// Sub-refs cannot have reuses, that's why using only the firstNodes is safe
		internalList.getNodeGroup( attributes.listGroup ).firstNodes.some(
			( node ) => node.getAttribute( 'mainRefKey' ) === attributes.listKey
		);
};

/**
 * Give unnamed refs a new "auto/<number>" listKey using the target document's
 * internal list autoincrement counter.
 *
 * @param {Object} dataElement Ref node data to modify
 * @param {Object<number,number>} mapping Stable map from old to new list index
 * @param {ve.dm.InternalList} newInternalList Target document internal list
 */
ve.dm.MWReferenceNode.static.remapInternalListIndexes = function (
	dataElement, mapping, newInternalList
) {
	// Remap listIndex
	dataElement.attributes.listIndex = mapping[ dataElement.attributes.listIndex ];

	// Remap listKey if it was automatically generated
	const listKeyParts = this.listKeyRegex.exec( dataElement.attributes.listKey );
	if ( listKeyParts && listKeyParts[ 1 ] === 'auto' ) {
		dataElement.attributes.listKey = this.makeListKey( newInternalList );
	}
};

/**
 * Change conflicting ref names pasted from an external document
 *
 * If a ref with name "ref-name" is pasted into a document which already has a
 * ref by that name, the new ref will be given a new name like "ref-name2" with
 * a suffix incremented until the name is unique.
 *
 * @param {Object} dataElement new ref data
 * @param {ve.dm.InternalList} newInternalList Target document's existing internalList
 */
ve.dm.MWReferenceNode.static.remapInternalListKeys = function ( dataElement, newInternalList ) {
	let suffix = '';
	// Try name, name2, name3, ... until unique
	while ( newInternalList.keys.includes( dataElement.attributes.listKey + suffix ) ) {
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
 * @private
 * @param {Object} dataElement Element data
 * @param {ve.dm.InternalList} internalList Internal list
 * @return {jQuery} Formatted label including the square brackets
 */
ve.dm.MWReferenceNode.static.getFormattedRefLinkLabel = function ( dataElement, internalList ) {
	const refGroup = dataElement.attributes.refGroup;
	const indexNumber = dataElement.attributes.placeholder ? '…' :
		ve.dm.MWReferenceNode.static.findIndexNumber( dataElement, internalList );
	const label = ( refGroup ? refGroup + ' ' : '' ) + indexNumber;

	return $( '<span>' ).addClass( 'cite-bracket' ).text( '[' )
		.add( document.createTextNode( label ) )
		.add( $( '<span>' ).addClass( 'cite-bracket' ).text( ']' ) );
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
		MWDocumentReferences.static.refsForDoc( internalList.getDocument() )
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
 * @return {jQuery} Formatted label including the square brackets
 */
ve.dm.MWReferenceNode.prototype.getFormattedRefLinkLabel = function () {
	return this.constructor.static.getFormattedRefLinkLabel(
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
 * Save a copy of this ref in the reflist as a backup
 *
 * This mechanism should be used whenever a ref becomes the main ref for a new
 * subref.  It allows the main ref to be deleted without losing a connection to
 * the main ref content.
 *
 * @param {ve.dm.Surface} surface
 */
ve.dm.MWReferenceNode.prototype.copySyntheticRefIntoReferencesList = function ( surface ) {
	// Get the ReferencesList we want to move the node into
	const docChildren = this.getDocument().getDocumentNode().getChildren();
	const refListNode = docChildren.find(
		( node ) => node.type === 'mwReferencesList' &&
			node.getAttribute( 'refGroup' ) === this.getGroup()
	);
	if ( !refListNode ) {
		// FIXME: There is no guarantee we have a corresponding reflist in the document when it's
		// not the default group. What to do then?
		return;
	}
	const refListNodeRange = refListNode.getRange();

	const attributes = ve.copy( this.getAttributes() );
	ve.setProp( attributes, 'mw', 'isSyntheticMainRef', true );
	ve.setProp( attributes, 'contentsUsed', true );
	if ( !ve.getProp( attributes, 'refListItemId' ) ) {
		// This will be the value of the `id` attribute of reference list item
		const refListItemId = 'cite_note-' +
			attributes.listGroup + '-' +
			attributes.listKey + '-' +
			attributes.listIndex;
		ve.setProp( attributes, 'refListItemId', refListItemId.replace( /[_\s]+/u, '_' ) );
	}
	const txInsert = ve.dm.TransactionBuilder.static.newFromInsertion(
		this.getDocument(), refListNodeRange.to, [
			{
				type: 'mwReference',
				attributes,
				originalDomElementsHash: Math.random()
			},
			{ type: '/mwReference' }
		]
	);
	surface.change( txInsert );
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
		// Phabricator T401495
		if ( this.getAttribute( 'mainRefKey' ) ) {
			ve.track( 'activity.subReference', { action: 'delete-subref' } );
		}
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
	// This works with the empty default group because it is "mwReference/", not ""
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

/**
 * Set the footnote number
 *
 * @param {number[]} groupItemIndex Pair of numbers giving the top-level and sub-reference indexes.
 */
ve.dm.MWReferenceNode.prototype.setGroupIndex = function ( groupItemIndex ) {
	// TODO: refine where this is stored
	this.groupItemIndex = groupItemIndex;
};

module.exports = ve.dm.MWReferenceNode;
