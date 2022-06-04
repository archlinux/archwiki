/*!
 * VisualEditor DataModel MWReferenceNode class.
 *
 * @copyright 2011-2018 VisualEditor Team's Cite sub-team and others; see AUTHORS.txt
 * @license MIT
 */

/**
 * DataModel MediaWiki reference node.
 *
 * @class
 * @extends ve.dm.LeafNode
 * @mixin ve.dm.FocusableNode
 *
 * @constructor
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

ve.dm.MWReferenceNode.static.toDataElement = function ( domElements, converter ) {
	function getReflistItemHtml( id ) {
		var elem = converter.getHtmlDocument().getElementById( id );
		return elem && elem.innerHTML || '';
	}

	var mwDataJSON = domElements[ 0 ].getAttribute( 'data-mw' );
	var mwData = mwDataJSON ? JSON.parse( mwDataJSON ) : {};
	var reflistItemId = mwData.body && mwData.body.id;
	var body = ( mwData.body && mwData.body.html ) ||
		( reflistItemId && getReflistItemHtml( reflistItemId ) ) ||
		'';
	var refGroup = mwData.attrs && mwData.attrs.group || '';
	var listGroup = this.name + '/' + refGroup;
	var autoKeyed = !mwData.attrs || mwData.attrs.name === undefined;
	var listKey = autoKeyed ?
		'auto/' + converter.internalList.getNextUniqueNumber() :
		'literal/' + mwData.attrs.name;
	var queueResult = converter.internalList.queueItemHtml( listGroup, listKey, body );
	var listIndex = queueResult.index;
	var contentsUsed = ( body !== '' && queueResult.isNew );

	var dataElement = {
		type: this.name,
		attributes: {
			mw: mwData,
			originalMw: mwDataJSON,
			listIndex: listIndex,
			listGroup: listGroup,
			listKey: listKey,
			refGroup: refGroup,
			contentsUsed: contentsUsed
		}
	};
	if ( reflistItemId ) {
		dataElement.attributes.refListItemId = reflistItemId;
	}
	return dataElement;
};

ve.dm.MWReferenceNode.static.toDomElements = function ( dataElement, doc, converter ) {
	var isForClipboard = converter.isForClipboard(),
		el = doc.createElement( 'sup' );

	el.setAttribute( 'typeof', 'mw:Extension/ref' );

	var mwData = dataElement.attributes.mw ? ve.copy( dataElement.attributes.mw ) : {};
	mwData.name = 'ref';

	if ( isForClipboard || converter.isForParser() ) {
		var setContents = dataElement.attributes.contentsUsed;

		// This call rebuilds the document tree if it isn't built already (e.g. on a
		// document slice), so only use when necessary (i.e. not in preview mode)
		var itemNode = converter.internalList.getItemNode( dataElement.attributes.listIndex );
		var itemNodeRange = itemNode.getRange();

		var keyedNodes = converter.internalList
			.getNodeGroup( dataElement.attributes.listGroup )
			.keyedNodes[ dataElement.attributes.listKey ];

		var i, iLen;
		var contentsAlreadySet = false;
		if ( setContents ) {
			// Check if a previous node has already set the content. If so, we don't overwrite this
			// node's contents.
			if ( keyedNodes ) {
				for ( i = 0, iLen = keyedNodes.length; i < iLen; i++ ) {
					if (
						ve.compare(
							this.getInstanceHashObject( keyedNodes[ i ].element ),
							this.getInstanceHashObject( dataElement )
						)
					) {
						break;
					}
					if ( keyedNodes[ i ].element.attributes.contentsUsed ) {
						contentsAlreadySet = true;
						break;
					}
				}
			}
		} else {
			// Check if any other nodes with this key provided content. If not
			// then we attach the contents to the first reference with this key

			// Check that this is the first reference with its key
			if (
				keyedNodes &&
				ve.compare(
					this.getInstanceHashObject( dataElement ),
					this.getInstanceHashObject( keyedNodes[ 0 ].element )
				)
			) {
				setContents = true;
				// Check no other reference originally defined the contents
				// As this is keyedNodes[0] we can start at 1
				for ( i = 1, iLen = keyedNodes.length; i < iLen; i++ ) {
					if ( keyedNodes[ i ].element.attributes.contentsUsed ) {
						setContents = false;
						break;
					}
				}
			}
		}

		// Add reference contents to data-mw.
		if ( setContents && !contentsAlreadySet ) {
			var itemNodeWrapper = doc.createElement( 'div' );
			var originalHtmlWrapper = doc.createElement( 'div' );
			converter.getDomSubtreeFromData(
				itemNode.getDocument().getFullData( itemNodeRange, 'roundTrip' ),
				itemNodeWrapper
			);
			var itemNodeHtml = itemNodeWrapper.innerHTML; // Returns '' if itemNodeWrapper is empty
			var originalHtml = ve.getProp( mwData, 'body', 'html' ) ||
				( ve.getProp( mwData, 'body', 'id' ) !== undefined && itemNode.getAttribute( 'originalHtml' ) ) ||
				'';
			originalHtmlWrapper.innerHTML = originalHtml;
			// Only set body.html if itemNodeHtml and originalHtml are actually different,
			// or we are writing the clipboard for use in another VE instance
			if ( isForClipboard || !originalHtmlWrapper.isEqualNode( itemNodeWrapper ) ) {
				ve.setProp( mwData, 'body', 'html', itemNodeHtml );
			}
		}

		// If we have no internal item data for this reference, don't let it get pasted into
		// another VE document. T110479
		if ( isForClipboard && itemNodeRange.isCollapsed() ) {
			el.setAttribute( 'data-ve-ignore', 'true' );
		}

		// Generate name
		var name;
		var listKeyParts = dataElement.attributes.listKey.match( this.listKeyRegex );
		if ( listKeyParts[ 1 ] === 'auto' ) {
			// Only render a name if this key was reused
			if ( keyedNodes.length > 1 ) {
				// Allocate a unique list key, then strip the 'literal/'' prefix
				name = converter.internalList.getUniqueListKey(
					dataElement.attributes.listGroup,
					dataElement.attributes.listKey,
					// Generate a name starting with ':' to distinguish it from normal names
					'literal/:'
				).slice( 'literal/'.length );
			}
		} else {
			// Use literal name
			name = listKeyParts[ 2 ];
		}
		// Set name
		if ( name !== undefined ) {
			ve.setProp( mwData, 'attrs', 'name', name );
		}

		// Set or clear group
		if ( dataElement.attributes.refGroup !== '' ) {
			ve.setProp( mwData, 'attrs', 'group', dataElement.attributes.refGroup );
		} else if ( mwData.attrs ) {
			delete mwData.attrs.refGroup;
		}
	}

	// If mwAttr and originalMw are the same, use originalMw to prevent reserialization,
	// unless we are writing the clipboard for use in another VE instance
	// Reserialization has the potential to reorder keys and so change the DOM unnecessarily
	var originalMw = dataElement.attributes.originalMw;
	if ( converter.isForParser() && originalMw && ve.compare( mwData, JSON.parse( originalMw ) ) ) {
		el.setAttribute( 'data-mw', originalMw );

		// Return the original DOM elements if possible
		if ( dataElement.originalDomElementsHash !== undefined ) {
			return ve.copyDomElements( converter.getStore().value( dataElement.originalDomElementsHash ), doc );
		}
	} else {
		el.setAttribute( 'data-mw', JSON.stringify( mwData ) );

		// HTML for the external clipboard, it will be ignored by the converter
		var group = this.getGroup( dataElement );
		var $link = $( '<a>', doc ).css(
			'counterReset', 'mw-Ref ' + this.getIndex( dataElement, converter.internalList )
		);
		if ( group ) {
			$link.attr( 'data-mw-group', this.getGroup( dataElement ) );
		}
		$( el ).addClass( 'mw-ref reference' ).append(
			$link.append(
				$( '<span>', doc ).addClass( 'mw-reflink-text' ).text( this.getIndexLabel( dataElement, converter.internalList ) )
			)
		);
	}

	return [ el ];
};

ve.dm.MWReferenceNode.static.remapInternalListIndexes = function ( dataElement, mapping, internalList ) {
	var listKeyParts;
	// Remap listIndex
	dataElement.attributes.listIndex = mapping[ dataElement.attributes.listIndex ];

	// Remap listKey if it was automatically generated
	listKeyParts = dataElement.attributes.listKey.match( this.listKeyRegex );
	if ( listKeyParts[ 1 ] === 'auto' ) {
		dataElement.attributes.listKey = 'auto/' + internalList.getNextUniqueNumber();
	}
};

ve.dm.MWReferenceNode.static.remapInternalListKeys = function ( dataElement, internalList ) {
	var suffix = '';
	// Try name, name2, name3, ... until unique
	while ( internalList.keys.indexOf( dataElement.attributes.listKey + suffix ) !== -1 ) {
		suffix = suffix ? suffix + 1 : 2;
	}
	if ( suffix ) {
		dataElement.attributes.listKey = dataElement.attributes.listKey + suffix;
	}
};

/**
 * Gets the index for the reference
 *
 * @static
 * @param {Object} dataElement Element data
 * @param {ve.dm.InternalList} internalList Internal list
 * @return {number} Index
 */
