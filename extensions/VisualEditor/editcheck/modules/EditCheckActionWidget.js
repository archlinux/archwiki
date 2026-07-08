/**
 * EditCheckActionWidget
 *
 * @class
 * @extends OO.ui.MessageWidget
 *
 * @param {Object} config Configuration options
 * @param {string} config.type Type of message (e.g., 'warning', 'error')
 * @param {string|jQuery|Function|OO.ui.HtmlSnippet} config.label Title
 * @param {string|jQuery|Function|OO.ui.HtmlSnippet} config.message Body message
 * @param {string|jQuery|Function|OO.ui.HtmlSnippet} [config.footer] Footer message
 * @param {string} [config.icon] Icon name
 * @param {boolean} [config.singleAction] This is the only action shown
 * @param {Object[]} [config.choices] User choices
 * @param {string} [config.mode] Mode for the action set widget
 * @param {boolean} [config.suggestion] This is a suggestion
 * @param {boolean} [config.experimental] This is an experimental check (not enabled by default)
 */
mw.editcheck.EditCheckActionWidget = function MWEditCheckActionWidget( config ) {
	this.singleAction = config.singleAction;
	this.mode = config.mode || '';
	this.suggestion = config.suggestion;

	this.name = config.name;

	this.actions = new OO.ui.ActionSet();
	this.actions.connect( this, {
		change: 'onActionsChange',
		click: [ 'emit', 'actionClick' ]
	} );

	mw.editcheck.EditCheckActionWidget.super.call( this, config );

	this.inactiveSelectionElements = null;
	this.feedbackDeferred = null;

	this.collapsed = false;
	this.message = new OO.ui.LabelWidget( { label: config.message } );
	mw.editcheck.trackActionLinks( this.message.$element, this.name, 'click-learn-more' );
	ve.targetLinksToNewWindow( this.message.$element[ 0 ] );

	this.prompt = config.prompt && new OO.ui.LabelWidget( {
		label: config.prompt,
		classes: [ 've-ui-editCheckActionWidget-prompt' ]
	} );
	this.footer = config.footer && new OO.ui.LabelWidget( {
		label: config.footer,
		classes: [ 've-ui-editCheckActionWidget-footer' ]
	} );
	this.$actions = $( '<div>' ).addClass( 've-ui-editCheckActionWidget-actions oo-ui-element-hidden' );
	if ( this.prompt ) {
		this.$actions.addClass( 've-ui-editCheckActionWidget-actions-prompted' )
			.append( this.prompt.$element );
	}
	if ( config.choices ) {
		this.actions.add( config.choices.map(
			( choice ) => new OO.ui.ActionWidget( ve.extendObject( { modes: [ '' ], framed: true }, choice ) )
		) );
	}
	this.actions.setMode( this.mode );

	this.$element.on( {
		click: this.onClick.bind( this ),
		mouseenter: this.onMouseEnter.bind( this ),
		mouseleave: this.onMouseLeave.bind( this )
	} );

	this.$body = $( '<div>' )
		.append( this.message.$element, this.$actions )
		.addClass( 've-ui-editCheckActionWidget-body' );

	if ( this.footer ) {
		this.$body.append( this.footer.$element );
		// TODO: Give this action a more generic event name
		mw.editcheck.trackActionLinks( this.footer.$element, this.name, 'click-model-card' );
		ve.targetLinksToNewWindow( this.footer.$element[ 0 ] );
	}

	if ( this.suggestion ) {
		this.$element.addClass( 've-ui-editCheckActionWidget-suggestion' );

		const suggestionFeedbackMenuSelect = new OO.ui.ButtonMenuSelectWidget( {
			label: ve.msg( 'visualeditor-feedback-tool' ),
			icon: 'ellipsis',
			framed: false,
			invisibleLabel: true,
			classes: [ 've-ui-editCheckActionWidget-suggestion-feedbackMenu' ],
			$overlay: true,
			menu: {
				verticalPosition: OO.ui.isMobile() ? 'above' : 'below',
				horizontalPosition: 'end',
				items: [
					new OO.ui.MenuOptionWidget( {
						data: '//www.mediawiki.org/wiki/VisualEditor/Suggestion_Mode',
						label: ve.msg( 'editcheck-suggestionfeedback-label-about' )
					} ),
					new OO.ui.MenuOptionWidget( {
						data: 'feedback',
						label: ve.msg( 'editcheck-suggestionfeedback-label-report' )
					} )
				]
			}
		} );
		suggestionFeedbackMenuSelect.getMenu().on( 'choose', ( menuOption ) => {
			const choice = menuOption.getData();
			if ( choice === 'feedback' ) {
				this.onFeedbackSelect();
			} else if ( !choice ) {
				return;
			} else {
				window.open( choice );
			}
		} );
		this.$actions.append( suggestionFeedbackMenuSelect.$element );
	}

	if ( config.experimental ) {
		const $warning = new OO.ui.MessageWidget( {
			type: 'error',
			label: 'Experimental edit check. For testing purposes only.',
			inline: true
		} ).$element.css( {
			'white-space': 'normal',
			margin: '0.5em 0'
		} );
		this.$actions.append( $warning );
	}

	this.$element
		.append( this.$body )
		.addClass( 've-ui-editCheckActionWidget' );
};

