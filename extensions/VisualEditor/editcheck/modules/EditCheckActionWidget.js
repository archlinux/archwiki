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
 * @param {string} [config.mode] Mode for the action set widget
 * @param {boolean} [config.suggestion] This is a suggestion
 */
mw.editcheck.EditCheckActionWidget = function MWEditCheckActionWidget( config ) {
	this.singleAction = config.singleAction;
	this.mode = config.mode || '';
	this.suggestion = config.suggestion;

	this.name = config.name;

	this.actions = new OO.ui.ActionSet();
	this.actions.connect( this, {
		change: 'onActionsChange'
	} );

	mw.editcheck.EditCheckActionWidget.super.call( this, config );

	this.feedbackDeferred = null;

	this.collapsed = false;
	this.message = new OO.ui.LabelWidget( { label: config.message } );
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

	this.$element.on( 'click', this.onClick.bind( this ) );

	this.$body = $( '<div>' )
		.append( this.message.$element, this.$actions )
		.addClass( 've-ui-editCheckActionWidget-body' );

	if ( this.footer ) {
		this.$body.append( this.footer.$element );
	}

	if ( this.suggestion ) {
		this.$element.addClass( 've-ui-editCheckActionWidget-suggestion' );
	}

	this.$element
		.append( this.$body )
		.addClass( 've-ui-editCheckActionWidget' );
};

/* Inheritance */

OO.inheritClass( mw.editcheck.EditCheckActionWidget, OO.ui.MessageWidget );

/* Events */

/**
 * Fired when the user toggles the collapsed state of the widget.
 *
 * @event mw.editcheck.EditCheckActionWidget#togglecollapse
 */

/* Methods */

/**
 * Handle change events on the action set
 */
mw.editcheck.EditCheckActionWidget.prototype.onActionsChange = function () {
	this.$actions.addClass( 'oo-ui-element-hidden' ).find( '.oo-ui-actionWidget' ).remove();
	this.actions.get( { modes: [ this.mode ] } ).forEach( ( actionWidget ) => {
		this.$actions.append( actionWidget.$element ).removeClass( 'oo-ui-element-hidden' );
	} );
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
 * Toggle the collapsed state of the widget
 *
 * @param {boolean} [collapsed] The new collapsed state, toggles if unset
 */
mw.editcheck.EditCheckActionWidget.prototype.toggleCollapse = function ( collapsed ) {
	const previousState = this.collapsed;
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
		answerRadioSelect.$element.blur();
		form.$element.remove();
		this.feedbackDeferred = null;
	} );
};
