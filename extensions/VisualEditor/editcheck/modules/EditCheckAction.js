/**
 * EditCheckAction
 *
 * @class
 * @mixes OO.EventEmitter
 *
 * @param {Object} config Configuration options
 * @param {mw.editcheck.BaseEditCheck} config.check Check which created this action
 * @param {ve.dm.SurfaceFragment[]} config.fragments Affected fragments
 * @param {ve.dm.SurfaceFragment} [config.focusFragment] Fragment to focus
 * @param {Function} [config.focusAnnotation] Annotation to focus, see ve.ce.Surface#selectAnnotation
 * @param {jQuery|string|Function|OO.ui.HtmlSnippet} [config.title] Title
 * @param {jQuery|string|Function|OO.ui.HtmlSnippet} [config.message] Body message
 * @param {jQuery|string|Function|OO.ui.HtmlSnippet} [config.prompt] Prompt to show before choices
 * @param {string} [config.id] Optional unique identifier
 * @param {string} [config.icon] Optional icon name
 * @param {string} [config.type='warning'] Type of message (e.g., 'warning', 'error')
 * @param {boolean} [config.suggestion] Whether this is a suggestion
 * @param {Object[]} [config.choices] User choices
 */
mw.editcheck.EditCheckAction = function MWEditCheckAction( config ) {
	// Mixin constructor
	OO.EventEmitter.call( this );

	this.mode = config.mode || '';
	this.check = config.check;
	this.fragments = config.fragments;
	this.originalText = this.fragments.map( ( fragment ) => fragment.getText() );
	this.focusFragment = config.focusFragment;
	this.focusAnnotation = config.focusAnnotation;
	this.message = config.message;
	this.prompt = config.prompt;
	this.footer = config.footer;
	this.id = config.id;
	this.title = config.title;
	this.icon = config.icon;
	this.type = config.type || 'warning';
	this.choices = config.choices || config.check.constructor.static.choices;
	this.suggestion = config.suggestion;
	this.widget = null;
	this.stale = false;
};

/* Inheritance */

OO.mixinClass( mw.editcheck.EditCheckAction, OO.EventEmitter );

/* Events */

/**
 * Fired when the user selects an action (e.g., clicks a suggestion button).
 *
 * @event mw.editcheck.EditCheckAction#act
 * @param {jQuery.Promise} promise A promise that resolves when the action is complete
 */

/**
 * Fired when the action's stale state changes
 *
 * @event mw.editcheck.EditCheckAction#stale
 * @param {boolean} stale The check is stale
 */

/**
 * Fired when the action is shown to the user
 *
 * @event mw.editcheck.EditCheckAction#shown
 * @param {boolean} shown The check is shown
 */

/**
 * Fired when the action is seen by the user
 *
 * @event mw.editcheck.EditCheckAction#seen
 * @param {boolean} seen The check is seen
 */

/* Methods */

/**
 * Compare the start offsets of two actions.
 *
 * @param {mw.editcheck.EditCheckAction} a
 * @param {mw.editcheck.EditCheckAction} b
 * @return {number}
 */
mw.editcheck.EditCheckAction.static.compareStarts = function ( a, b ) {
	const aStart = a.getHighlightSelections()[ 0 ].getCoveringRange().start;
	const bStart = b.getHighlightSelections()[ 0 ].getCoveringRange().start;
	const difference = aStart - bStart;
	if ( difference === 0 ) {
		if ( a.check.takesFocus() ) {
			return -1;
		}
		if ( b.check.takesFocus() ) {
			return 1;
		}
		const aEnd = a.getHighlightSelections()[ 0 ].getCoveringRange().end;
		const bEnd = b.getHighlightSelections()[ 0 ].getCoveringRange().end;
		return bEnd - aEnd;
	}
	return difference;
};

/**
 * Get the action's title
 *
 * @return {jQuery|string|Function|OO.ui.HtmlSnippet}
 */
