/**
 * BaseEditCheck
 *
 * Abstract base class for edit checks. Provides common configuration, tagging,
 * and utility methods for subclasses implementing specific edit check logic.
 *
 * Subclasses should implement event handler methods such as onBeforeSave and onDocumentChange.
 *
 * @class
 * @abstract
 * @param {mw.editcheck.Controller} controller Edit check controller
 * @param {Object} [config] Configuration options
 * @param {boolean} [includeSuggestions=false]
 */
mw.editcheck.BaseEditCheck = function MWBaseEditCheck( controller, config, includeSuggestions ) {
	this.controller = controller;
	this.config = ve.extendObject( {}, this.constructor.static.defaultConfig, config );
	this.includeSuggestions = includeSuggestions;
};

/* Inheritance */

OO.initClass( mw.editcheck.BaseEditCheck );

/* Static properties */

mw.editcheck.BaseEditCheck.static.onlyCoveredNodes = false;

mw.editcheck.BaseEditCheck.static.choices = [
	{
		action: 'accept',
		label: ve.msg( 'editcheck-dialog-action-yes' ),
		icon: 'check'
	},
	{
		action: 'reject',
		label: ve.msg( 'editcheck-dialog-action-no' ),
		icon: 'close'
	}
];

mw.editcheck.BaseEditCheck.static.defaultConfig = {
	enabled: true,
	account: false, // 'loggedin', 'loggedout', anything non-truthy means allow either
	maximumEditcount: 100,
	ignoreSections: [],
	ignoreLeadSection: false
};

mw.editcheck.BaseEditCheck.static.title = ve.msg( 'editcheck-review-title' );

mw.editcheck.BaseEditCheck.static.description = ve.msg( 'editcheck-dialog-addref-description' );

mw.editcheck.BaseEditCheck.static.canBeStale = false;

/**
 * Takes focus from the surface to show the check as soon as it is detected (on mobile)
 *
 * On desktop the check cards are always visible so
 * this config does nothing.
 *
 * @type {boolean}
 */
mw.editcheck.BaseEditCheck.static.takesFocus = false;

/* Static methods */

/**
 * Find out if any conditions in the provided config are met
 *
 * @param {Object} [config] Configuration options
 * @return {boolean} Whether the config matches
 */
mw.editcheck.BaseEditCheck.static.doesConfigMatch = function ( config ) {
	if ( !config.enabled ) {
		return false;
	}
	// account status:
	// loggedin, loggedout, or any-other-value meaning 'both'
	// we'll count temporary users as "logged out" by using isNamed here
	if ( config.account === 'loggedout' && mw.user.isNamed() ) {
		return false;
	}
	if ( config.account === 'loggedin' && !mw.user.isNamed() ) {
		return false;
	}
	// some checks are only shown for newer users
	if ( config.maximumEditcount && mw.config.get( 'wgUserEditCount', 0 ) > config.maximumEditcount ) {
		return false;
	}
	return true;
};

/* Methods */

/**
 * Get the name of the check type
 *
 * @return {string} Check type name
 */
mw.editcheck.BaseEditCheck.prototype.getName = function () {
	return this.constructor.static.name;
};

/**
 * Check if the edit check can be stale
 *
 * @return {boolean}
 */
mw.editcheck.BaseEditCheck.prototype.canBeStale = function () {
	return this.constructor.static.canBeStale;
};

/**
 * Get actions to show before save
 *
 * @abstract
 * @param {ve.dm.Surface} surfaceModel
 * @return {mw.editcheck.EditCheckAction[]|void}
 */
mw.editcheck.BaseEditCheck.prototype.onBeforeSave = null;

/**
 * Get actions to show when document changed
 *
 * @abstract
 * @param {ve.dm.Surface} surfaceModel
 * @return {mw.editcheck.EditCheckAction[]|void}
 */
mw.editcheck.BaseEditCheck.prototype.onDocumentChange = null;

/**
 * Get actions to show when the focused branch node changed
 *
 * @abstract
 * @param {ve.dm.Surface} surfaceModel
 * @return {mw.editcheck.EditCheckAction[]|void}
 */
mw.editcheck.BaseEditCheck.prototype.onBranchNodeChange = null;

/**
 * User performs an action on an check
 *
 * @abstract
 * @param {string} choice `action` key from static.choices
 * @param {mw.editcheck.EditCheckAction} action
 * @param {ve.ui.Surface} surface
 * @return {jQuery.Promise} Promise which resolves when action is complete
 */
mw.editcheck.BaseEditCheck.prototype.act = null;

/**
 * Get the title of the check
 *
 * @param {mw.editcheck.EditCheckAction} action
 * @return {jQuery|string|Function|OO.ui.HtmlSnippet}
 */
mw.editcheck.BaseEditCheck.prototype.getTitle = function () {
	return this.constructor.static.title;
};