ve.dm.MWReferenceNode.static.getIndex = function ( dataElement, internalList ) {
	var listIndex, listGroup, position,
		overrideIndex = ve.getProp( dataElement, 'internal', 'overrideIndex' );

	if ( overrideIndex ) {
		return overrideIndex;
	}

	listIndex = dataElement.attributes.listIndex;
	listGroup = dataElement.attributes.listGroup;
	position = internalList.getIndexPosition( listGroup, listIndex );

	return position + 1;
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
 * @return {string} Reference label
 */
ve.dm.MWReferenceNode.static.getIndexLabel = function ( dataElement, internalList ) {
	var refGroup = dataElement.attributes.refGroup,
		index = dataElement.attributes.placeholder ? '…' :
			ve.dm.MWReferenceNode.static.getIndex( dataElement, internalList );

	return '[' + ( refGroup ? refGroup + ' ' : '' ) + index + ']';
};

/**
 * @inheritdoc
 */
ve.dm.MWReferenceNode.static.cloneElement = function () {
	var clone = ve.dm.MWReferenceNode.super.static.cloneElement.apply( this, arguments );
	delete clone.attributes.contentsUsed;
	delete clone.attributes.mw;
	delete clone.attributes.originalMw;
	// HACK: Generate a fake hash so this element is never instance comparable to other elements
	// Without originalMw this hash will not get used in toDomElements
	clone.originalDomElementsHash = Math.random();
	return clone;
};

/**
 * @inheritdoc
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
 * @inheritdoc
 */
ve.dm.MWReferenceNode.static.describeChange = function ( key, change ) {
	if ( key === 'refGroup' ) {
		if ( !change.from ) {
			return ve.htmlMsg( 'cite-ve-changedesc-ref-group-to', this.wrapText( 'ins', change.to ) );
		} else if ( !change.to ) {
			return ve.htmlMsg( 'cite-ve-changedesc-ref-group-from', this.wrapText( 'del', change.from ) );
		} else {
			return ve.htmlMsg( 'cite-ve-changedesc-ref-group-both', this.wrapText( 'del', change.from ), this.wrapText( 'ins', change.to ) );
		}
	}
};

/* Methods */

/**
 * Don't allow reference nodes to be edited if we can't find their contents.
 *
 * @inheritdoc
 */
ve.dm.MWReferenceNode.prototype.isEditable = function () {
	var internalItem = this.getInternalItem();
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
	return this.constructor.static.getIndexLabel( this.element, this.getDocument().getInternalList() );
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
		this.registeredListGroup = this.element.attributes.listGroup;
		this.registeredListKey = this.element.attributes.listKey;
		this.registeredListIndex = this.element.attributes.listIndex;
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

ve.dm.MWReferenceNode.prototype.onAttributeChange = function ( key, from, to ) {
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
