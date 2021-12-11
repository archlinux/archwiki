/**
 * Creates a AutosizeTextInputWidget object.
 * Used to allow autosizable text input to handle bigger content in the template data editor.
 *
 * @class
 * @extends OO.ui.MultilineTextInputWidget
 *
 * @constructor
 * @param {Object} config
 */
function AutosizeTextInputWidget( config ) {
	config.autosize = true;
	config.rows = 1;

	// Parent constructor
	AutosizeTextInputWidget.super.call( this, config );
}

/* Inheritance */

OO.inheritClass( AutosizeTextInputWidget, OO.ui.MultilineTextInputWidget );

/* Methods */

/**
 * @inheritdoc
 */
AutosizeTextInputWidget.prototype.onKeyPress = function ( e ) {
	if ( e.which === OO.ui.Keys.ENTER ) {
		// block adding of newlines
		e.preventDefault();
	}
	OO.ui.MultilineTextInputWidget.prototype.onKeyPress.call( this, e );
};

module.exports = AutosizeTextInputWidget;
