'use strict';

/*!
 * VisualEditor DataModel MWReferencesListNode class.
 *
 * @copyright 2011-2018 VisualEditor Team's Cite sub-team and others; see AUTHORS.txt
 * @license MIT
 */

const MWDocumentReferences = require( './ve.dm.MWDocumentReferences.js' );

/**
 * DataModel MediaWiki references list node.
 *
 * @constructor
 * @extends ve.dm.BranchNode
 * @mixes ve.dm.FocusableNode
 * @param {Object} [element] Reference to element in linear model
 * @param {ve.dm.Node[]} [children]
 */
ve.dm.MWReferencesListNode = function VeDmMWReferencesListNode() {
	// Parent constructor
	ve.dm.MWReferencesListNode.super.apply( this, arguments );

	// Mixin constructors
	ve.dm.FocusableNode.call( this );
};

/* Inheritance */

OO.inheritClass( ve.dm.MWReferencesListNode, ve.dm.BranchNode );

OO.mixinClass( ve.dm.MWReferencesListNode, ve.dm.FocusableNode );

/* Methods */

ve.dm.MWReferencesListNode.prototype.isEditable = function () {
	return !this.getAttribute( 'templateGenerated' );
};

/* Static members */

ve.dm.MWReferencesListNode.static.name = 'mwReferencesList';

ve.dm.MWReferencesListNode.static.handlesOwnChildren = true;

ve.dm.MWReferencesListNode.static.ignoreChildren = true;

ve.dm.MWReferencesListNode.static.matchTagNames = null;

ve.dm.MWReferencesListNode.static.matchRdfaTypes = [ 'mw:Extension/references', 'mw:Transclusion' ];

// Allow TemplateStyles in the matching element
ve.dm.MWReferencesListNode.static.allowedRdfaTypes = [ 'mw:Extension/templatestyles' ];

// e.g. with a {{reflist}} with TemplateStyles
ve.dm.MWReferencesListNode.static.enableAboutGrouping = true;

// This node has the same specificity as ve.dm.MWTranslcusionNode and only matches
// ahead of it because it is registered later (via a dependency in ResourceLoader)
// TODO: Make this less fragile.
ve.dm.MWReferencesListNode.static.matchFunction = function ( domElement ) {
	function hasTypeof( el, type ) {
		return ( el.getAttribute( 'typeof' ) || '' ).includes( type );
	}
	function isRefList( el ) {
		return el && el.nodeType === Node.ELEMENT_NODE && hasTypeof( el, 'mw:Extension/references' );
	}
	// If the template generated only a reference list, treat it as a ref list (T52769)
	return isRefList( domElement ) ||
		// A div-wrapped reference list
		( domElement.children.length === 1 && isRefList( domElement.children[ 0 ] ) ) ||
		// TemplateStyles, about-grouped to a div-wrapped reference list
		(
			hasTypeof( domElement, 'mw:Extension/templatestyles' ) &&
			domElement.hasAttribute( 'about' ) &&
			domElement.nextElementSibling &&
			domElement.nextElementSibling.getAttribute( 'about' ) === domElement.getAttribute( 'about' ) &&
			// A div-wrapped reference list
			domElement.nextElementSibling.children.length === 1 &&
			isRefList( domElement.nextElementSibling.children[ 0 ] )
			// TODO: We should probably check there aren't subsequent elements. This
			// and the above checks would be easier if the matchFunction was passed
			// all the elements in the about group.
		);
};

ve.dm.MWReferencesListNode.static.preserveHtmlAttributes = false;

/**
 * Transform parsoid HTML DOM to constructor parameters for VE referenceList nodes.
 *
 * @param {Node[]} domElements DOM elements to convert
 * @param {ve.dm.ModelFromDomConverter} converter
 * @return {Object|Array|null} Data element or array of linear model data, or null to alienate
 */