/**
 * Get the prompt for the check's actions, if any
 *
 * @param {mw.editcheck.EditCheckAction} action
 * @return {jQuery|string|Function|OO.ui.HtmlSnippet|undefined}
 */
mw.editcheck.BaseEditCheck.prototype.getPrompt = function () {
	return this.constructor.static.prompt || undefined;
};

/**
 * Get the footer of the check, if any
 *
 * @param {mw.editcheck.EditCheckAction} action
 * @return {jQuery|string|Function|OO.ui.HtmlSnippet|undefined}
 */
mw.editcheck.BaseEditCheck.prototype.getFooter = function () {
	return this.constructor.static.footer || undefined;
};

/**
 * @param {mw.editcheck.EditCheckAction} action
 * @return {string}
 */
mw.editcheck.BaseEditCheck.prototype.getDescription = function () {
	return this.constructor.static.description;
};

/**
 * Find out whether the check should be applied
 *
 * This is a general check for its applicability to the viewer / page, rather
 * than a specific check based on the current edit. It's used to filter out
 * checks before any maybe-expensive content analysis happens.
 *
 * @return {boolean} Whether the check should be shown
 */
mw.editcheck.BaseEditCheck.prototype.canBeShown = function () {
	// all checks are only in the main namespace for now
	if ( mw.config.get( 'wgNamespaceNumber' ) !== mw.config.get( 'wgNamespaceIds' )[ '' ] ) {
		return false;
	}
	// some checks are configured to only be for logged in / out users
	if ( mw.editcheck.forceEnable ) {
		return true;
	}
	if ( !this.constructor.static.doesConfigMatch( this.config ) ) {
		return false;
	}
	return true;
};

/**
 * Get content ranges where at least the minimum about of text has been changed
 *
 * @param {ve.dm.Document} documentModel
 * @return {ve.Range[]}
 */
mw.editcheck.BaseEditCheck.prototype.getModifiedContentRanges = function ( documentModel ) {
	return this.getModifiedRanges( documentModel, this.constructor.static.onlyCoveredNodes, true );
};

/**
 * Get content ranges where at least the minimum about of text has been added
 *
 * @param {ve.dm.Document} documentModel
 * @return {ve.Range[]}
 */
mw.editcheck.BaseEditCheck.prototype.getAddedContentRanges = function ( documentModel ) {
	return this.getAddedRanges( documentModel, this.constructor.static.onlyCoveredNodes, true );
};

/**
 * Get ContentBranchNodes where some text has been changed
 *
 * @param {ve.dm.Document} documentModel
 * @return {ve.dm.ContentBranchNode[]}
 */
mw.editcheck.BaseEditCheck.prototype.getModifiedContentBranchNodes = function ( documentModel ) {
	const modified = new Set();
	this.getModifiedRanges( documentModel, false, true ).forEach( ( range ) => {
		if ( !range.isCollapsed() ) {
			modified.add( documentModel.getBranchNodeFromOffset( range.start ) );
		}
	} );
	return Array.from( modified );
};

/**
 * Find nodes that were added during the edit session
 *
 * @param {ve.dm.Document} documentModel
 * @param {string} [type] Type of nodes to find, or all nodes if false
 * @return {ve.dm.Node[]}
 */
mw.editcheck.BaseEditCheck.prototype.getAddedNodes = function ( documentModel, type ) {
	const matchedNodes = [];
	if ( this.includeSuggestions ) {
		if ( type ) {
			return documentModel.getNodesByType( type, true );
		}
		return documentModel.selectNodes( documentModel.getDocumentRange(), 'covered' )
			.map( ( node ) => node.node );
	}
	this.getModifiedRanges( documentModel ).forEach( ( range ) => {
		const nodes = documentModel.selectNodes( range, 'covered' );
		nodes.forEach( ( node ) => {
			if ( !type || node.node.getType() === type ) {
				matchedNodes.push( node.node );
			}
		} );
	} );
	return matchedNodes;
};

/**
 * Get content ranges which have been inserted
 *
 * @param {ve.dm.Document} documentModel
 * @param {boolean} coveredNodesOnly Only include ranges which cover the whole of their node
 * @param {boolean} onlyContentRanges Only return ranges which are content branch node interiors
 * @return {ve.Range[]}
 */
mw.editcheck.BaseEditCheck.prototype.getAddedRanges = function ( documentModel, coveredNodesOnly, onlyContentRanges ) {
	return this.getModifiedRanges( documentModel, coveredNodesOnly, onlyContentRanges, true );
};

/**
 * Get content ranges which have been modified
 *
 * @param {ve.dm.Document} documentModel
 * @param {boolean} coveredNodesOnly Only include ranges which cover the whole of their node
 * @param {boolean} onlyContentRanges Only return ranges which are content branch node interiors
 * @param {boolean} onlyPureInsertions Only return ranges which didn't replace any other content
 * @return {ve.Range[]}
 */
