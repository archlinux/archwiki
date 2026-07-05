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
		label: OO.ui.deferMsg( 'editcheck-dialog-action-yes' ),
		icon: 'check'
	},
	{
		action: 'reject',
		label: OO.ui.deferMsg( 'editcheck-dialog-action-no' ),
		icon: 'close'
	}
];

mw.editcheck.BaseEditCheck.static.defaultConfig = {
	account: false, // 'loggedin', 'loggedout', anything non-truthy means allow either
	maximumEditcount: 100,
	minimumEditcount: 0,
	ignoreSections: [],
	ignoreLeadSection: false,
	includeSections: true, // any non-array means to include all; array of names means to include only those
	ignoreDisambiguationPages: false,
	ignoreQuotedContent: false,
	showAsCheck: true,
	showAsSuggestion: true
};

mw.editcheck.BaseEditCheck.static.title = null;

/**
 * Main message of the edit check
 *
 * @type {jQuery|string|Function|OO.ui.HtmlSnippet}
 */
mw.editcheck.BaseEditCheck.static.description = null;

/**
 * Footer message of the edit check
 *
 * @type {jQuery|string|Function|OO.ui.HtmlSnippet}
 */
mw.editcheck.BaseEditCheck.static.footer = null;

/**
 * Prompt message of the edit check
 *
 * @type {jQuery|string|Function|OO.ui.HtmlSnippet}
 */
mw.editcheck.BaseEditCheck.static.prompt = null;

/**
 * Success message of the edit check
 *
 * TODO: Add a default success message?
 *
 * @type {jQuery|string|Function|OO.ui.HtmlSnippet}
 */
mw.editcheck.BaseEditCheck.static.success = null;

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
 * @param {Object} config Configuration options
 * @param {ve.dm.Document} [documentModel] if attached to a known document
 * @param {boolean} [suggestion=false] Whether we are checking for suggestion mode
 * @return {boolean} Whether the config matches
 */
