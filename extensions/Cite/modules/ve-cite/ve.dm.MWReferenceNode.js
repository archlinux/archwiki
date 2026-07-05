'use strict';

/*!
 * VisualEditor DataModel MWReferenceNode class.
 *
 * @copyright 2011-2018 VisualEditor Team's Cite sub-team and others; see AUTHORS.txt
 * @license MIT
 */

const MWDocumentReferences = require( './ve.dm.MWDocumentReferences.js' );
const MWReferenceKeyGenerator = require( './ve.dm.MWReferenceKeyGenerator.js' );

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
 * @private
 * @param {ve.dm.ModelFromDomConverter} converter
 * @param {string|null} [refListItemId]
 * @return {string}
 */
ve.dm.MWReferenceNode.static.getBodyFromReflist = function ( converter, refListItemId ) {
	if ( !refListItemId ) {
		return '';
	}
	const elem = converter.getHtmlDocument().getElementById( refListItemId );
	return elem && elem.innerHTML || '';
};

/**
 * @private
 * @param {ve.dm.ModelFromDomConverter} converter
 * @param {string|null} [refListItemId]
 * @return {string}
 */
ve.dm.MWReferenceNode.static.getGroupFromReflist = function ( converter, refListItemId ) {
	if ( !refListItemId ) {
		return '';
	}
	const elem = converter.getHtmlDocument().getElementById( refListItemId );
	return elem && elem.getAttribute( 'data-mw-group' ) || '';
};

/**
 * @private
 * @param {ve.dm.ModelFromDomConverter} converter
 * @param {string|null} [refListItemId]
 * @return {Array(string,number)|null} [listKey, index] or null when the sub-ref is unknown
 */
ve.dm.MWReferenceNode.static.lookupSubRefIndex = function ( converter, refListItemId ) {
	return converter.subrefLookup && refListItemId && converter.subrefLookup[ refListItemId ];
};

/**
 * @private
 * @param {ve.dm.ModelFromDomConverter} converter
 * @param {string|null} [refListItemId]
 * @param {string} listKey
 * @param {number} index
 */
ve.dm.MWReferenceNode.static.insertSubRefIndex = function ( converter, refListItemId, listKey, index ) {
	if ( !refListItemId ) {
		return;
	}
	if ( !converter.subrefLookup ) {
		converter.subrefLookup = {};
	}
	converter.subrefLookup[ refListItemId ] = [ listKey, index ];
};

/**
 * Transform parsoid HTML DOM to constructor parameters for VE reference nodes.
 *
 * @param {Node[]} domElements DOM elements to convert
 * @param {ve.dm.ModelFromDomConverter} converter
 * @return {Object|Array|null} Data element or array of linear model data, or null to alienate
 */