/* Inheritance */

OO.inheritClass( mw.editcheck.EditCheckActionWidget, OO.ui.MessageWidget );

mw.editcheck.EditCheckActionWidget.static.iconMap = ve.copy( mw.editcheck.EditCheckActionWidget.static.iconMap );
mw.editcheck.EditCheckActionWidget.static.iconMap.progressive = 'lightbulb';

/* Events */

/**
 * Fired when the user toggles the collapsed state of the widget.
 *
 * @event mw.editcheck.EditCheckActionWidget#togglecollapse
 */

/**
 * Fired when action set emits a click event
 *
 * @event mw.editcheck.EditCheckActionWidget#actionClick
 */

/* Methods */

/**
 * Set the mode
 *
 * @param {string} mode
 */
mw.editcheck.EditCheckActionWidget.prototype.setMode = function ( mode ) {
	this.mode = mode;
	this.actions.setMode( mode );
};

/**
 * Open suggestion mode feedback dialog
 */
mw.editcheck.EditCheckActionWidget.prototype.onFeedbackSelect = function () {
	const wikiID = mw.config.get( 'wgWikiID' ),
		pageName = mw.config.get( 'wgRelevantPageName' );
	const contents = {
		subject: `${ this.name } on ${ pageName } at ${ wikiID }`
	};
	if ( !this.feedbackPromise ) {
		this.feedbackPromise = mw.loader.using( 'mediawiki.feedback' ).then( () => {

			const feedbackConfig = {
				bugsLink: 'https://phabricator.wikimedia.org/maniphest/task/edit/form/1/?project=PHID-PROJ-g4joo7tiwslypfpk5lkv'
			};

			const veConfig = mw.config.get( 'wgVisualEditorConfig' );
			if ( veConfig.suggestionFeedbackAPIURL ) {
				feedbackConfig.apiUrl = veConfig.suggestionFeedbackAPIURL;
				feedbackConfig.title = new mw.Title( veConfig.suggestionFeedbackTitle );
			} else {
				feedbackConfig.title = new mw.Title( ve.msg( 'visualeditor-suggestionfeedback-link' ) );
			}

			return new mw.Feedback( feedbackConfig );
		} );
	}
	this.feedbackPromise.then( ( feedback ) => {
		feedback.launch( contents );
	} );
};

/**
 * Handle change events on the action set
 */
mw.editcheck.EditCheckActionWidget.prototype.onActionsChange = function () {
	let hasVisibleActions = false;
	this.$actions.children( '.oo-ui-actionWidget' ).detach();
	this.actions.get().forEach( ( actionWidget ) => {
		this.$actions.append( actionWidget.$element );
		hasVisibleActions = hasVisibleActions || actionWidget.isVisible();
	} );
	this.$actions.toggleClass( 'oo-ui-element-hidden', !hasVisibleActions );
};

/**
 * @inheritdoc
 */
mw.editcheck.EditCheckActionWidget.prototype.setDisabled = function ( disabled ) {
	// Calling setDisabled on the parent doesn't do anything useful,
	// and causes the icon to become the wrong color due to an
	// upstream bug
	// Parent method
	// OO.ui.Widget.prototype.setDisabled.call( this, disabled );
	this.actions.forEach( null, ( action ) => {
		action.setDisabled( disabled );
	} );
};

/**
 * Handle click events anywhere on the widget
 *
 * @param {jQuery.Event} e Click event
 * @fires mw.editcheck.EditCheckActionWidget#togglecollapse
 */
mw.editcheck.EditCheckActionWidget.prototype.onClick = function ( e ) {
	if ( this.singleAction ) {
		return;
	}
	if ( this.$body[ 0 ].contains( e.target ) ) {
		return;
	}
	this.emit( 'togglecollapse' );

	e.preventDefault();
};

