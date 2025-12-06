/*!
 * VisualEditor DataModel MWInternalLinkAnnotation class.
 *
 * @copyright See AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * DataModel MediaWiki internal link annotation.
 *
 * Example HTML sources:
 *
 *     <a rel="mw:WikiLink">
 *
 * @class
 * @extends ve.dm.LinkAnnotation
 * @constructor
 * @param {Object} element
 */
ve.dm.MWInternalLinkAnnotation = function VeDmMWInternalLinkAnnotation() {
	// Parent constructor
	ve.dm.MWInternalLinkAnnotation.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( ve.dm.MWInternalLinkAnnotation, ve.dm.LinkAnnotation );

/* Static Properties */

ve.dm.MWInternalLinkAnnotation.static.name = 'link/mwInternal';

ve.dm.MWInternalLinkAnnotation.static.matchRdfaTypes = [ 'mw:WikiLink', 'mw:MediaLink' ];

ve.dm.MWInternalLinkAnnotation.static.allowedRdfaTypes = [
	// mw:MediaLink to non-existent files come with typeof="mw:Error"
	'mw:Error',
	// Present on red links
	'mw:LocalizedAttrs',
	// Present on any link where the target is template- or extension-generated
	'mw:ExpandedAttrs',
	// (particularly generated from the Translate extension markup)
	/^mw:Annotation\//
];

ve.dm.MWInternalLinkAnnotation.static.toDataElement = function ( domElements, converter ) {
	let hasGeneratedHref = false;
	const types = domElements[ 0 ].getAttribute( 'typeof' ) || '';
	if ( types.split( /\s+/ ).includes( 'mw:ExpandedAttrs' ) ) {
		const dataMw = JSON.parse( domElements[ 0 ].getAttribute( 'data-mw' ) );
		for ( const [ attrName /* , attrValue */ ] of dataMw.attribs ) {
			// Check that mw:ExpandedAttrs only contains 'href' - otherwise alienate
			if ( attrName.txt === 'href' ) {
				hasGeneratedHref = true;
			} else {
				return null;
			}
		}
	}

	const resource = domElements[ 0 ].getAttribute( 'resource' );

	let targetData;
	if ( resource ) {
		targetData = mw.libs.ve.parseParsoidResourceName( resource );
	} else {
		targetData = mw.libs.ve.getTargetDataFromHref(
			domElements[ 0 ].getAttribute( 'href' ),
			converter.getTargetHtmlDocument()
		);

		if ( !targetData.isInternal ) {
			if ( hasGeneratedHref ) {
				return null;
			}
			return ve.dm.MWExternalLinkAnnotation.static.toDataElement( domElements, converter );
		}
	}

	const data = {
		type: this.name,
		attributes: {
			title: targetData.title,
			normalizedTitle: this.normalizeTitle( targetData.title ),
			lookupTitle: this.getLookupTitle( targetData.title )
		}
	};
	if ( hasGeneratedHref ) {
		data.attributes.hasGeneratedHref = true;
	}
	return data;
};

ve.dm.MWInternalLinkAnnotation.prototype.isEditable = function () {
	return !this.getAttribute( 'hasGeneratedHref' );
};

/**
 * Build element from a given mw.Title and raw title
 *
 * @param {mw.Title} title The title to link to.
 * @return {Object} The element.
 */
ve.dm.MWInternalLinkAnnotation.static.dataElementFromTitle = function ( title ) {
	let target = title.toText();

	if ( title.getFragment() ) {
		target += '#' + title.getFragment();
	}

	const element = {
		type: this.name,
		attributes: {
			title: target,
			normalizedTitle: this.normalizeTitle( title ),
			lookupTitle: this.getLookupTitle( title )
		}
	};

	return element;
};

/**
 * Build a ve.dm.MWInternalLinkAnnotation from a given mw.Title.
 *
 * @param {mw.Title} title The title to link to.
 * @return {ve.dm.MWInternalLinkAnnotation} The annotation.
 */
ve.dm.MWInternalLinkAnnotation.static.newFromTitle = function ( title ) {
	const element = this.dataElementFromTitle( title );

	return new ve.dm.MWInternalLinkAnnotation( element );
};

ve.dm.MWInternalLinkAnnotation.static.toDomElements = function () {
	const parentResult = ve.dm.LinkAnnotation.static.toDomElements.apply( this, arguments );
	// we just created that link so the 'rel' attribute should be safe
	parentResult[ 0 ].setAttribute( 'rel', 'mw:WikiLink' );
	return parentResult;
};

ve.dm.MWInternalLinkAnnotation.static.getHref = function ( dataElement ) {
	let title = dataElement.attributes.title;

	if ( title.startsWith( '#' ) ) {
		// Special case: For a newly created link to a #fragment with
		// no explicit title use the current title as prefix (T218581)
		// TODO: Pass a 'doc' param to getPageName
		title = ve.init.target.getPageName() + title;
	}

	return mw.libs.ve.encodeParsoidResourceName( title );
};

/**
 * Normalize title for comparison purposes.
 * E.g. capitalisation and underscores.
 *
 * @param {string|mw.Title} original Original title
 * @return {string} Normalized title, or the original string if it is invalid
 */
ve.dm.MWInternalLinkAnnotation.static.normalizeTitle = function ( original ) {
	const title = original instanceof mw.Title ? original : mw.Title.newFromText( original );
	if ( !title ) {
		return original;
	}
	return title.getPrefixedText() + ( title.getFragment() !== null ? '#' + title.getFragment() : '' );
};

/**
 * Normalize title for lookup (search suggestion, existence) purposes.
 *
 * @param {string|mw.Title} original Original title
 * @return {string} Normalized title, or the original string if it is invalid
 */
ve.dm.MWInternalLinkAnnotation.static.getLookupTitle = function ( original ) {
	const title = original instanceof mw.Title ? original : mw.Title.newFromText( original );
	if ( !title ) {
		return original;
	}
	return title.getPrefixedText();
};

/**
 * Get the fragment for a title
 *
 * @static
 * @param {string|mw.Title} original Original title
 * @return {string|null} Fragment for the title, or null if it was invalid or missing
 */
ve.dm.MWInternalLinkAnnotation.static.getFragment = function ( original ) {
	const title = original instanceof mw.Title ? original : mw.Title.newFromText( original );
	if ( !title ) {
		return null;
	}
	return title.getFragment();
};

ve.dm.MWInternalLinkAnnotation.static.describeChange = function ( key, change ) {
	if ( key === 'title' ) {
		return ve.htmlMsg( 'visualeditor-changedesc-link-href', this.wrapText( 'del', change.from ), this.wrapText( 'ins', change.to ) );
	}
	return null;
};

/* Methods */

/**
 * @inheritdoc
 */
ve.dm.MWInternalLinkAnnotation.prototype.getComparableObject = function () {
	return {
		type: this.getType(),
		normalizedTitle: this.getAttribute( 'normalizedTitle' )
	};
};

/**
 * @inheritdoc
 */
ve.dm.MWInternalLinkAnnotation.prototype.getComparableHtmlAttributes = function () {
	// Assume that wikitext never adds meaningful html attributes for comparison purposes,
	// although ideally this should be decided by Parsoid (Bug T95028).
	return {};
};

/**
 * @inheritdoc
 */
ve.dm.MWInternalLinkAnnotation.prototype.getDisplayTitle = function () {
	return this.getAttribute( 'normalizedTitle' );
};

/**
 * Convenience wrapper for .getFragment() on the current element.
 *
 * @see #static-getFragment
 * @return {string} Fragment for the title, or an empty string if it was invalid
 */
ve.dm.MWInternalLinkAnnotation.prototype.getFragment = function () {
	return this.constructor.static.getFragment( this.getAttribute( 'normalizedTitle' ) );
};

/* Registration */

ve.dm.modelRegistry.register( ve.dm.MWInternalLinkAnnotation );