mw.editcheck.BaseEditCheck.static.doesConfigMatch = function ( config, documentModel = undefined, suggestion = false ) {
	const show = suggestion ? config.showAsSuggestion : config.showAsCheck;
	if ( !show && !mw.editcheck.experimental ) {
		return false;
	}

	// Skip account status checks when in suggestion mode or when forceEnable is set
	// (forceEnable should only bypass account configs, not ones that are integral to the check working as intended, such as category)
	if ( !suggestion && !mw.editcheck.forceEnable ) {
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
		// and some checks are only shown for more experienced users
		if ( config.minimumEditcount && mw.config.get( 'wgUserEditCount', 0 ) < config.minimumEditcount ) {
			return false;
		}
	}
	if ( documentModel ) {
		if ( config.inCategory || config.notInCategory ) {
			// wgCategories is populated at load time, so won't reflect
			// changes during the edit session but it does include
			// categories added by templates.
			const categories = mw.config.get( 'wgCategories' ) || [];

			const normalizeTitle = ( title ) => {
				const mwTitle = mw.Title.newFromText( title );
				return mwTitle ? mwTitle.getMainText() : title;
			};

			const inCategory = ( config.inCategory || [] ).map( normalizeTitle );
			const notInCategory = ( config.notInCategory || [] ).map( normalizeTitle );

			// Is the page in any of the specified categories?
			if ( inCategory.length && !categories.some(
				( category ) => inCategory.includes( category )
			) ) {
				return false;
			}
			if ( notInCategory.length && categories.some(
				( category ) => notInCategory.includes( category )
			) ) {
				return false;
			}
		}
		if ( config.hasTemplate || config.lacksTemplate ) {
			// By class rather than by name because we want the subclasses as well.
			const templates = documentModel.getOrInsertCachedData( () => {
				const dmTemplates = new Set();
				documentModel.getNodesByType( ve.dm.MWTransclusionNode, false ).forEach( ( node ) => {
					node.getPartsList().forEach( ( part ) => {
						if ( part.templatePage ) {
							// part.template varies depending on how it was specified, so normalize via mw.Title
							const title = mw.Title.newFromText( part.templatePage );
							if ( title ) {
								dmTemplates.add( title.getMainText() );
							}
						}
					} );
				} );
				return dmTemplates;
			}, 'editcheck-templates' );
			if ( config.hasTemplate && !config.hasTemplate.some(
				( template ) => templates.has( template )
			) ) {
				return false;
			}
			if ( config.lacksTemplate && config.lacksTemplate.some(
				( template ) => templates.has( template )
			) ) {
				return false;
			}
		}
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
 * @return {mw.editcheck.EditCheckAction[]|Promise[]|mw.editcheck.EditCheckAction|Promise} Action, or Promise which resolves with an Action or null
 */
mw.editcheck.BaseEditCheck.prototype.onBeforeSave = null;

/**
 * Get actions to show when document changed
 *
 * @abstract
 * @param {ve.dm.Surface} surfaceModel
 * @return {mw.editcheck.EditCheckAction[]|Promise[]|mw.editcheck.EditCheckAction|Promise} Action, or Promise which resolves with an Action or null
 */
mw.editcheck.BaseEditCheck.prototype.onDocumentChange = null;

/**
 * Get actions to show when the focused branch node changed
 *
 * @abstract
 * @param {ve.dm.Surface} surfaceModel
 * @return {mw.editcheck.EditCheckAction[]|Promise[]|mw.editcheck.EditCheckAction|Promise} Action, or Promise which resolves with an Action or null
 */
mw.editcheck.BaseEditCheck.prototype.onBranchNodeChange = null;

/**
 * User performs an action on an check
 *
 * @abstract
 * @param {string} choice `action` key from static.choices
 * @param {mw.editcheck.EditCheckAction} action
 * @param {ve.ui.Surface} surface
 * @return {jQuery.Promise|undefined} Promise which resolves when action is complete, or undefined if there's nothing to wait on
 */
mw.editcheck.BaseEditCheck.prototype.act = function ( choice, action ) {
	if ( choice === 'dismiss' ) {
		this.dismiss( action );
	}
};

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
 * @return {jQuery|string|Function|OO.ui.HtmlSnippet}
 */
mw.editcheck.BaseEditCheck.prototype.getDescription = function () {
	return this.constructor.static.description;
};

/**
 * @return {boolean}
 */
mw.editcheck.BaseEditCheck.prototype.takesFocus = function () {
	return this.constructor.static.takesFocus;
};

/**
 * Find out whether the check should be applied
 *
 * This is a general check for its applicability to the viewer / page, rather
 * than a specific check based on the current edit. It's used to filter out
 * checks before any maybe-expensive content analysis happens.
 *
 * @param {ve.dm.Document} [documentModel] if attached to a known document
 * @param {boolean} [suggestion=false] Whether we are checking for suggestion mode
 * @return {boolean} Whether the check should be shown
 */
mw.editcheck.BaseEditCheck.prototype.canBeShown = function ( documentModel = undefined, suggestion = false ) {
	// All checks are only in the main namespace for now
	if ( mw.config.get( 'wgNamespaceNumber' ) !== mw.config.get( 'wgNamespaceIds' )[ '' ] ) {
		return false;
	}
	// Disambiguation page check
	if ( this.config.ignoreDisambiguationPages && mw.config.get( 'wgVisualEditorPageIsDisambiguation' ) ) {
		return false;
	}
	if ( !this.constructor.static.doesConfigMatch( this.config, documentModel, suggestion ) ) {
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
 * Get annotation ranges where at least some content has been modified
 *
 * @param {ve.dm.Document} documentModel
 * @param {string[]} [names] Names of annotations to filter for
 * @return {ve.dm.LinearData.AnnotationRange[]} Annotation ranges, containing an annotation and its range
 */
mw.editcheck.BaseEditCheck.prototype.getModifiedAnnotationRanges = function ( documentModel, names ) {
	const modified = this.getModifiedContentRanges( documentModel );

	return documentModel.getDocumentNode().getAnnotationRanges().filter(
		// Try to apply fastest filters first
		( annRange ) => modified.some( ( modifiedRange ) => modifiedRange.containsRange( annRange.range ) ) &&
			( !names || names.includes( annRange.annotation.name ) ) &&
			!this.isDismissedRange( annRange.range ) &&
			// isRangeInValidSection is relatively expensive, so check last
			this.isRangeInValidSection( annRange.range, documentModel )
	);
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
	return documentModel.getOrInsertCachedData( () => {
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
	}, `editcheck-addednodes-${ JSON.stringify( [ this.includeSuggestions, type ] ) }` );
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
 * In suggestion mode, this will return all content ranges.
 *
 * @param {ve.dm.Document} documentModel
 * @param {boolean} coveredNodesOnly Only include ranges which cover the whole of their node
 * @param {boolean} onlyContentRanges Only return ranges which are content branch node interiors
 * @param {boolean} onlyPureInsertions Only return ranges which didn't replace any other content
 * @return {ve.Range[]}
 */
mw.editcheck.BaseEditCheck.prototype.getModifiedRanges = function ( documentModel, coveredNodesOnly, onlyContentRanges, onlyPureInsertions ) {
	if ( !this.includeSuggestions && !documentModel.completeHistory.getLength() ) {
		return [];
	}
	return documentModel.getOrInsertCachedData( () => {
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

			const opIsNoOp = ( op ) => (
				// This is a no-op operation that added-and-removed
				// identical content. TransactionSquasher will
				// generate these operations when undo/redo have been used.
				op.insert.length === op.remove.length &&
				ve.compare( op.insert, op.remove )
			);

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
					if (
						!opIsNoOp( op ) && (
							( !onlyPureInsertions && op.insert.length > 0 ) ||
							// Only removals of content count, not element open/closes.
							// TODO: this could be extended so removals of inline elements do count
							!op.remove.some( ( item ) => !ve.dm.LinearData.static.isElementData( item ) )
						)
					) {
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
		return ranges;
	}, `editcheck-modifiedranges-${
		JSON.stringify( [ this.includeSuggestions, coveredNodesOnly, onlyContentRanges, onlyPureInsertions ] )
	}` ).filter( ( range ) => this.isRangeValid( range, documentModel ) );
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
 * @param {Object} [config] Override config to use instead of the check's default
 * @return {boolean}
 */
mw.editcheck.BaseEditCheck.prototype.isRangeValid = function ( range, documentModel, config ) {
	if ( !this.isRangeInValidSection( range, documentModel, config ) ) {
		return false;
	}
	if ( ( config || this.config ).ignoreQuotedContent && this.isOffsetQuoted( range.start, documentModel ) ) {
		return false;
	}
	return true;
};

/**
 * Get the heading hierarchy at a given range
 *
 * @param {number} offset
 * @param {ve.dm.Document} documentModel
 * @return {ve.dm.MWHeadingNode[]} Heading nodes from nearest to furthest
 */
mw.editcheck.BaseEditCheck.prototype.getHeadingHierarchyFromOffset = function ( offset, documentModel ) {
	const headings = this.getHeadingsFromDocument( documentModel );
	if ( !headings.length ) {
		return [];
	}
	let headingIndex = headings.findIndex( ( heading ) => heading.getRange().start > offset );

	if ( headingIndex === -1 ) {
		headingIndex = headings.length;
	}
	headingIndex--;

	const headingHierarchy = [];
	let minLevelSeen = Infinity;
	while ( headingIndex >= 0 ) {
		const nextHeading = headings[ headingIndex ];
		const level = nextHeading.getAttribute( 'level' );
		if ( level < minLevelSeen ) {
			headingHierarchy.push( nextHeading );
			minLevelSeen = level;
			if ( minLevelSeen <= 1 ) {
				// <h1> is the highest level, no need to search further
				break;
			}
		}
		headingIndex--;
	}
	return headingHierarchy;
};

mw.editcheck.BaseEditCheck.prototype.getHeadingsFromDocument = function ( documentModel ) {
	return documentModel.getOrInsertCachedData( () => (
		documentModel.getNodesByType( 'mwHeading', true )
	), 'editcheck-headings' );
};

/**
 * Check if a modified range is a section we allow
 *
 * This checks config.ignoreSections and config.includeSections, which are
 * arrays containing strings that will be compared to the heading names. As a
 * special-case, an empty string will be treated as referring to the lead
 * section. Articles that don't contain any headings at all are "stubs" and
 * won't be treated as having a lead section.
 *
 * @param {ve.Range} range
 * @param {ve.dm.Document} documentModel
 * @param {Object} [config] Override config to use instead of the check's default
 * @return {boolean} Whether the range is in a section we don't ignore
 */
mw.editcheck.BaseEditCheck.prototype.isRangeInValidSection = function ( range, documentModel, config ) {
	config = config || this.config;
	const ignoreSections = config.ignoreSections || [];
	const includeSections = config.includeSections;
	const shouldIncludeSections = Array.isArray( includeSections );
	if ( config.ignoreLeadSection ) {
		// backwards compatibility
		ignoreSections.push( '' );
	}
	if ( ignoreSections.length === 0 && !shouldIncludeSections ) {
		// No restrictions, so skip the rest
		return true;
	}
	const headingHierarchy = this.getHeadingHierarchyFromOffset( range.start, documentModel );

	if ( headingHierarchy.length === 0 ) {
		// There's no preceding heading, so work out if we count as being in a
		// lead section. It's only a lead section if there are more headings
		// later in the document, otherwise it's just a stub article.
		if ( this.getHeadingsFromDocument( documentModel ).some(
			( heading ) => heading.getRange().start > range.start
		) ) {
			if ( shouldIncludeSections ) {
				return includeSections.includes( '' );
			}
			return !ignoreSections.includes( '' );
		}
		// We're in a non-sectioned document, so ignoreSections can't apply.
		// We can skip the checks below by just seeing whether there are any
		// includeSections restrictions present at all. (Just like ignoring
		// the lead wouldn't count, explicitly including the lead also wouldn't.)
		return !shouldIncludeSections;
	}

	const compare = new Intl.Collator( documentModel.getLang(), { sensitivity: 'accent' } ).compare;

	// Climb the heirarchy bottom-up and return the first time we find an
	// ignored or excluded section
	for ( let i = 0; i < headingHierarchy.length; i++ ) {
		const headingText = documentModel.data.getText( false, headingHierarchy[ i ].getRange() );
		if ( shouldIncludeSections && includeSections.some( ( section ) => compare( headingText, section ) === 0 ) ) {
			return true;
		}
		if ( ignoreSections.length > 0 && ignoreSections.some( ( section ) => compare( headingText, section ) === 0 ) ) {
			return false;
		}
	}

	// Nothing matched, so return true only if we weren't restricting to specific sections
	return !shouldIncludeSections;
};

mw.editcheck.BaseEditCheck.static.quoteGroupings = new Map( [
	// This groups various quote-types together so we'll know which count
	// as opening/closing each other.
	// See: https://en.wikipedia.org/wiki/Quotation_mark#Summary_table
	// [ character, grouping ]
	[ '"', '"' ], // plain quotation mark
	[ '“', '“' ], [ '”', '“' ], [ '„', '“' ], // various curly quotes
	[ '‘', '‘' ], [ '’', '‘' ], [ '‚', '‘' ], // curly single quotes (that last one is SINGLE LOW-9 QUOTATION MARK, not a comma)
	[ '「', '「' ], [ '」', '「' ], // CJK brackets
	[ '『', '『' ], [ '』', '『' ], // CJK brackets
	[ '«', '«' ], [ '»', '«' ], // guillemets
	[ '‹', '‹' ], [ '›', '‹' ], // guillemets
	[ '《', '《' ], [ '》', '《' ], // "Far East angle bracket quotation marks"
	[ '⟪', '⟪' ], [ '⟫', '⟪' ],
	[ '⟨', '⟨' ], [ '⟩', '⟨' ]
] );

/**
 * Check if a specific offset in the document counts as being quoted
 *
 * This is approximate because "quoted" is complicated. Various types of
 * quotes are grouped together, and we count whether there's an odd number of
 * any group preceding the offset within the current content-containing
 * node.
 *
 * Special attention is paid to distinguishing apostrophes from single-quotes,
 * and blockquote nodes are explicitly always quoted.
 *
 * @param {number} offset
 * @param {ve.dm.Document} documentModel
 * @return {boolean}
 */
mw.editcheck.BaseEditCheck.prototype.isOffsetQuoted = function ( offset, documentModel ) {
	const closestBlockNode = documentModel.getNearestNodeMatching( ( nodeType ) => (
		ve.dm.nodeFactory.canNodeContainContent( nodeType )
	), offset, -1, 0 );
	if ( !closestBlockNode ) {
		// You'd need a really weird range to manage this, but just in case
		return false;
	}
	if ( closestBlockNode.hasMatchingAncestor( 'blockquote' ) ) {
		return true;
	}
	// This is going to ignore template content, which might be non-ideal in
	// some edge cases. A future enhancement could be to allow configuration
	// of templates that count as unbalanced quotes.
	// Fetch to offset+1 because we want inclusive.
	const data = documentModel.data;
	const quotes = new Map();
	for ( let i = closestBlockNode.getRange().start; i < offset + 1; i++ ) {
		if ( !data.isElementData( i ) ) {
			let quote = false;
			const character = data.getCharacterData( i );
			if ( character === '\'' ) {
				// The single non-curly quote requires extra work to be distinguished from an apostrophe
				if (
					// If it's at the beginning it must be a quote
					data.isOpenElementData( i - 1 ) ||
					// Otherwise, check whether it looks like a break compared
					// to the previous character. Note: unicodeJS does
					// somewhat-complicated things here to look back and
					// decide whether this is a break, so it actually does
					// need the entire data -- just [previous,item] would not work.
					unicodeJS.wordbreak.isBreak( new ve.dm.DataString( data.getData() ), i )
				) {
					const previousCharacter = data.getCharacterData( i - 1 );
					if (
						previousCharacter && mw.config.get( 'wgContentLanguage' ) === 'en' &&
						previousCharacter === 's' && ( quotes.get( '\'' ) || 0 ) % 2 === 0
					) {
						// One extra check to rule out English's possessive
						// apostrophes following a `s`. There's no way to
						// tell the difference between a closing single-quote
						// and a possessive, but we can say that they can't
						// count as opening quotes.
						continue;
					}
					quote = '\'';
				}
			} else {
				quote = this.constructor.static.quoteGroupings.get( character );
			}
			if ( quote ) {
				quotes.set( quote, ( quotes.get( quote ) || 0 ) + 1 );
			}
		}
	}
	return Array.from( quotes.values() ).some( ( count ) => count % 2 === 1 );
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
	if ( !name ) {
		name = this.constructor.static.name;
	}
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

/**
 * Show a success notification
 *
 * @param {string} [message] Message to show; defaults to static.success
 */
mw.editcheck.BaseEditCheck.prototype.showSuccess = function ( message ) {
	mw.notify( message || this.constructor.static.success, { type: 'success' } );
};
