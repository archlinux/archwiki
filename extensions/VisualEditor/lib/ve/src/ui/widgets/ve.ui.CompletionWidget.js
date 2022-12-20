/*!
 * VisualEditor UserInterface CompletionWidget class.
 *
 * @copyright 2011-2019 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * Widget that displays autocompletion suggestions
 *
 * @class
 * @extends OO.ui.Widget
 *
 * @constructor
 * @param {ve.ui.Surface} surface Surface to complete into
 * @param {Object} [config] Configuration options
 * @cfg {Object} [validate] Validation pattern passed to TextInputWidgets
 * @cfg {boolean} [readOnly=false] Prevent changes to the value of the widget.
 */
ve.ui.CompletionWidget = function VeUiCompletionWidget( surface, config ) {
	this.surface = surface;
	this.surfaceModel = surface.getModel();

	// Configuration
	config = config || {
		anchor: false
	};

	// Parent constructor
	ve.ui.CompletionWidget.super.call( this, config );

	this.$tabIndexed = this.$element;

	var $doc = surface.getView().getDocument().getDocumentNode().$element;
	this.popup = new OO.ui.PopupWidget( {
		anchor: false,
		align: 'forwards',
		hideWhenOutOfView: false,
		autoFlip: false,
		width: null,
		$container: config.$popupContainer || this.surface.$element,
		containerPadding: config.popupPadding
	} );
	this.menu = new OO.ui.MenuSelectWidget( {
		widget: this,
		$input: $doc
	} );
	// This may be better semantically as a MenuSectionOptionWidget,
	// but that causes all subsequent options to be indented.
	this.header = new OO.ui.MenuOptionWidget( {
		classes: [ 've-ui-completionWidget-header' ],
		disabled: true
	} );

	// Events
	this.menu.connect( this, {
		choose: 'onMenuChoose',
		toggle: 'onMenuToggle'
	} );

	this.popup.$body.append( this.menu.$element );

	// Setup
	this.$element.addClass( 've-ui-completionWidget' )
		.append(
			this.popup.$element
		);
};

/* Inheritance */

OO.inheritClass( ve.ui.CompletionWidget, OO.ui.Widget );

ve.ui.CompletionWidget.prototype.setup = function ( action ) {
	var offset = this.surfaceModel.getSelection().getRange();
	if ( !offset.isCollapsed() ) {
		return;
	}
	this.action = action;
	this.initialOffset = offset.end - this.action.constructor.static.triggerLength;

	this.update();

	this.surfaceModel.connect( this, { select: 'onModelSelect' } );
};

ve.ui.CompletionWidget.prototype.teardown = function () {
	this.tearingDown = true;
	this.popup.toggle( false );
	this.surfaceModel.disconnect( this );
	this.action = undefined;
	this.tearingDown = false;
};

ve.ui.CompletionWidget.prototype.update = function () {
	var direction = this.surface.getDir(),
		range = this.getCompletionRange(),
		boundingRect = this.surface.getView().getSelection( new ve.dm.LinearSelection( range ) ).getSelectionBoundingRect(),
		style = {
			top: boundingRect.bottom
		},
		data = this.surfaceModel.getDocument().data,
		input = data.getText( false, range );

	if ( direction === 'rtl' ) {
		// This works because this.$element is a 0x0px box, with the menu positioned relative to it.
		// If this style was applied to the menu, we'd need to do some math here to align the right
		// edge of the menu with the right edge of the selection.
		style.left = boundingRect.right;
	} else {
		style.left = boundingRect.left;
	}
	this.$element.css( style );

	this.updateMenu( input );
	this.action.getSuggestions( input ).then( function ( suggestions ) {
		this.menu.clearItems();
		this.menu.addItems( suggestions.map( this.action.getMenuItemForSuggestion.bind( this.action ) ) );
		this.menu.highlightItem( this.menu.findFirstSelectableItem() );
		this.updateMenu( input, suggestions );
	}.bind( this ) );
};

ve.ui.CompletionWidget.prototype.updateMenu = function ( input, suggestions ) {
	// Update the header based on the input
	var label = this.action.getHeaderLabel( input, suggestions );
	if ( label !== undefined ) {
		this.header.setLabel( label );
	}
	if ( this.header.getLabel() !== null ) {
		this.menu.addItems( [ this.header ], 0 );
	} else {
		this.menu.removeItems( [ this.header ] );
	}
	// If there is a header or menu items, show the menu
	if ( this.menu.items.length ) {
		this.menu.toggle( true );
		this.popup.toggle( true );
		// Menu may have changed size, so recalculate position
		this.popup.updateDimensions();
	} else {
		this.popup.toggle( false );
	}
};

ve.ui.CompletionWidget.prototype.onMenuChoose = function ( item ) {
	var fragment = this.action.insertCompletion( item.getData(), this.getCompletionRange( true ) );

	fragment.collapseToEnd().select();

	this.teardown();
};

ve.ui.CompletionWidget.prototype.onMenuToggle = function ( visible ) {
	if ( !visible && !this.tearingDown ) {
		// Menu was hidden by the user (e.g. pressed ESC) - trigger a teardown
		this.teardown();
	}
};

ve.ui.CompletionWidget.prototype.onModelSelect = function () {
	var range = this.getCompletionRange();
	if ( !range || range.isBackwards() || this.action.shouldAbandon( this.surfaceModel.getDocument().data.getText( false, range ), this.menu.getItems().length ) ) {
		this.teardown();
	} else {
		this.update();
	}
};

ve.ui.CompletionWidget.prototype.getCompletionRange = function ( withTrigger ) {
	var range = this.surfaceModel.getSelection().getCoveringRange();
	if ( !range || !range.isCollapsed() || !this.action ) {
		return null;
	}
	return new ve.Range( this.initialOffset + ( withTrigger ? 0 : this.action.constructor.static.triggerLength ), range.end );
};