/**
 * Handle mouse enter events on the widget
 */
mw.editcheck.EditCheckActionWidget.prototype.onMouseEnter = function () {
	if ( this.inactiveSelectionElements ) {
		this.inactiveSelectionElements.$selection.addClass( 've-ce-surface-selection-editCheck-highlighted' );
	}
};

/**
 * Handle mouse leave events on the widget
 */
mw.editcheck.EditCheckActionWidget.prototype.onMouseLeave = function () {
	if ( this.inactiveSelectionElements ) {
		this.inactiveSelectionElements.$selection.removeClass( 've-ce-surface-selection-editCheck-highlighted' );
	}
};

/**
 * Set the selection elements for this action
 *
 * @param {ve.ce.SelectionManager.SelectionElements} selectionElements
 */
mw.editcheck.EditCheckActionWidget.prototype.setInactiveSelectionElements = function ( selectionElements ) {
	this.inactiveSelectionElements = selectionElements;
};

/**
 * Toggle the collapsed state of the widget
 *
 * @param {boolean} [collapsed] The new collapsed state, toggles if unset
 */
mw.editcheck.EditCheckActionWidget.prototype.toggleCollapse = function ( collapsed ) {
	const previousState = this.collapsed;
	// Assume that the widget being expanded means the suggestion/check is seen.
	// (For instrumentation and tagging purposes per T412334)
	if ( !collapsed && previousState ) {
		this.emit( 'actionSeen' );
	}
	this.collapsed = collapsed !== undefined ? collapsed : !this.collapsed;
	this.$element.toggleClass( 've-ui-editCheckActionWidget-collapsed', this.collapsed );

	if ( this.collapsed && previousState !== this.collapsed && this.feedbackDeferred ) {
		this.feedbackDeferred.reject();
	}
};

/**
 * Show a feedback panel
 *
 * @param {Object} data
 * @param {string} data.title
 * @param {string} [data.description]
 * @param {Object[]} data.choices
 * @return {jQuery.Promise} Promise which resolves when feedback is submitted or is rejected when back is chosen
 */
mw.editcheck.EditCheckActionWidget.prototype.showFeedback = function ( data ) {
	const deferred = this.feedbackDeferred = ve.createDeferred();
	if ( this.suggestion ) {
		// Suggestions bypass feedback surveys
		return deferred.resolve().promise();
	}

	const form = new OO.ui.FieldsetLayout( {
		classes: [ 've-ui-editCheckActionWidget-feedback' ]
	} );
	const answerRadioSelect = new OO.ui.RadioSelectWidget( {
		items: data.choices.map( ( choice ) => new OO.ui.RadioOptionWidget( choice ) )
	} );
	const submit = new OO.ui.ButtonInputWidget( {
		label: ve.msg( 'editcheck-dialog-action-submit' ),
		flags: [ 'progressive', 'primary' ],
		disabled: true
	} );
	const back = new OO.ui.ButtonInputWidget( {
		label: ve.msg( 'editcheck-dialog-action-back' ),
		flags: [ 'safe', 'back' ],
		icon: 'previous'
	} );
	answerRadioSelect.on( 'select', () => {
		submit.setDisabled( !answerRadioSelect.findSelectedItem() );
	} );
	form.addItems( [
		new OO.ui.FieldLayout( answerRadioSelect, {
			label: data.description || ve.msg( 'editcheck-reject-description' ),
			align: 'top'
		} ),
		new OO.ui.HorizontalLayout( {
			items: [
				new OO.ui.FieldLayout( back ),
				new OO.ui.FieldLayout( submit )
			]
		} )
	] );
	submit.on( 'click', () => {
		const selectedItem = answerRadioSelect.findSelectedItem();
		const reason = selectedItem && selectedItem.getData();
		if ( reason ) {
			deferred.resolve( reason );
			ve.track( 'activity.editCheck-' + this.name, { action: 'edit-check-feedback-reason-' + reason } );
		}
	} );
	back.on( 'click', () => {
		deferred.reject();
	} );

	this.$body.prepend( form.$element );

	ve.track( 'activity.editCheck-' + this.name, { action: 'edit-check-feedback-shown' } );
	return deferred.promise().always( () => {
		// HACK: This causes the answerRadioSelect.onDocumentKeyDownHandler to be unbound
		// Otherwise, it'll swallow certain key events (arrow keys, enter, pagedown/up) forever.
		answerRadioSelect.$element.trigger( 'blur' );
		form.$element.remove();
		this.feedbackDeferred = null;
	} );
};
