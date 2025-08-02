mw.editcheck.BaseEditCheck = function MWBaseEditCheck( controller, config ) {
	this.controller = controller;
	this.config = ve.extendObject( {}, this.constructor.static.defaultConfig, config );
};

OO.initClass( mw.editcheck.BaseEditCheck );

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
	account: false, // 'loggedin', 'loggedout', anything non-truthy means allow either
	maximumEditcount: 100,
	ignoreSections: [],
	ignoreLeadSection: false
};

mw.editcheck.BaseEditCheck.static.title = ve.msg( 'editcheck-review-title' );

mw.editcheck.BaseEditCheck.static.description = ve.msg( 'editcheck-dialog-addref-description' );

/**
 * Get the name of the check type
 *
 * @return {string} Check type name
 */
mw.editcheck.BaseEditCheck.prototype.getName = function () {
	return this.constructor.static.name;
};

/**
 * @param {ve.dm.Surface} surfaceModel
 * @return {mw.editcheck.EditCheckAction[]}
 */
mw.editcheck.BaseEditCheck.prototype.onBeforeSave = null;

/**
 * @param {ve.dm.Surface} surfaceModel
 * @return {mw.editcheck.EditCheckAction[]}
 */
mw.editcheck.BaseEditCheck.prototype.onDocumentChange = null;

/**
 * @param {string} choice `action` key from static.choices
 * @param {mw.editcheck.EditCheckAction} action
 * @param {ve.ui.Surface} surface
 * @return {jQuery.Promise} Promise which resolves when action is complete
 */
mw.editcheck.BaseEditCheck.prototype.act = null;

/**
 * @param {mw.editcheck.EditCheckAction} action
 * @return {Object[]}
 */
mw.editcheck.BaseEditCheck.prototype.getChoices = function () {
	return this.constructor.static.choices;
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
	if ( mw.editcheck.ecenable ) {
		return true;
	}
	// account status:
	// loggedin, loggedout, or any-other-value meaning 'both'
	// we'll count temporary users as "logged out" by using isNamed here
	if ( this.config.account === 'loggedout' && mw.user.isNamed() ) {
		return false;
	}
	if ( this.config.account === 'loggedin' && !mw.user.isNamed() ) {
		return false;
	}
	// some checks are only shown for newer users
	if ( this.config.maximumEditcount && mw.config.get( 'wgUserEditCount', 0 ) > this.config.maximumEditcount ) {
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
 * Find nodes that were added during the edit session
 *
 * @param {ve.dm.Document} documentModel
 * @param {string} [type] Type of nodes to find, or all nodes if false
 * @return {ve.dm.Node[]}
 */
mw.editcheck.BaseEditCheck.prototype.getAddedNodes = function ( documentModel, type ) {
	const matchedNodes = [];
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
mw.editcheck.BaseEditCheck.prototype.getModifiedRanges = function ( documentModel, coveredNodesOnly, onlyContentRanges ) {
	if ( !documentModel.completeHistory.getLength() ) {
		return [];
	}
	let operations;
	try {
		operations = documentModel.completeHistory.squash().transactions[ 0 ].operations;
	} catch ( err ) {
		// TransactionSquasher can sometimes throw errors; until T333710 is
		// fixed just count this as not needing a reference.
		mw.errorLogger.logError( err, 'error.visualeditor' );
		return [];
	}

	const ranges = [];
	let offset = 0;
	const endOffset = documentModel.getDocumentRange().end;
	operations.every( ( op ) => {
		if ( op.type === 'retain' ) {
			offset += op.length;
		} else if ( op.type === 'replace' ) {
			const insertedRange = new ve.Range( offset, offset + op.insert.length );
			offset += op.insert.length;
			// 1. Only trigger if the check is a pure insertion, with no adjacent content removed (T340088)
			if ( op.remove.length === 0 ) {
				if ( onlyContentRanges ) {
					ve.batchPush(
						ranges,
						// 2. Only fully inserted paragraphs (ranges that cover the whole node) (T345121)
						this.getContentRangesFromRange( documentModel, insertedRange, coveredNodesOnly )
					);
				} else {
					ranges.push( insertedRange );
				}
			}
		}
		// Reached the end of the doc / start of internal list, stop searching
		return offset < endOffset;
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
 * @return {boolean}
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
	const name = this.constructor.static.name;
	if ( action.id ) {
		const dismissedIds = this.controller.dismissedIds;
		dismissedIds[ name ] = dismissedIds[ name ] || [];
		dismissedIds[ name ].push( action.id );
	} else {
		const dismissedFragments = this.controller.dismissedFragments;
		dismissedFragments[ name ] = dismissedFragments[ name ] || [];
		dismissedFragments[ name ].push(
			// Exclude insertions so we don't accidentally block unrelated changes:
			...action.fragments.map( ( fragment ) => fragment.clone().setExcludeInsertions( true ) )
		);
	}
};

/**
 * Check if this type of check has been dismissed covering a specific range
 *
 * @param {ve.Range} range
 * @return {boolean}
 */
mw.editcheck.BaseEditCheck.prototype.isDismissedRange = function ( range ) {
	const fragments = this.controller.dismissedFragments[ this.constructor.static.name ];
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
	const ids = this.controller.dismissedIds[ this.constructor.static.name ];
	return ids && ids.includes( id );
};
