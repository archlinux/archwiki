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
	this.message = config.message;
	this.prompt = config.prompt;
	this.footer = config.footer;
	this.id = config.id;
	this.title = config.title;
	this.icon = config.icon;
	this.type = config.type || 'warning';
	this.choices = config.choices || config.check.constructor.static.choices;
	this.suggestion = config.suggestion;
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
	return aStart - bStart;
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
 * @return {string}
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
		return 'success';
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
	const widget = new mw.editcheck.EditCheckActionWidget( {
		type: this.getType(),
		icon: this.icon,
		name: this.getName(),
		label: this.getTitle(),
		message: this.getDescription(),
		footer: this.getFooter(),
		prompt: this.getPrompt(),
		mode: this.mode,
		singleAction: singleAction,
		suggestion: this.suggestion
	} );
	widget.actions.connect( this, {
		click: [ 'onActionClick', surface ]
	} );
	widget.actions.add( this.getChoices().map(
		( choice ) => new OO.ui.ActionWidget( ve.extendObject( { modes: [ '' ], framed: true }, choice ) )
	) );
	widget.toggleCollapse( collapsed );

	return widget;
};

mw.editcheck.EditCheckAction.prototype.setMode = function ( mode ) {
	this.mode = mode;
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
	this.emit( 'act', promise || ve.createDeferred().resolve().promise() );
	ve.track( 'activity.editCheck-' + this.getName(), {
		action: 'action-' + ( actionWidget.getAction() || 'unknown' )
	} );
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
	if ( this.id || other.id ) {
		return this.id === other.id;
	}
	if ( this.fragments.length !== other.fragments.length ) {
		return false;
	}
	return this.fragments.every( ( fragment ) => {
		const selection = fragment.getSelection();
		return other.fragments.some( ( otherFragment ) => {
			if ( allowOverlaps ) {
				return otherFragment.getSelection().getCoveringRange().overlapsRange( selection.getCoveringRange() );
			} else {
				return otherFragment.getSelection().equals( selection );
			}
		} );
	} );
};

/**
 * Force the action into a stale or not-stale state
 *
 * @param {boolean} stale
 */
mw.editcheck.EditCheckAction.prototype.setStale = function ( stale ) {
	const previousState = this.isStale();
	this.originalText = stale ? null : this.fragments.map( ( fragment ) => fragment.getText() );
	if ( previousState !== this.isStale() ) {
		this.emit( 'stale', this.isStale() );
	}
};

/**
 * Check whether the text has changed since this action was created
 *
 * @return {boolean} Whether the text has changed since this action was created
 */
mw.editcheck.EditCheckAction.prototype.isStale = function () {
	return this.check.canBeStale() && (
		!this.originalText || !OO.compare(
			this.originalText,
			this.fragments.map( ( fragment ) => fragment.getText() )
		)
	);
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
