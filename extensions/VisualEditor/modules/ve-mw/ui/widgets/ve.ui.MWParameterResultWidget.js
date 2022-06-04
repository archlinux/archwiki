/*!
 * VisualEditor UserInterface MWParameterResultWidget class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * Creates an ve.ui.MWParameterResultWidget object.
 *
 * @class
 * @extends OO.ui.DecoratedOptionWidget
 *
 * @constructor
 * @param {Object} config
 * @cfg {Object} data
 * @cfg {string} [data.name] Parameter name
 * @cfg {string[]} [data.aliases]
 * @cfg {string} data.label
 * @cfg {string} [data.description='']
 * @cfg {boolean} [data.isUnknown=false] If the parameter is unknown, i.e. not documented via
 *  TemplateData
 */
ve.ui.MWParameterResultWidget = function VeUiMWParameterResultWidget( config ) {
	// Configuration initialization
	config = ve.extendObject( { icon: 'parameter' }, config );

	// Parent constructor
	ve.ui.MWParameterResultWidget.super.call( this, config );

	// Initialization
	this.$element.addClass( 've-ui-mwParameterResultWidget' );
	this.setLabel( this.buildLabel() );
};

/* Inheritance */

OO.inheritClass( ve.ui.MWParameterResultWidget, OO.ui.DecoratedOptionWidget );

/* Methods */

/**
 * Build the label element
 *
 * @private
 * @return {jQuery}
 */
ve.ui.MWParameterResultWidget.prototype.buildLabel = function () {
	var $label = $( '<div>' )
			.addClass( 've-ui-mwParameterResultWidget-label' )
			.text( this.data.label ),
		$names = $( '<div>' )
			.addClass( 've-ui-mwParameterResultWidget-names' ),
		$description = $( '<div>' )
			.addClass( 've-ui-mwParameterResultWidget-description' )
			.text( this.data.description || '' );

	if ( this.data.isUnknown ) {
		$description.addClass( 've-ui-mwParameterResultWidget-unknown' )
			.text( ve.msg( 'visualeditor-parameter-search-unknown' ) );
	}

	if ( this.data.name && this.data.name !== this.data.label ) {
		$names.append(
			$( '<span>' )
				.addClass( 've-ui-mwParameterResultWidget-name' )
				.text( this.data.name )
		);
	}
	for ( var i = 0; i < this.data.aliases.length; i++ ) {
		if ( this.data.aliases[ i ] === this.data.label ) {
			continue;
		}
		$names.append(
			$( '<span>' )
				.addClass( 've-ui-mwParameterResultWidget-name ve-ui-mwParameterResultWidget-alias' )
				.text( this.data.aliases[ i ] )
		);
	}

	return $label.add( $names ).add( $description );
};
