/**
 * Generic button-like widget for items in the template dialog sidebar. See
 * {@see OO.ui.ButtonWidget} for inspiration.
 *
 * @class
 * @extends OO.ui.OptionWidget
 *
 * @constructor
 * @param {Object} config
 * @cfg {string} [icon='']
 * @cfg {string} label
 */
ve.ui.MWTransclusionOutlineButtonWidget = function VeUiMWTransclusionOutlineButtonWidget( config ) {
	// Parent constructor
	ve.ui.MWTransclusionOutlineButtonWidget.super.call( this, ve.extendObject( config, {
		classes: [ 've-ui-mwTransclusionOutlineButtonWidget' ]
	} ) );

	// Mixin constructors
	OO.ui.mixin.ButtonElement.call( this, {
		framed: false
	} );
	OO.ui.mixin.IconElement.call( this, config );
	OO.ui.mixin.TabIndexedElement.call( this, ve.extendObject( {
		$tabIndexed: this.$button
	}, config ) );

	this.$element
		.append( this.$button.append( this.$icon, this.$label ) );
};

/* Inheritance */

OO.inheritClass( ve.ui.MWTransclusionOutlineButtonWidget, OO.ui.OptionWidget );
OO.mixinClass( ve.ui.MWTransclusionOutlineButtonWidget, OO.ui.mixin.ButtonElement );
OO.mixinClass( ve.ui.MWTransclusionOutlineButtonWidget, OO.ui.mixin.IconElement );
OO.mixinClass( ve.ui.MWTransclusionOutlineButtonWidget, OO.ui.mixin.TabIndexedElement );

ve.ui.MWTransclusionOutlineButtonWidget.static.highlightable = false;
ve.ui.MWTransclusionOutlineButtonWidget.static.pressable = false;

/* Events */

/**
 * @event spacePressed
 */

/**
 * @inheritDoc OO.ui.mixin.ButtonElement
 * @param {jQuery.Event} e Key press event
 * @fires spacePressed
 */
ve.ui.MWTransclusionOutlineButtonWidget.prototype.onKeyPress = function ( e ) {
	if ( e.which === OO.ui.Keys.SPACE ) {
		// We know we can only select another part, so don't even try to unselect this one
		if ( !this.isSelected() ) {
			this.emit( 'spacePressed' );
		}
		e.preventDefault();
		return;
	}

	return OO.ui.mixin.ButtonElement.prototype.onKeyPress.call( this, e );
};
