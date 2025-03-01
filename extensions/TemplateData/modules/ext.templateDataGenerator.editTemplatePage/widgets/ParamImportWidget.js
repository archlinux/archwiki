/**
 * TemplateData Param Import Widget
 *
 * @class
 * @extends OO.ui.ButtonWidget
 * @param {Object} [config]
 */
function ParamImportWidget( config ) {
	config = config || {};

	// Parent constructor
	ParamImportWidget.super.call( this, Object.assign( {
		icon: 'parameter-set'
	}, config ) );

	// Initialize
	this.$element.addClass( 'tdg-templateDataParamImportWidget' );
}

/* Inheritance */

OO.inheritClass( ParamImportWidget, OO.ui.ButtonWidget );

/**
 * Build the parameter label in the parameter select widget
 *
 * @param {string[]} params Param names
 */
ParamImportWidget.prototype.buildParamLabel = function ( params ) {
	const paramNames = params.slice( 0, 9 ).join( mw.msg( 'comma-separator' ) );
	this.setLabel( $( '<div>' )
		.addClass( 'tdg-templateDataParamWidget-param-name' )
		.text( mw.msg( 'templatedata-modal-table-param-importoption', params.length ) )
		.append( $( '<div>' )
			.addClass( 'tdg-templateDataParamWidget-param-description' )
			.text( mw.msg( 'templatedata-modal-table-param-importoption-subtitle', paramNames ) )
		) );
};

module.exports = ParamImportWidget;