mw.editcheck.BaseEditCheck.prototype.getModifiedRanges = function ( documentModel, coveredNodesOnly, onlyContentRanges, onlyPureInsertions ) {
	if ( !documentModel.completeHistory.getLength() ) {
		return [];
	}
	let candidates = [];
	if ( this.includeSuggestions ) {
		candidates = documentModel.getDocumentNode().getChildren()
			.filter( ( branchNode ) => !( branchNode instanceof ve.dm.InternalListNode ) )
			.map( ( branchNode ) => branchNode.getRange() );
	} else {
		let operations;
		try {
			operations = documentModel.completeHistory.squash().transactions[ 0 ].operations;
		} catch ( err ) {
			// TransactionSquasher can sometimes throw errors; until T333710 is
			// fixed just count this as not needing a reference.
			mw.errorLogger.logError( err, 'error.visualeditor' );
			return [];
		}

		let offset = 0;
		const endOffset = documentModel.getDocumentRange().end;
		operations.every( ( op ) => {
			if ( op.type === 'retain' ) {
				offset += op.length;
			} else if ( op.type === 'replace' ) {
				const insertedRange = new ve.Range( offset, offset + op.insert.length );
				offset += op.insert.length;
				// 1. Only trigger if the check is a pure insertion with no
				// adjacent content removed (T340088), or if we're allowing
				// non-pure insertions. Either way, a pure removal won't be included.
				if ( ( !onlyPureInsertions && op.insert.length > 0 ) || op.remove.length === 0 ) {
					candidates.push( insertedRange );
				}
			}
			// Reached the end of the doc / start of internal list, stop searching
			return offset < endOffset;
		} );
	}
	const ranges = [];
	candidates.forEach( ( range ) => {
		if ( onlyContentRanges ) {
			ve.batchPush(
				ranges,
				// 2. Only fully inserted paragraphs (ranges that cover the whole node) (T345121)
				this.getContentRangesFromRange( documentModel, range, coveredNodesOnly )
			);
		} else {
			ranges.push( range );
		}
	} );
	return ranges.filter( ( range ) => this.isRangeValid( range, documentModel ) );
};

/**
 * Return the content ranges (content branch node interiors) contained within a range
 *
 * For a content branch node entirely contained within the range, its entire interior
 * range will be included. For a content branch node overlapping with the range boundary,
 * only the covered part of its interior range will be included.
 *
 * @param {ve.dm.Document} documentModel The documentModel to search
 * @param {ve.Range} range The range to include
 * @param {boolean} covers Only include ranges which cover the whole of their node
 * @return {ve.Range[]} The contained content ranges (content branch node interiors)
 */
mw.editcheck.BaseEditCheck.prototype.getContentRangesFromRange = function ( documentModel, range, covers ) {
	const ranges = [];
	documentModel.selectNodes( range, 'branches' ).forEach( ( spec ) => {
		if (
			spec.node.canContainContent() && (
				!covers || (
					!spec.range || // an empty range means the node is covered
					spec.range.equalsSelection( spec.nodeRange )
				)
			)
		) {
			ranges.push( spec.range || spec.nodeRange );
		}
	} );
	return ranges;
};

/**
 * Test whether the range is valid for the check to apply
 *
 * @param {ve.Range} range
 * @param {ve.dm.Document} documentModel
 * @return {boolean}
 */
mw.editcheck.BaseEditCheck.prototype.isRangeValid = function ( range, documentModel ) {
	return this.isRangeInValidSection( range, documentModel );
};

/**
 * Check if a modified range is a section we don't ignore (config.ignoreSections)
 *
 * @param {ve.Range} range
 * @param {ve.dm.Document} documentModel
 * @return {boolean} Whether the range is in a section we don't ignore
 */
mw.editcheck.BaseEditCheck.prototype.isRangeInValidSection = function ( range, documentModel ) {
	const ignoreSections = this.config.ignoreSections || [];
	if ( ignoreSections.length === 0 && !this.config.ignoreLeadSection ) {
		// Nothing is forbidden, so everything is permitted
		return true;
	}
	const isHeading = ( nodeType ) => nodeType === 'mwHeading';
	// Note: we set a limit of 1 here because otherwise this will turn around
	// to keep looking when it hits the document boundary:
	const heading = documentModel.getNearestNodeMatching( isHeading, range.start, -1, 1 );
	if ( !heading ) {
		// There's no preceding heading, so work out if we count as being in a
		// lead section. It's only a lead section if there's more headings
		// later in the document, otherwise it's just a stub article.
		return !(
			this.config.ignoreLeadSection &&
			!!documentModel.getNearestNodeMatching( isHeading, range.start, 1 )
		);
	}
	if ( ignoreSections.length === 0 ) {
		// There's nothing left to deny
		return true;
	}
	const compare = new Intl.Collator( documentModel.getLang(), { sensitivity: 'accent' } ).compare;
	const headingText = documentModel.data.getText( false, heading.getRange() );
	// If the heading text matches any of ignoreSections, return false.
	return !ignoreSections.some( ( section ) => compare( headingText, section ) === 0 );
};

