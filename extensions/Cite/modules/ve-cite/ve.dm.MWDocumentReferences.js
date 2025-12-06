'use strict';

/*!
 * @copyright 2024 VisualEditor Team's Cite sub-team and others; see AUTHORS.txt
 * @license MIT
 */

const MWGroupReferences = require( './ve.dm.MWGroupReferences.js' );

/**
 * A facade providing a simplified and safe interface to Cite `ref` and
 * `references` tags in a document.
 *
 * @constructor
 * @mixes OO.EventEmitter
 * @param {ve.dm.Document} doc The document that reference tags will be embedded in.
 */
ve.dm.MWDocumentReferences = function VeDmMWDocumentReferences( doc ) {
	// Mixin constructors
	OO.EventEmitter.call( this );

	// Properties
	this.doc = doc;
	/**
	 * Holds the information calculated for each group.
	 *
	 * @member {Object.<string, MWGroupReferences>}
	 */
	this.cachedByGroup = {};

	doc.getInternalList().connect( this, { update: 'updateGroups' } );
	this.updateGroups( this.getAllGroupNames() );
};

/* Inheritance */

OO.mixinClass( ve.dm.MWDocumentReferences, OO.EventEmitter );

/* Methods */

/**
 * Singleton MWDocumentReferences for a document.
 *
 * @param {ve.dm.Document} doc Source document associated with the
 * singleton. May be a fragment in which case we only look at refs included in
 * the fragment.
 *
 * @return {ve.dm.MWDocumentReferences} Singleton docRefs
 */
ve.dm.MWDocumentReferences.static.refsForDoc = function ( doc ) {
	let docRefs;
	// Only use cache if we're working with the full document.
	if ( !doc.getOriginalDocument() ) {
		docRefs = doc.extCiteDocumentReferences;
	}
	if ( docRefs === undefined ) {
		docRefs = new ve.dm.MWDocumentReferences( doc );
	}
	if ( !doc.getOriginalDocument() ) {
		doc.extCiteDocumentReferences = docRefs;
	}
	return docRefs;
};

/**
 * @private
 * @param {string[]} groupsChanged A list of group names which have changed in
 *  this transaction
 */
ve.dm.MWDocumentReferences.prototype.updateGroups = function ( groupsChanged ) {
	groupsChanged.forEach( ( groupName ) => {
		const nodeGroup = this.doc.getInternalList().getNodeGroup( groupName );
		this.cachedByGroup[ groupName ] = MWGroupReferences.static.makeGroupRefs( nodeGroup );
	} );
};

/**
 * @param {string} groupName with or without prefix
 * @return {MWGroupReferences}
 */
ve.dm.MWDocumentReferences.prototype.getGroupRefs = function ( groupName ) {
	return this.cachedByGroup[ groupName.startsWith( 'mwReference/' ) ? groupName : 'mwReference/' + groupName ] ||
		new MWGroupReferences();
};

/**
 * @return {string[]}
 */
ve.dm.MWDocumentReferences.prototype.getAllGroupNames = function () {
	return Object.keys( this.doc.getInternalList().getNodeGroups() );
};

/**
 * @return {boolean}
 */
ve.dm.MWDocumentReferences.prototype.hasRefs = function () {
	return Object.values( this.doc.getInternalList().getNodeGroups() )
		.some( ( group ) => !group.isEmpty() );
};

/**
 * Return a formatted number, in the content script, with no separators.
 *
 * Partial clone of mw.language.convertNumber .
 *
 * @param {number} num
 * @return {string}
 */
ve.dm.MWDocumentReferences.static.contentLangDigits = function ( num ) {
	const contentLang = mw.config.get( 'wgContentLanguage' );
	const digitLookup = mw.config.get( 'wgTranslateNumerals' ) &&
		mw.language.getData( contentLang, 'digitTransformTable' );
	const numString = String( num );
	if ( !digitLookup ) {
		return numString;
	}
	return numString.split( '' ).map( ( numChar ) => digitLookup[ numChar ] ).join( '' );
};

/**
 * @deprecated Should be refactored to store formatted index numbers as a simple
 *  property on each CE ref node after document transaction.
 * @param {string} groupName Ref group without prefix
 * @param {string} listKey Ref key with prefix
 * @return {string} Rendered index number string which can be used as a footnote
 *  marker or reflist item number.
 */
ve.dm.MWDocumentReferences.prototype.getIndexLabel = function ( groupName, listKey ) {
	return this.getGroupRefs( groupName ).getIndexLabel( listKey );
};

module.exports = ve.dm.MWDocumentReferences;
