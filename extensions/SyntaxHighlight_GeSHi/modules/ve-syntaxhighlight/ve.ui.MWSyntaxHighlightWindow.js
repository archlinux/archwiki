/*!
 * VisualEditor UserInterface MWSyntaxHighlightWindow class.
 *
 * @copyright 2011-2015 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * MediaWiki syntax highlight window.
 *
 * @class
 * @abstract
 *
 * @constructor
 */
ve.ui.MWSyntaxHighlightWindow = function VeUiMWSyntaxHighlightWindow() {
};

/* Inheritance */

OO.initClass( ve.ui.MWSyntaxHighlightWindow );

/* Static properties */

ve.ui.MWSyntaxHighlightWindow.static.icon = 'alienextension';

ve.ui.MWSyntaxHighlightWindow.static.title = OO.ui.deferMsg( 'syntaxhighlight-visualeditor-mwsyntaxhighlightinspector-title' );

ve.ui.MWSyntaxHighlightWindow.static.dir = 'ltr';

/* Methods */

/**
 * @inheritdoc
 */
ve.ui.MWSyntaxHighlightWindow.prototype.initialize = function () {
	var noneMsg = ve.msg( 'syntaxhighlight-visualeditor-mwsyntaxhighlightinspector-none' );

	this.language = new OO.ui.ComboBoxInputWidget( {
		menu: {
			filterFromInput: true,
			items: $.map( ve.dm.MWSyntaxHighlightNode.static.getLanguages(), function ( lang ) {
				return new OO.ui.MenuOptionWidget( { data: lang, label: lang || noneMsg } );
			} )
		},
		validate: function ( input ) {
			return ve.dm.MWSyntaxHighlightNode.static.isLanguageSupported( input );
		}
	} );
	this.language.connect( this, { change: 'onLanguageInputChange' } );

	this.showLinesCheckbox = new OO.ui.CheckboxInputWidget();

	this.languageField = new OO.ui.FieldLayout( this.language, {
		classes: [ 've-ui-mwSyntaxHighlightWindow-languageField' ],
		align: 'top',
		label: ve.msg( 'syntaxhighlight-visualeditor-mwsyntaxhighlightinspector-language' )
	} );
	this.codeField = new OO.ui.FieldLayout( this.input, {
		align: 'top',
		label: ve.msg( 'syntaxhighlight-visualeditor-mwsyntaxhighlightinspector-code' )
	} );
	this.showLinesField = new OO.ui.FieldLayout( this.showLinesCheckbox, {
		align: 'inline',
		label: ve.msg( 'syntaxhighlight-visualeditor-mwsyntaxhighlightinspector-showlines' )
	} );
};

/**
 * Handle input change events
 *
 * @param {string} value New value
 */
ve.ui.MWSyntaxHighlightWindow.prototype.onLanguageInputChange = function () {
	var validity, inspector = this;
	validity = this.language.getValidity();
	validity.always( function () {
		inspector.getActions().setAbilities( { done: validity.state() === 'resolved' } );
	} );
};

/**
 * @inheritdoc OO.ui.Window
 */
ve.ui.MWSyntaxHighlightWindow.prototype.getReadyProcess = function ( data, process ) {
	return process.next( function () {
		this.language.getMenu().toggle( false );
		if ( !this.language.getValue() ) {
			this.language.focus();
		} else {
			this.input.focus();
		}
	}, this );
};

/**
 * @inheritdoc OO.ui.Window
 */
ve.ui.MWSyntaxHighlightWindow.prototype.getSetupProcess = function ( data, process ) {
	return process.next( function () {
		var attrs = this.selectedNode ? this.selectedNode.getAttribute( 'mw' ).attrs : {},
			language = attrs.lang || '',
			showLines = attrs.line !== undefined;

		this.language.setValue( language );

		this.showLinesCheckbox.setSelected( showLines );
	}, this );
};

/**
 * @inheritdoc OO.ui.Window
 */
ve.ui.MWSyntaxHighlightWindow.prototype.getTeardownProcess = function ( data, process ) {
	return process;
};

/**
 * @inheritdoc ve.ui.MWExtensionWindow
 */
ve.ui.MWSyntaxHighlightWindow.prototype.updateMwData = function ( mwData ) {
	var language, showLines;

	language = this.language.getValue();
	showLines = this.showLinesCheckbox.isSelected();

	mwData.attrs.lang = language || undefined;
	mwData.attrs.line = showLines ? '1' : undefined;
};