ve.dm.MWReferencesListNode.static.toDataElement = function ( domElements, converter ) {
	const type = domElements[ 0 ].getAttribute( 'typeof' ) || '';

	let refListNode;
	// We may have matched a mw:Transclusion wrapping a reference list, so pull out the refListNode
	if ( type.includes( 'mw:Extension/references' ) ) {
		refListNode = domElements[ 0 ];
	} else {
		refListNode = domElements[ 0 ].querySelector( '[typeof*="mw:Extension/references"]' ) ||
			// In the TemplateStyles case, the ref list is in the second element
			domElements[ 1 ].querySelector( '[typeof*="mw:Extension/references"]' );
	}

	const mwDataJSON = refListNode.getAttribute( 'data-mw' );
	const mwData = mwDataJSON ? JSON.parse( mwDataJSON ) : {};
	const mwAttrs = mwData.attrs || {};
	const refGroup = mwAttrs.group || '';
	const responsiveAttr = mwAttrs.responsive;
	const listGroup = 'mwReference/' + refGroup;
	const templateGenerated = type.includes( 'mw:Transclusion' );
	const isResponsiveDefault = mw.config.get( 'wgCiteResponsiveReferences' );

	const referencesListElement = {
		type: this.name,
		attributes: {
			mw: mwData,
			originalMw: mwDataJSON,
			refGroup,
			listGroup,
			isResponsive: responsiveAttr !== undefined ? responsiveAttr !== '0' : isResponsiveDefault,
			templateGenerated
		}
	};
	if ( mwData.body && mwData.body.html && !templateGenerated ) {
		// Process the nodes in .body.html as if they were this node's children
		// Don't process template-generated reflists, that mangles the content (T209493)
		const contentsDiv = domElements[ 0 ].ownerDocument.createElement( 'div' );
		contentsDiv.innerHTML = mwData.body.html;
		const contentsData = converter.getDataFromDomClean( contentsDiv );
		return [
			referencesListElement,
			...contentsData,
			{ type: '/' + this.name }
		];
	}
	return referencesListElement;
};

/**
 * Transform referenceList data elements from the linear model to HTML DOM elements as input for
 * the Parsoid parser.
 *
 * @param {Object[]} data
 * @param {HTMLDocument} doc
 * @param {ve.dm.DomFromModelConverter} converter
 * @return {HTMLElement[]}
 */
ve.dm.MWReferencesListNode.static.toDomElements = function ( data, doc, converter ) {
	const dataElement = data[ 0 ];

	// TODO: handle transclusion edge case

	// If we are sending a template generated ref back to Parsoid, output it as a
	// template.  This works because the dataElement already has mw, originalMw
	// and originalDomIndex properties.
	if ( dataElement.attributes.templateGenerated && converter.isForParser() ) {
		return ve.dm.MWTransclusionNode.static
			.toDomElements.call( this, dataElement, doc, converter );
	}

	const updatedMw = ve.dm.MWReferencesListNode.static.updatedMwForDom( data, doc, converter );

	let domElements = [ doc.createElement( 'div' ) ];
	if ( !converter.isForParser() ) {
		// Output needs to be read so re-render
		const modelNode = ve.dm.nodeFactory.createFromElement( dataElement );
		// Build from original doc's internal list to get all refs (T186407)
		modelNode.setDocument( converter.originalDocInternalList.getDocument() );
		const viewNode = ve.ce.nodeFactory.createFromModel( modelNode );
		viewNode.modified = true;
		viewNode.update();
		domElements[ 0 ].appendChild( viewNode.$reflist[ 0 ] );
		// Destroy the view node so it doesn't try to update the DOM node later
		// (e.g. updateDebounced)
		viewNode.destroy();
	} else if (
		dataElement.originalDomElementsHash !== undefined &&
		// don't get originalDamElements when there are changes, needed to update synthetic refs
		!updatedMw
	) {
		// If there's more than 1 element, preserve entire array, not just first element
		domElements = ve.copyDomElements(
			converter.getStore().value( dataElement.originalDomElementsHash ), doc
		);
	} else {
		domElements[ 0 ].appendChild(
			ve.dm.MWReferencesListNode.static.listToDomElement(
				dataElement.attributes.refGroup || '',
				doc,
				converter
			)
		);
	}

	domElements[ 0 ].setAttribute( 'typeof', 'mw:Extension/references' );
	domElements[ 0 ].setAttribute(
		'data-mw',
		// If mwData and originalMw are the same, use originalMw to prevent reserialization.
		// Reserialization has the potential to reorder keys and so change the DOM unnecessarily
		!updatedMw ? dataElement.attributes.originalMw : updatedMw
	);

	return domElements;
};

