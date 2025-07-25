/**
 * TemplateData Param Widget
 *
 * @class
 * @extends OO.ui.DecoratedOptionWidget
 * @mixes OO.ui.mixin.DraggableElement
 *
 * @param {Object} config
 * @param {string} config.key
 * @param {string} [config.label=key]
 * @param {string[]} [config.aliases=[]]
 * @param {string} [config.description=""]
 */
function ParamWidget( config ) {
	// Parent constructor
	ParamWidget.super.call( this, { data: config.key, icon: 'menu' } );

	// Mixin constructors
	OO.ui.mixin.DraggableElement.call( this, $.extend( { $handle: this.$icon } ) );
	OO.ui.mixin.TabIndexedElement.call( this, { $tabIndexed: this.$element } );

	this.key = config.key;
	this.label = config.label;
	this.aliases = config.aliases || [];
	this.description = config.description;

	// Events
	this.$element.on( 'keydown', this.onKeyDown.bind( this ) );

	// Initialize
	this.$element.addClass( 'tdg-templateDataParamWidget' );
	this.buildParamLabel();
}

/* Inheritance */

OO.inheritClass( ParamWidget, OO.ui.DecoratedOptionWidget );

OO.mixinClass( ParamWidget, OO.ui.mixin.DraggableElement );
OO.mixinClass( ParamWidget, OO.ui.mixin.TabIndexedElement );

/* Events */

/**
 * @event choose
 * @param {ParamWidget} paramWidget
 */

/* Methods */

/**
 * @param {jQuery.Event} e Key down event
 * @fires choose
 */
ParamWidget.prototype.onKeyDown = function ( e ) {
	if ( e.which === OO.ui.Keys.ENTER ) {
		this.emit( 'choose', this );
	}
};

/**
 * Build the parameter label in the parameter select widget
 */
ParamWidget.prototype.buildParamLabel = function () {
	const keys = this.aliases.slice(),
		$paramLabel = $( '<div>' )
			.addClass( 'tdg-templateDataParamWidget-param-name' ),
		$aliases = $( '<div>' )
			.addClass( 'tdg-templateDataParamWidget-param-aliases' ),
		$description = $( '<div>' )
			.addClass( 'tdg-templateDataParamWidget-param-description' );

	keys.unshift( this.key );

	$paramLabel.text( this.label || this.key );
	$description.text( this.description );

	keys.forEach( ( key ) => {
		$aliases.append(
			$( '<span>' )
				.addClass( 'tdg-templateDataParamWidget-param-alias' )
				.text( key )
		);
	} );

	this.setLabel( $aliases.add( $paramLabel ).add( $description ) );
};

module.exports = ParamWidget;
