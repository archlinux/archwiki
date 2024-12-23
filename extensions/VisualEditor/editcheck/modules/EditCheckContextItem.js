/*!
 * VisualEditor EditCheckContextItem class.
 *
 * @copyright See AUTHORS.txt
 */

/**
 * Context item shown after a rich text paste.
 *
 * @class
 * @extends ve.ui.PersistentContextItem
 *
 * @constructor
 * @param {ve.ui.LinearContext} context Context the item is in
 * @param {ve.dm.Model} model Model the item is related to
 * @param {Object} [config]
 */
ve.ui.EditCheckContextItem = function VeUiEditCheckContextItem() {
	// Parent constructor
	ve.ui.EditCheckContextItem.super.apply( this, arguments );

	// Initialization
	this.$element.addClass( 've-ui-editCheckContextItem' );
};

/* Inheritance */

OO.inheritClass( ve.ui.EditCheckContextItem, ve.ui.PersistentContextItem );

/* Static Properties */

ve.ui.EditCheckContextItem.static.name = 'editCheckReferences';

ve.ui.EditCheckContextItem.static.icon = 'quotes';

ve.ui.EditCheckContextItem.static.label = OO.ui.deferMsg( 'editcheck-dialog-addref-title' );

/* Methods */

/**
 * @inheritdoc
 */
ve.ui.EditCheckContextItem.prototype.renderBody = function () {
	const $actions = $( '<div>' ).addClass( 've-ui-editCheckContextItem-actions' );

	this.data.action.getChoices().forEach( ( choice ) => {
		const button = new OO.ui.ButtonWidget( choice );
		button.connect( this, {
			click: () => {
				this.onChoiceClick( choice.action );
			}
		} );
		$actions.append( button.$element );
	} );

	// HACK: Suppress close button on mobile context
	if ( this.context.isMobile() ) {
		this.context.closeButton.toggle( false );
	}

	this.$body.append(
		$( '<p>' ).text( this.data.action.getDescription() ),
		$actions
	);
};

ve.ui.EditCheckContextItem.prototype.close = function ( data ) {
	// HACK: Un-suppress close button on mobile context
	if ( this.context.isMobile() ) {
		this.context.closeButton.toggle( true );
	}
	this.data.callback( data, this.data );
};

ve.ui.EditCheckContextItem.prototype.onChoiceClick = function ( choice ) {
	this.data.action.check.act( choice, this.data.action, this );
};

/* Registration */

ve.ui.contextItemFactory.register( ve.ui.EditCheckContextItem );