/***
 * Prepare mwData for conversion to DOM and check for changes
 *
 * @static
 * @param {Object} data
 * @param {Document} doc
 * @param {ve.dm.DomFromModelConverter} converter
 * @return {string|false} updated mwData as string or false if nothing changed
 * */
ve.dm.MWReferencesListNode.static.updatedMwForDom = function ( data, doc, converter ) {
	const attributes = data[ 0 ].attributes;
	const originalMw = attributes.originalMw;
	const originalMwData = originalMw && JSON.parse( originalMw );

	const mwData = attributes.mw ? ve.copy( attributes.mw ) : {};
	mwData.name = 'references';

	if ( attributes.refGroup ) {
		ve.setProp( mwData, 'attrs', 'group', attributes.refGroup );
	} else if ( mwData.attrs ) {
		delete mwData.attrs.group;
	}

	const originalResponsiveAttr = ve.getProp( originalMwData, 'attrs', 'responsive' );
	const isResponsiveDefault = mw.config.get( 'wgCiteResponsiveReferences' );
	if ( !(
		// The original "responsive" attribute hasn't had its meaning changed
		originalResponsiveAttr !== undefined && ( originalResponsiveAttr !== '0' ) === attributes.isResponsive
	) ) {
		if ( attributes.isResponsive !== isResponsiveDefault ) {
			ve.setProp( mwData, 'attrs', 'responsive', attributes.isResponsive ? '' : '0' );
		} else if ( mwData.attrs ) {
			delete mwData.attrs.responsive;
		}
	}

	// If this was an autogenerated reflist. We need to check whether changes
	// have been made which make that no longer true. The reflist dialog
	// handles unsetting this if changes to the properties have been made.
	// Here we want to work out if it has been moved away from the end of
	// the document.
	if (
		mwData.autoGenerated &&
		!ve.dm.MWReferencesListNode.static.isReflistLastElement( converter.documentData, data )
	) {
		delete mwData.autoGenerated;
	}

	// Add body.html to data-mw if applicable
	const contentsData = data.slice( 1, -1 );
	if ( contentsData.length > 2 ) {
		// get the current content html of the node
		const currentHtmlWrapper = doc.createElement( 'div' );
		converter.getDomSubtreeFromData( contentsData, currentHtmlWrapper );

		// get the original content html of the node
		const originalHtmlWrapper = doc.createElement( 'div' );
		originalHtmlWrapper.innerHTML = ve.getProp( mwData, 'body', 'html' ) || '';

		// Only set body.html if contentsHtml and originalHtml are actually different
		// FIXME?: Synthetic refs from main+details always seem to have different bodyHtml here
		if ( !originalHtmlWrapper.isEqualNode( currentHtmlWrapper ) ) {
			ve.setProp( mwData, 'body', 'html', currentHtmlWrapper.innerHTML );
		}
	}

	return originalMw && ve.compare( mwData, originalMwData ) ? false : JSON.stringify( mwData );
};

/***
 * Check the reflist is the last element in the DM
 *
 * @static
 * @param {Array} documentData
 * @param {Object} data
 * @return {boolean}
 * */
ve.dm.MWReferencesListNode.static.isReflistLastElement = function ( documentData, data ) {
	// TODO: it would be better to do this without needing to fish through
	// the converter's linear data. Use the DM tree instead?
	let nextIndex = documentData.indexOf( data[ data.length - 1 ] ) + 1;
	let nextElement;
	while ( ( nextElement = documentData[ nextIndex ] ) ) {
		if ( nextElement.type[ 0 ] !== '/' ) {
			break;
		}
		nextIndex++;
	}
	return !nextElement || nextElement.type === 'internalList';
};