mw.editcheck.EditCheckAction.prototype.getTitle = function () {
	return this.title || this.check.getTitle( this );
};

/**
 * Get the action's footer, if any
 *
 * @return {jQuery|string|Function|OO.ui.HtmlSnippet|undefined}
 */
mw.editcheck.EditCheckAction.prototype.getFooter = function () {
	return this.footer || this.check.getFooter( this );
};

/**
 * Get the prompt question for the current choices
 *
 * @return {jQuery|string|Function|OO.ui.HtmlSnippet|undefined}
 */
mw.editcheck.EditCheckAction.prototype.getPrompt = function () {
	return this.prompt || this.check.getPrompt( this );
};

/**
 * Get the available choices
 *
 * @return {Object[]}
 */
mw.editcheck.EditCheckAction.prototype.getChoices = function () {
	return this.choices;
};

/**
 * Get selections to highlight for this check
 *
 * @return {ve.dm.Selection[]}
 */
mw.editcheck.EditCheckAction.prototype.getHighlightSelections = function () {
	return this.fragments.map( ( fragment ) => fragment.getSelection() );
};

/**
 * Get the selection to focus for this check
 *
 * @return {ve.dm.Selection}
 */
mw.editcheck.EditCheckAction.prototype.getFocusSelection = function () {
	// TODO: Instead of fragments[0], create a fragment that covers all fragments?
	return ( this.focusFragment || this.fragments[ 0 ] ).getSelection();
};

/**
 * Get a description of the check
 *
 * @return {jQuery|string|Function|OO.ui.HtmlSnippet}
 */
mw.editcheck.EditCheckAction.prototype.getDescription = function () {
	return this.message || this.check.getDescription( this );
};

/**
 * Get the type of this action (e.g., 'warning', 'error')
 *
 * @return {string}
 */
mw.editcheck.EditCheckAction.prototype.getType = function () {
	if ( this.suggestion ) {
		return 'progressive';
	}
	return this.type;
};

/**
 * Get the name of the check type
 *
 * @return {string} Check type name
 */
mw.editcheck.EditCheckAction.prototype.getName = function () {
	return this.check.getName();
};

/**
 * Whether this is a suggestion
 *
 * @return {boolean}
 */
mw.editcheck.EditCheckAction.prototype.isSuggestion = function () {
	return this.suggestion;
};

/**
 * Render as an EditCheckActionWidget
 *
 * @param {boolean} collapsed Start collapsed
 * @param {boolean} singleAction This is the only action shown
 * @param {ve.ui.Surface} surface Surface
 * @return {mw.editcheck.EditCheckActionWidget}
 */
mw.editcheck.EditCheckAction.prototype.render = function ( collapsed, singleAction, surface ) {
	const enabledByDefault = ( !this.suggestion && this.check.config.showAsCheck ) ||
		( this.suggestion && this.check.config.showAsSuggestion );
	this.widget = new mw.editcheck.EditCheckActionWidget( {
		type: this.getType(),
		icon: this.icon,
		name: this.getName(),
		label: this.getTitle(),
		message: this.getDescription(),
		footer: this.getFooter(),
		prompt: this.getPrompt(),
		choices: this.getChoices(),
		mode: this.mode,
		singleAction,
		suggestion: this.suggestion,
		experimental: !enabledByDefault
	} );
	this.widget.connect( this, {
		actionClick: [ 'onActionClick', surface ]
	} );
	// On mobile, 'shown' is already emitted by the GutterSidebarEditCheckDialog, so skip emitting here
	// (though technically the controller should dedupe anyway)
	if ( !OO.ui.isMobile() ) {
		this.emit( 'shown' );
	}
	this.widget.once( 'actionSeen', this.onActionSeen.bind( this ) );
	this.widget.toggleCollapse( collapsed );

	return this.widget;
};

/**
 * Set the mode used by the action widget
 *
 * @param {string} mode
 */
