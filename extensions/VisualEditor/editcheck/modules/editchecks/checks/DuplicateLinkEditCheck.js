/**
 * Edit check to detect duplicate links within a section or paragraph
 *
 * @class
 * @extends mw.editcheck.LinkEditCheck
 *
 * @constructor
 * @param {mw.editcheck.Controller} controller
 * @param {Object} [config]
 * @param {string} [config.scope='paragraph'] Scope to check for duplicates: 'paragraph' or 'section'
 * @param {boolean} [includeSuggestions=false]
 */
mw.editcheck.DuplicateLinkEditCheck = function MWDuplicateLinkEditCheck() {
	// Parent constructor
	mw.editcheck.DuplicateLinkEditCheck.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( mw.editcheck.DuplicateLinkEditCheck, mw.editcheck.LinkEditCheck );

/* Static properties */

mw.editcheck.DuplicateLinkEditCheck.static.defaultConfig = ve.extendObject( {}, mw.editcheck.DuplicateLinkEditCheck.super.static.defaultConfig, {
	showAsCheck: false,
	scope: 'paragraph'
} );

mw.editcheck.DuplicateLinkEditCheck.static.name = 'duplicateLink';

mw.editcheck.DuplicateLinkEditCheck.static.title = OO.ui.deferMsg( 'editcheck-duplicate-link-title' );

mw.editcheck.DuplicateLinkEditCheck.static.description = ve.deferJQueryMsg( 'editcheck-duplicate-link-description' );

mw.editcheck.DuplicateLinkEditCheck.static.choices = [
	{
		action: 'remove',
		label: OO.ui.deferMsg( 'editcheck-action-remove-link' )
	},
	{
		action: 'dismiss',
		label: OO.ui.deferMsg( 'ooui-dialog-process-dismiss' )
	}
];

mw.editcheck.DuplicateLinkEditCheck.static.linkClasses = [ ve.dm.MWInternalLinkAnnotation ];

/* Methods */

/*
 * Break down the document into sections
 */
function getSectionRanges( documentModel ) {
	const headingRanges = documentModel.getNodesByType( 'mwHeading', true )
		.filter( ( node ) => node.getAttribute( 'level' ) === 2 )
		.map( ( node ) => node.getOuterRange() );

	const sections = [];
	let start = 0;
	for ( const headingRange of headingRanges ) {
		sections.push( new ve.Range( start, headingRange.start ) );
		start = headingRange.end;
	}
	sections.push( new ve.Range( start, documentModel.getDocumentRange().end ) );
	return sections;
}

function orderedCollectBy( iterable, keyFunction ) {
	const result = new Map();
	for ( const entry of iterable ) {
		const key = keyFunction( entry );
		if ( key === undefined || key === null ) {
			continue;
		}
		if ( result.has( key ) ) {
			result.get( key ).push( entry );
		} else {
			result.set( key, [ entry ] );
		}
	}
	return result;
}

mw.editcheck.DuplicateLinkEditCheck.prototype.onDocumentChange = function ( surfaceModel ) {
	const normalizedTitleKey = 'normalizedTitle';

	const documentModel = surfaceModel.getDocument();

	// Each section of the document that we want to detect duplicates within
	let sectionRanges;
	if ( this.config.scope === 'section' ) {
		sectionRanges = getSectionRanges( documentModel );
	} else if ( this.config.scope === 'paragraph' ) {
		sectionRanges = documentModel.getNodesByType( 'paragraph' ).map( ( node ) => node.getOuterRange() );
	}

	// Traverse the tree once to find internal links, and build a map
	const allLinks = documentModel.getDocumentNode().getAnnotationRanges().filter( ( annRange ) => annRange.annotation.name === ve.dm.MWInternalLinkAnnotation.static.name );
	const allLinksByTitle = orderedCollectBy( allLinks, ( annRef ) => annRef.annotation.getAttribute( normalizedTitleKey ) );

	// Traverse again for links we want to show a Check on. This could be a small set, or all links.
	// Filter out any links that appear only once in the document.
	// getModifiedLinkRanges handles filtering sections and dismissed actions.
	const candidateModifiedLinks = this.getModifiedLinkRanges( surfaceModel ).filter( ( annRange ) => allLinksByTitle.get( annRange.annotation.getAttribute( normalizedTitleKey ) ).length > 1 );

	// Now we have a list of modified links which, if they're root links and duplicated in the same section by another root link, are duplicates.
	let i = 0; // Index into candidate links
	const actions = [];
	for ( const sectionRange of sectionRanges ) {
		const duplicateLinksByTitle = new Map();
		for ( ; i < candidateModifiedLinks.length && candidateModifiedLinks[ i ].range.end <= sectionRange.end; i++ ) {
			const annRange = candidateModifiedLinks[ i ];
			const title = annRange.annotation.getAttribute( normalizedTitleKey );
			if ( !duplicateLinksByTitle.has( title ) ) {
				const sectionLinksForTitle = allLinksByTitle.get( title ).filter( ( ar ) => sectionRange.containsRange( ar.range ) );
				if ( sectionLinksForTitle.length > 1 ) {
					// Only validate we're in a root paragraph after confirming there's a duplicate in the section
					const rootSectionLinksForTitle = sectionLinksForTitle.filter( ( ar ) => documentModel.getBranchNodeFromOffset( ar.range.start ).getParent() === documentModel.attachedRoot );
					if ( rootSectionLinksForTitle.length > 1 ) {
						duplicateLinksByTitle.set( title, rootSectionLinksForTitle );
					} else {
						duplicateLinksByTitle.set( title, [] );
					}
				} else {
					duplicateLinksByTitle.set( title, [] );
				}
			}
			const duplicateLinks = duplicateLinksByTitle.get( title );
			if ( duplicateLinks.length < 2 ) {
				continue;
			}

			const index = duplicateLinks.findIndex( ( ar ) => annRange.range.equalsSelection( ar.range ) );
			if ( index === 0 ) {
				// Don't mark the first link as a duplicate.
				continue;
			}

			// Highlight all duplicates with the one being acted on first
			const highlights = duplicateLinks.slice();
			highlights.splice( index, 1 );
			highlights.unshift( annRange );

			actions.push( this.buildActionFromLinkRange( annRange.range, surfaceModel, {
				fragments: highlights.map( ( ar ) => surfaceModel.getLinearFragment( ar.range ) )
			} ) );
		}
	}

	return actions;
};

mw.editcheck.DuplicateLinkEditCheck.prototype.act = function ( choice, action, surface ) {
	if ( choice === 'remove' ) {
		action.fragments[ 0 ].annotateContent( 'clear', ve.ce.MWInternalLinkAnnotation.static.name );
		action.select( surface, true );
		return;
	}
	// Parent method
	return mw.editcheck.DuplicateLinkEditCheck.super.prototype.act.apply( this, arguments );
};

/* Registration */

mw.editcheck.editCheckFactory.register( mw.editcheck.DuplicateLinkEditCheck );