/***
 * Create references list HTML DOM for Parsoid
 *
 * @static
 * @param {string} refGroup
 * @param {HTMLDocument} doc
 * @param {ve.dm.Converter} converter
 * @return {HTMLElement} <ol> element for the references list
 * */
ve.dm.MWReferencesListNode.static.listToDomElement = function ( refGroup, doc, converter ) {
	// Render all group refs
	const docRefs = MWDocumentReferences.static.refsForDoc(
		converter.internalList.document
	);
	const groupRefs = docRefs.getGroupRefs( refGroup );

	const $wrapper = $( '<ol>', doc );
	$wrapper.append(
		groupRefs.getTopLevelKeysInReflistOrder()
			.map( ( listKey ) => ve.dm.MWReferencesListNode.static.listItemToDomElement(
				groupRefs, listKey, doc, converter
			) )
	);

	return $wrapper[ 0 ];
};

/***
 * Create references list item HTML DOM for Parsoid
 *
 * @static
 * @param {ve.dm.MWGroupReferences} groupRefs
 * @param {string} listKey
 * @param {HTMLDocument} doc
 * @param {ve.dm.Converter} converter
 * @return {jQuery} <li> element for the references listitem
 * */
ve.dm.MWReferencesListNode.static.listItemToDomElement = function (
	groupRefs,
	listKey,
	doc,
	converter
) {
	const internalItem = groupRefs.getInternalModelNode( listKey );
	const subrefs = groupRefs.getSubrefs( listKey );
	const $li = $( '<li>', doc );

	if ( internalItem && internalItem.length ) {
		// make sure to find the node holding the refListItemId
		const refListNode = groupRefs.nodeGroup.getAllReuses( listKey )
			.find( ( node ) => node.getAttribute( 'refListItemId' ) );
		const htmlWrapper = doc.createElement( 'span' );
		converter.getDomSubtreeFromData(
			internalItem.getDocument().getFullData( internalItem.getRange(), 'roundTrip' ),
			htmlWrapper
		);
		$li.append(
			$( htmlWrapper )
				.attr( 'typeof', 'mw:Extension/ref' )
				.attr( 'id', refListNode && refListNode.getAttribute( 'refListItemId' ) )
		);
	} else {
		// TODO: What to do here?
		$li.append(
			$( '<span>', doc )
		).addClass( 've-ce-mwReferencesListNode-missingRef' );
	}

	if ( subrefs.length ) {
		$li.append(
			$( '<ol>', doc ).append(
				subrefs.map( ( subNode ) => ve.dm.MWReferencesListNode.static.listItemToDomElement(
					groupRefs, subNode.getAttribute( 'listKey' ), doc, converter
				) )
			)
		);
	}

	return $li;
};

ve.dm.MWReferencesListNode.static.describeChange = function ( key, change ) {
	if ( key === 'refGroup' ) {
		if ( !change.from ) {
			return ve.htmlMsg( 'cite-ve-changedesc-reflist-group-to', this.wrapText( 'ins', change.to ) );
		} else if ( !change.to ) {
			return ve.htmlMsg( 'cite-ve-changedesc-reflist-group-from', this.wrapText( 'del', change.from ) );
		} else {
			return ve.htmlMsg(
				'cite-ve-changedesc-reflist-group-both',
				this.wrapText( 'del', change.from ),
				this.wrapText( 'ins', change.to )
			);
		}
	}

	if ( key === 'isResponsive' ) {
		if ( change.from ) {
			return ve.msg( 'cite-ve-changedesc-reflist-responsive-unset' );
		}
		return ve.msg( 'cite-ve-changedesc-reflist-responsive-set' );
	}

	if ( key === 'originalMw' ) {
		return null;
	}

	return null;
};

ve.dm.MWReferencesListNode.static.getHashObject = function ( dataElement ) {
	const attributes = dataElement.attributes;
	return {
		type: dataElement.type,
		attributes: {
			refGroup: attributes.refGroup,
			listGroup: attributes.listGroup,
			isResponsive: attributes.isResponsive,
			templateGenerated: attributes.templateGenerated
		}
	};
};

module.exports = ve.dm.MWReferencesListNode;