mw.editcheck.EditCheckAction.prototype.setMode = function ( mode ) {
	this.mode = mode;
	if ( this.widget ) {
		this.widget.setMode( mode );
	}
};

/**
 * Handle click events from an action button
 *
 * @param {ve.ui.Surface} surface Surface
 * @param {OO.ui.ActionWidget} actionWidget Clicked action widget
 * @fires mw.editcheck.EditCheckAction#act
 */
mw.editcheck.EditCheckAction.prototype.onActionClick = function ( surface, actionWidget ) {
	const promise = this.check.act( actionWidget.action, this, surface );
	this.emit( 'act', promise || ve.createDeferred().resolve().promise(), actionWidget.action );
};

/**
 * Handle seen events from an action widget
 *
 * @fires mw.editcheck.EditCheckAction#seen
 */
mw.editcheck.EditCheckAction.prototype.onActionSeen = function () {
	this.emit( 'seen' );
};

/**
 * Compare to another action
 *
 * @param {mw.editcheck.EditCheckAction} other Other action
 * @param {boolean} allowOverlaps Count overlaps rather than a perfect match
 * @return {boolean}
 */
mw.editcheck.EditCheckAction.prototype.equals = function ( other, allowOverlaps ) {
	if ( this.check.constructor !== other.check.constructor ) {
		return false;
	}
	if ( this.id !== other.id ) {
		return false;
	}
	if ( this.fragments.length !== other.fragments.length ) {
		return false;
	}
	return this.fragments.every( ( fragment, i ) => {
		if ( allowOverlaps ) {
			const selection = fragment.getSelection();
			return other.fragments.some( ( otherFragment ) => {
				if ( otherFragment.getSelection().equals( selection ) ) {
					// A perfect match always counts, and also covers the case of
					// zero-width ranges on the same point which don't "overlap"
					return true;
				}
				// This case is meant to catch suggestions which were generated on
				// the same content but which don't perfectly match up because the
				// modified range is different.
				const range = selection.getCoveringRange(),
					otherRange = otherFragment.getSelection().getCoveringRange();
				// If one is collapsed we accept them touching, otherwise we
				// only allow overlaps.
				return ( range.isCollapsed() || otherRange.isCollapsed() ) ?
					otherRange.touchesRange( range ) :
					otherRange.overlapsRange( range );
			} );
		} else {
			return fragment.getSelection().equals( other.fragments[ i ].getSelection() );
		}
	} );
};

/**
 * Update the stale state of the action based on the text, or force a specific state
 *
 * @param {boolean} [forceStale] Force the action into a stale or not-stale state
 */
mw.editcheck.EditCheckAction.prototype.updateStale = function ( forceStale ) {
	const wasStale = this.isStale();
	if ( forceStale !== undefined ) {
		this.originalText = forceStale ? null : this.fragments.map( ( fragment ) => fragment.getText() );
	}
	this.stale = !this.originalText || !OO.compare(
		this.originalText,
		this.fragments.map( ( fragment ) => fragment.getText() )
	);
	if ( wasStale !== this.stale ) {
		this.emit( 'stale', this.stale );
	}
};

/**
 * Get the stale state of the action
 *
 * Users must call #updateStale first if they want to get the latest
 * state based on the current text.
 *
 * @return {boolean} The action is stale
 */
mw.editcheck.EditCheckAction.prototype.isStale = function () {
	return this.check.canBeStale() && this.stale;
};

/**
 * Method called by the controller when the action is removed from the action list
 */
mw.editcheck.EditCheckAction.prototype.discarded = function () {
	this.emit( 'discard' );
};

/**
 * Tag this action
 *
 * @param {string} tag
 */
mw.editcheck.EditCheckAction.prototype.tag = function ( tag ) {
	this.check.tag( tag, this );
};

/**
 * Untag this action
 *
 * @param {string} tag
 * @return {boolean} Whether anything was untagged
 */
