/*!
 * VisualEditor UserInterface MWMoreParametersResultWidget class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * Creates an ve.ui.MWMoreParametersResultWidget object.
 *
 * @class
 * @extends OO.ui.DecoratedOptionWidget
 *
 * @constructor
 * @param {Object} config
 * @cfg {Object} data
 * @cfg {number} data.remainder Number of remaining items currently not shown
 */
ve.ui.MWMoreParametersResultWidget = function VeUiMWMoreParametersResultWidget( config ) {
	// Configuration initialization
	config = ve.extendObject( { icon: 'parameter-set' }, config );

	// Parent constructor
	ve.ui.MWMoreParametersResultWidget.super.call( this, config );

	// Initialization
	this.$element.addClass( 've-ui-mwMoreParametersResultWidget' );
	this.setLabel( this.buildLabel() );
};

/* Inheritance */

OO.inheritClass( ve.ui.MWMoreParametersResultWidget, OO.ui.DecoratedOptionWidget );

/* Methods */

/**
 * Build the label element
 *
 * @private
 * @return {jQuery}
 */
ve.ui.MWMoreParametersResultWidget.prototype.buildLabel = function () {
	return $( '<div>' )
		.addClass( 've-ui-mwMoreParametersResultWidget-label' )
		.text( ve.msg( 'visualeditor-parameter-search-more', this.data.remainder ) );
};