ve.dm.MWReferenceNode.static.toDataElement = function ( domElements, converter ) {
	const mwDataJSON = domElements[ 0 ].getAttribute( 'data-mw' );
	const mwData = mwDataJSON ? JSON.parse( mwDataJSON ) : {};
	const mwAttrs = mwData.attrs || {};

	// Load the item's embedded HTML or find it in the reflist.
	const refListItemId = ve.getProp( mwData, 'body', 'id' );
	const body = ve.getProp( mwData, 'body', 'html' ) || this.getBodyFromReflist( converter, refListItemId );
	const refName = ( mwData.mainRef ? null : mwAttrs.name );
	if ( converter.isFromClipboard() && !( refName || body ) ) {
		// Pasted reference has neither a name nor body HTML, must have
		// come from Parsoid read mode directly. (T389518)
		return [];
	}

	const refGroup = mwAttrs.group || this.getGroupFromReflist( converter, refListItemId );
	const listGroup = this.name + '/' + refGroup;

	// FIXME When ve.dm.InternalList takes more responsibilty for sub-refs the code might move there
	const internalList = converter.getInternalList();
	if ( !internalList.itemHtmlQueue.length ) {
		// The property needs to be reset when we start parsing a new doc
		ve.dm.converter.modelFromDomConverter.subrefLookup = null;
	}

	let listKey, index, isNew;
	const lookupResult = mwData.mainRef && this.lookupSubRefIndex( converter, refListItemId );
	if ( lookupResult ) {
		[ listKey, index ] = lookupResult;
		isNew = false;
	} else {
		listKey = MWReferenceKeyGenerator.makeListKey( internalList, refName );
		const { index: qIndex, isNew: qNew } = internalList.queueItemHtml( listGroup, listKey, body );
		index = qIndex;
		isNew = qNew;
		if ( mwData.mainRef ) {
			this.insertSubRefIndex( converter, refListItemId, listKey, index );
		}
	}

	// Sub-refs will always get body content for the details attribute so we use contentsUsed to
	// store if they had main content in the main+details case
	const contentsUsed = !!( mwData.mainRef ? mwData.mainBody : isNew && body );

	const dataElement = {
		type: this.name,
		attributes: {
			mw: mwData,
			originalMw: mwDataJSON,
			listGroup,
			listKey,
			listIndex: index,
			refGroup,
			contentsUsed
		}
	};

	if ( mwData.mainRef && mw.config.get( 'wgCiteSubReferencing' ) ) {
		// Create a main ref internalListItem
		const mainListKey = MWReferenceKeyGenerator.makeListKey(
			internalList,
			mwData.mainRef
		);
		dataElement.attributes.mainListKey = mainListKey;
		let mainHtml;
		if ( mw.config.get( 'wgCiteRemoveSyntheticRefsUnsafe' ) ) {
			// If this is a non-synthetic main+details then read its contents from the
			// list item fragment.  We skip synthetic refs because they may have been
			// produced by a transclusion.
			mainHtml = !mwData.isSyntheticMainRef &&
				mwData.mainBody &&
				this.getBodyFromReflist( converter, mwData.mainBody );
		}
		const { index: mainListIndex } = internalList.queueItemHtml( listGroup, mainListKey, mainHtml || '' );
		dataElement.attributes.mainListIndex = mainListIndex;
	}

	if ( refListItemId ) {
		dataElement.attributes.refListItemId = refListItemId;
	}

	if ( mw.config.get( 'wgCiteRemoveSyntheticRefsUnsafe' ) && ve.getProp( mwData, 'isSyntheticMainRef' ) ) {
		return [];
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
	const attributes = dataElement.attributes;
	if ( attributes.placeholder ) {
		return [];
	}
	// Create output DOM element
	const domElement = doc.createElement( 'sup' );
	domElement.setAttribute( 'typeof', 'mw:Extension/ref' );

	const isSubRef = this.isSubRef( attributes );

	const internalList = converter.getInternalList();
	const mwData = attributes.mw ? ve.copy( attributes.mw ) : {};
	const originalMw = attributes.originalMw;
	const originalMwData = originalMw ? JSON.parse( originalMw ) : {};
	mwData.name = 'ref';

	if ( converter.isForClipboard() || converter.isForParser() ) {
		// This call rebuilds the document tree if it isn't built already (e.g. on a
		// document slice), so only use when necessary (i.e. not in preview mode)
		const itemNode = internalList.getItemNode( attributes.listIndex );
		const itemNodeRange = itemNode.getRange();
		const hasEmptyItem = itemNodeRange.isCollapsed();

		const nodeGroup = internalList.getNodeGroup( attributes.listGroup );
		const nodeReuses = nodeGroup.getAllReusesByListIndex( attributes.listIndex ) || [];

		// Generate and add name to data-mw
		const isReused = nodeReuses.length > 1 || this.hasSubRefs( attributes, internalList );
		const name = MWReferenceKeyGenerator.generateName( attributes, internalList, isReused );
		if ( name !== undefined ) {
			ve.setProp( mwData, 'attrs', 'name', name );
		}

		if ( isSubRef ) {
			// this is always either the literal name that was already there or the
			// auto generated literal from above
			ve.setProp( mwData, 'mainRef', name );
		}

		const shouldGetMainContent = this.shouldGetMainContent( dataElement, nodeGroup );

		// Set reference content on data-mw
		if ( isSubRef ||
			( !this.shouldAvoidContentOverride( dataElement, nodeReuses ) && shouldGetMainContent )
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
			// unless we are writing the clipboard for use in another VE instance
			if ( converter.isForClipboard() || !originalHtmlWrapper.isEqualNode( currentHtmlWrapper ) ) {
				ve.setProp( mwData, 'body', 'html', currentHtmlWrapper.innerHTML );
			}
		}

		// Set flags for sub-refs with body content on data-mw
		if ( isSubRef && shouldGetMainContent ) {
			const mainKeyReuses = nodeGroup.getAllReuses( attributes.mainListKey ) || [];
			const refListNode = mainKeyReuses.find( ( node ) => node.getAttribute( 'refListItemId' ) );
			const refListItemId = ( refListNode && refListNode.getAttribute( 'refListItemId' ) ) ||
				MWReferenceKeyGenerator.makeRefListItemId( attributes.mainListIndex );
			ve.setProp( mwData, 'mainBody', refListItemId );
		}

		// Set or clear group on data-mw
		if ( attributes.refGroup &&
			// List defined references that had no group before should not save their group T400596
			!( attributes.refListItemId && !ve.getProp( originalMwData, 'attrs', 'group' ) )
		) {
			ve.setProp( mwData, 'attrs', 'group', attributes.refGroup );
		} else if ( mwData.attrs ) {
			delete mwData.attrs.group;
		}

		// If we have no internal item data for this reference, don't let it get pasted into
		// another VE document. T110479
		if ( converter.isForClipboard() && hasEmptyItem ) {
			domElement.setAttribute( 'data-ve-ignore', '' );
		}
	}

	// If mwData and originalMwData are the same, use originalMwData to prevent reserialization,
	// unless we are writing the clipboard for use in another VE instance
	if ( converter.isForParser() && originalMw && ve.compare( mwData, originalMwData ) ) {
		// Return the original DOM elements if possible
		if ( dataElement.originalDomElementsHash !== undefined ) {
			return ve.copyDomElements(
				converter.getStore().value( dataElement.originalDomElementsHash ), doc );
		}

		domElement.setAttribute( 'data-mw', originalMw );
	} else {
		let stringifiedMwData = JSON.stringify( mwData );
		if ( converter.isForClipboard() ) {
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
 * FIXME: I guess this method needs to take sub-refs with the main key into
 * consideration, not only reuses?
 *
 * @private
 * @static
 * @param {Object} dataElement
 * @param {ve.dm.Node[]} nodeReuses
 * @return {boolean}
 * */
ve.dm.MWReferenceNode.static.shouldAvoidContentOverride = function ( dataElement, nodeReuses ) {
	// Avoiding an override is irrelevant when our node had content before or is a sub-ref
	if ( !dataElement.attributes.contentsUsed ||
		this.isSubRef( dataElement.attributes )
	) {
		return false;
	}

	const current = this.getInstanceHashObject( dataElement );
	for ( let i = 0; i < nodeReuses.length; i++ ) {
		// Stop at the current node, we are only interested in earlier nodes
		if ( ve.compare( current, this.getInstanceHashObject( nodeReuses[ i ].element ) ) ) {
			break;
		}

		// Yes, an earlier node is already marked as holding the content
		if ( nodeReuses[ i ].getAttribute( 'contentsUsed' ) ) {
			return true;
		}
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

	const mainListIndex = ve.getProp( attributes, 'listIndex' );
	const subRefs = this.getSubRefs( mainListIndex, nodeGroup );
	return subRefs.some(
		// Is there a sub-ref that already holds the main body?
		( node ) => ve.getProp( node.getAttribute( 'mw' ), 'mainBody' )
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
	const mainListIndex = this.isSubRef( attributes ) ? attributes.mainListIndex : attributes.listIndex;
	const mainReuses = this.getRefsWithSameMain( mainListIndex, nodeGroup );

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
			( node ) => {
				if ( node.isSubRef() ) {
					return node.getAttribute( 'contentsUsed' );
				}
				return this.doesHoldBodyContent( node.element.attributes, nodeGroup );
			}
		);
};

/**
 * Return a list of nodes sharing the same main content.  This can be sub-refs or main refs.
 * This list includes all nodes in index.  Reuses are expaned in the list according to the
 * first occurence of the first node.  So the list is not in document order.
 *
 * @private
 * @static
 * @param {number} mainListIndex
 * @param {ve.dm.InternalListNodeGroup} nodeGroup
 * @return {ve.dm.MWReferenceNode[]}
 */
ve.dm.MWReferenceNode.static.getRefsWithSameMain = function ( mainListIndex, nodeGroup ) {
	const keys = nodeGroup.getKeysInIndexOrder();
	const results = [];
	keys.forEach( ( key ) => {
		const reuses = nodeGroup.getAllReuses( key ) || [];
		// Sub-ref reuses share the mainListIndex, that's why stopping after the first match is fine
		if ( reuses.some( ( node ) => ( node.getAttribute( 'mainListIndex' ) === mainListIndex ) ||
			node.getAttribute( 'listIndex' ) === mainListIndex )
		) {
			results.push( ...reuses );
		}
	} );
	return results;
};

/**
 * @private
 * @static
 * @param {number} mainListIndex
 * @param {ve.dm.InternalListNodeGroup} nodeGroup
 * @return {ve.dm.Node[]}
 */
ve.dm.MWReferenceNode.static.getSubRefs = function ( mainListIndex, nodeGroup ) {
	const keys = nodeGroup.getKeysInIndexOrder();
	const results = [];
	keys.forEach( ( key ) => {
		const reuses = nodeGroup.getAllReuses( key ) || [];
		// Sub-ref reuses share the mainListIndex, that's why stopping after the first match is fine
		if ( reuses.some( ( node ) => node.getAttribute( 'mainListIndex' ) === mainListIndex ) ) {
			results.push( ...reuses );
		}
	} );
	return results;
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
	return !this.isSubRef( attributes ) &&
		// Sub-ref reuses share the mainListIndex, that's why using only the firstNodes is safe
		internalList.getNodeGroup( attributes.listGroup ).firstNodes.some(
			( node ) => node.getAttribute( 'mainListIndex' ) === attributes.listIndex
		);
};

/**
 * @private
 * @static
 * @param {Object} attributes
 * @return {boolean}
 */
ve.dm.MWReferenceNode.static.isSubRef = function ( attributes ) {
	return attributes.mainListIndex !== undefined ||
		// TODO: Temporary redundancy, please remove as soon as possible
		!!attributes.mainListKey;
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
	if ( !MWReferenceKeyGenerator.isLiteralListKey( dataElement.attributes.listKey ) ) {
		dataElement.attributes.listKey = MWReferenceKeyGenerator.makeListKey( newInternalList );
	} else {
		ve.error( 'T420107 ve.dm.MWReferenceNode.remapInternalListIndexes() called with named ref' );
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
	const group = newInternalList.getNodeGroup( dataElement.attributes.listGroup );
	if ( !group ) {
		return;
	}

	let suffix = '';
	// Try name, name2, name3, ... until unique
	while ( group.getAllReuses( dataElement.attributes.listKey + suffix ) ) {
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
	const indexNumber = !dataElement.attributes.placeholder &&
		ve.dm.MWReferenceNode.static.findIndexNumber( dataElement, internalList );
	const label = ( refGroup ? refGroup + ' ' : '' ) + ( indexNumber || '…' );

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
 * @return {string|undefined} footnote number ready for rendering
 */
ve.dm.MWReferenceNode.static.findIndexNumber = function ( dataElement, internalList ) {
	return ve.getProp( dataElement, 'internal', 'overrideIndex' ) ||
		MWDocumentReferences.static.refsForDoc( internalList.getDocument() )
			.getGroupRefs( dataElement.attributes.refGroup )
			.getIndexLabel( dataElement.attributes.listIndex );
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
 * @private
 * @return {boolean}
 */
ve.dm.MWReferenceNode.prototype.isSubRef = function () {
	return this.constructor.static.isSubRef( this.element.attributes );
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
 * @return {string|undefined} Footnote number ready for rendering
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
	if ( mw.config.get( 'wgCiteRemoveSyntheticRefsUnsafe' ) ) {
		return;
	}

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

	const attributes = ve.copy( this.element.attributes );
	ve.setProp( attributes, 'mw', 'isSyntheticMainRef', true );
	ve.setProp( attributes, 'contentsUsed', true );
	if ( !ve.getProp( attributes, 'refListItemId' ) ) {
		// This will be the value of the `id` attribute of the reference list item
		const refListItemId = MWReferenceKeyGenerator.makeRefListItemId( attributes.listIndex );
		ve.setProp( attributes, 'refListItemId', refListItemId );
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
		if ( this.isSubRef() ) {
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
	} else if (
		( key === 'listGroup' && this.registeredListGroup !== to ) ||
		( key === 'listKey' && this.registeredListKey !== to )
	) {
		// Need the old list keys and indexes, so we register them in addToInternalList
		// They've already been updated in this.element.attributes before this code runs
		this.removeFromInternalList();
		this.addToInternalList();
	}
};

module.exports = ve.dm.MWReferenceNode;