mw.editcheck.EditCheckAction.prototype.untag = function ( tag ) {
	return this.check.untag( tag, this );
};

/**
 * Is this action tagged?
 *
 * @param {string} tag
 * @return {boolean}
 */
mw.editcheck.EditCheckAction.prototype.isTagged = function ( tag ) {
	if ( this.id ) {
		return this.check.isTaggedId( tag, this.id );
	} else {
		return this.fragments.some( ( fragment ) => this.check.isTaggedRange( tag, fragment.getSelection().getRange() ) );
	}
};

/**
 * Get unique tag name for this action
 *
 * @return {string}
 */
mw.editcheck.EditCheckAction.prototype.getTagName = function () {
	return this.check.constructor.static.name;
};

/**
 * Select the action in the surface
 *
 * @param {ve.ui.Surface} surface
 * @param {boolean} selectFocusRange Whether to select the focus range of the check,
 *  or just move the cursor to the nearest point in the selection if outside the check range
 * @param {boolean} [focus=true] Activate and focus the surface
 */
mw.editcheck.EditCheckAction.prototype.select = function ( surface, selectFocusRange, focus = true ) {
	const surfaceModel = surface.getModel();
	const surfaceView = surface.getView();
	if ( focus ) {
		surfaceView.activate();
	}
	if ( this.focusAnnotation ) {
		surfaceModel.setSelection( this.getFocusSelection() );
		if ( focus ) {
			surfaceView.selectAnnotation( this.focusAnnotation );
		}
	} else {
		const checkRange = this.getFocusSelection().getCoveringRange();
		if ( selectFocusRange || surfaceView.findFocusedNode( checkRange ) ) {
			surfaceModel.setLinearSelection( checkRange );
		} else {
			const surfaceRange = surfaceModel.getSelection().getCoveringRange();
			// Collapse and move the selection to the nearest part of the check range
			// Don't alter it if it touches the check range
			if ( surfaceRange === null || surfaceRange.end < checkRange.start ) {
				surfaceModel.setLinearSelection( new ve.Range( checkRange.start ) );
			} else if ( surfaceRange.start > checkRange.end ) {
				surfaceModel.setLinearSelection( new ve.Range( checkRange.end ) );
			}
		}
		if ( focus ) {
			surfaceView.focus();
		}
	}
};

/**
 *
 * Check if any of this action's fragments' ranges overlap with the given ranges
 *
 * @param {ve.Range[]} ranges The ranges
 * @return {boolean} True if any overlap exists
 */
mw.editcheck.EditCheckAction.prototype.overlapsRanges = function ( ranges ) {
	return this.fragments.some( ( fragment ) => {
		const sel = fragment.getSelection();
		let fragmentRange;
		if ( sel instanceof ve.dm.LinearSelection ) {
			fragmentRange = sel.getRange();
		} else if ( sel instanceof ve.dm.TableSelection ) {
			fragmentRange = sel.getCoveringRange();
		} else {
			return false;
		}
		return ranges.some( ( range ) => range.overlapsRange( fragmentRange ) );
	} );
};

/**
 * Check whether every range of this action has been dismissed for this check
 *
 * @param {string} tag
 * @return {boolean}
 */
mw.editcheck.EditCheckAction.prototype.isDismissed = function () {
	return this.isTagged( 'dismissed' );
};

/**
 * Check whether every range of this action has been tagged for this check
 *
 * @param {string} tag
 * @return {boolean}
 */
mw.editcheck.EditCheckAction.prototype.isTagged = function ( tag ) {
	return this.fragments.every( ( fragment ) => {
		const sel = fragment.getSelection();
		let fragmentRange;
		if ( sel instanceof ve.dm.LinearSelection ) {
			fragmentRange = sel.getRange();
		} else if ( sel instanceof ve.dm.TableSelection ) {
			fragmentRange = sel.getCoveringRange();
		} else {
			return false;
		}
		return this.check.isTaggedRange( fragmentRange, tag );
	} );
};