/**
 * Dismiss a check action
 *
 * @param {mw.editCheck.EditCheckAction} action
 */
mw.editcheck.BaseEditCheck.prototype.dismiss = function ( action ) {
	this.tag( 'dismissed', action );
};

/**
 * Tag a check action
 *
 * TODO: This is asymmetrical. Do we want to split this into two functions, or
 * unify isTaggedRange/isTaggedId into one function?
 *
 * @param {string} tag
 * @param {mw.editCheck.EditCheckAction} action
 */
mw.editcheck.BaseEditCheck.prototype.tag = function ( tag, action ) {
	const name = action.getTagName();
	if ( action.id ) {
		const taggedIds = this.controller.taggedIds;
		taggedIds[ name ] = taggedIds[ name ] || {};
		taggedIds[ name ][ tag ] = taggedIds[ name ][ tag ] || new Set();
		taggedIds[ name ][ tag ].add( action.id );
	} else {
		const taggedFragments = this.controller.taggedFragments;
		taggedFragments[ name ] = taggedFragments[ name ] || {};
		taggedFragments[ name ][ tag ] = taggedFragments[ name ][ tag ] || [];
		taggedFragments[ name ][ tag ].push(
			// Exclude insertions so we don't accidentally block unrelated changes:
			...action.fragments.map( ( fragment ) => fragment.clone().setExcludeInsertions( true ) )
		);
	}
};

/**
 * Untag a check action
 *
 * TODO: This is asymmetrical. Do we want to split this into two functions, or
 * unify isTaggedRange/isTaggedId into one function?
 *
 * @param {string} tag
 * @param {mw.editCheck.EditCheckAction} action
 * @return {boolean} Whether anything was untagged
 */
mw.editcheck.BaseEditCheck.prototype.untag = function ( tag, action ) {
	const name = action.getTagName();
	if ( action.id ) {
		const taggedIds = this.controller.taggedIds;
		if ( taggedIds[ name ] && taggedIds[ name ][ tag ] ) {
			taggedIds[ name ][ tag ].delete( action.id );
			return true;
		}
	} else {
		const taggedFragments = this.controller.taggedFragments;
		if ( taggedFragments[ name ] && taggedFragments[ name ][ tag ] ) {
			action.fragments.forEach( ( fragment ) => {
				const selection = fragment.getSelection();
				const index = taggedFragments[ name ][ tag ].findIndex(
					( taggedFragment ) => taggedFragment.getSelection().equals( selection )
				);
				if ( index !== -1 ) {
					taggedFragments[ name ][ tag ].splice( index, 1 );
					return true;
				}
			} );
		}
	}
	return false;
};

/**
 * Check if this type of check has been dismissed covering a specific range
 *
 * @param {ve.Range} range
 * @param {string} name of the tag
 * @return {boolean}
 */
mw.editcheck.BaseEditCheck.prototype.isDismissedRange = function ( range, name ) {
	if ( !name ) {
		name = this.constructor.static.name;
	}
	return this.isTaggedRange( 'dismissed', range, name );
};

/**
 * Check if this type of check has a given tag
 *
 * @param {string} tag
 * @param {ve.Range} range
 * @param {string} name of the tag
 * @return {boolean}
 */
mw.editcheck.BaseEditCheck.prototype.isTaggedRange = function ( tag, range, name ) {
	const tags = this.controller.taggedFragments[ name ];
	if ( tags === undefined ) {
		return false;
	}

	const fragments = tags[ tag ];
	return !!fragments && fragments.some(
		( fragment ) => fragment.getSelection().getCoveringRange().containsRange( range )
	);
};

/**
 * Check if an action with a given ID has been dismissed
 *
 * @param {string} id
 * @return {boolean}
 */
mw.editcheck.BaseEditCheck.prototype.isDismissedId = function ( id ) {
	return this.isTaggedId( 'dismissed', id );
};

/**
 * Check if an action with a given ID has a given tag
 *
 * @param {string} tag
 * @param {string} id
 * @return {boolean}
 */
mw.editcheck.BaseEditCheck.prototype.isTaggedId = function ( tag, id ) {
	const tags = this.controller.taggedIds[ this.constructor.static.name ];
	if ( tags === undefined ) {
		return false;
	}
	const ids = tags[ tag ];
	return !!ids && ids.has( id );
};
