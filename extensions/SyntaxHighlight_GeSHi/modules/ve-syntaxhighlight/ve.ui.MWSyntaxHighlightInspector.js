/*!
 * VisualEditor UserInterface MWSyntaxHighlightInspector class.
 *
 * @copyright 2011-2015 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * MediaWiki syntax highlight inspector.
 *
 * @class
 * @extends ve.ui.MWLiveExtensionInspector
 *
 * @constructor
 * @param {Object} [config] Configuration options
 */
ve.ui.MWSyntaxHighlightInspector = function VeUiMWSyntaxHighlightInspector() {
	// Parent constructor
	ve.ui.MWSyntaxHighlightInspector.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( ve.ui.MWSyntaxHighlightInspector, ve.ui.MWLiveExtensionInspector );

/* Static properties */

ve.ui.MWSyntaxHighlightInspector.static.name = 'syntaxhighlight';

ve.ui.MWSyntaxHighlightInspector.static.icon = 'alienextension';

ve.ui.MWSyntaxHighlightInspector.static.size = 'large';

ve.ui.MWSyntaxHighlightInspector.static.title = OO.ui.deferMsg( 'syntaxhighlight-visualeditor-mwsyntaxhighlightinspector-title' );

ve.ui.MWSyntaxHighlightInspector.static.modelClasses = [ ve.dm.MWBlockSyntaxHighlightNode, ve.dm.MWInlineSyntaxHighlightNode ];

ve.ui.MWSyntaxHighlightInspector.static.dir = 'ltr';

/* Methods */

/**
 * @inheritdoc
 */
ve.ui.MWSyntaxHighlightInspector.prototype.initialize = function () {
	var languageField, codeField, showLinesField,
		noneMsg = ve.msg( 'syntaxhighlight-visualeditor-mwsyntaxhighlightinspector-none' );
	// Parent method
	ve.ui.MWSyntaxHighlightInspector.super.prototype.initialize.call( this );

	this.language = new OO.ui.ComboBoxWidget( {
		menu: {
			filterFromInput: true,
			items: $.map( ve.dm.MWSyntaxHighlightNode.static.getLanguages(), function ( lang ) {
				return new OO.ui.MenuOptionWidget( { data: lang, label: lang || noneMsg } );
			} )
		},
		input: { validate: function ( input ) {
			return ve.dm.MWSyntaxHighlightNode.static.isLanguageSupported( input );
		} }
	} );
	this.language.getInput().connect( this, { change: 'onLanguageInputChange' } );

	this.showLinesCheckbox = new OO.ui.CheckboxInputWidget();

	languageField = new OO.ui.FieldLayout( this.language, {
		align: 'top',
		label: ve.msg( 'syntaxhighlight-visualeditor-mwsyntaxhighlightinspector-language' )
	} );
	codeField = new OO.ui.FieldLayout( this.input, {
		align: 'top',
		label: ve.msg( 'syntaxhighlight-visualeditor-mwsyntaxhighlightinspector-code' )
	} );
	showLinesField = new OO.ui.FieldLayout( this.showLinesCheckbox, {
		align: 'inline',
		label: ve.msg( 'syntaxhighlight-visualeditor-mwsyntaxhighlightinspector-showlines' )
	} );

	// Initialization
	this.$content.addClass( 've-ui-mwSyntaxHighlightInspector-content' );
	this.form.$element.prepend(
		languageField.$element,
		codeField.$element,
		showLinesField.$element
	);
};

/**
 * Handle input change events
 *
 * @param {string} value New value
 */
ve.ui.MWSyntaxHighlightInspector.prototype.onLanguageInputChange = function () {
	var inspector = this;
	this.language.getInput().isValid().done( function ( valid ) {
		inspector.getActions().setAbilities( { done: valid } );
	} );
};

/**
 * @inheritdoc
 */
ve.ui.MWSyntaxHighlightInspector.prototype.getReadyProcess = function ( data ) {
	return ve.ui.MWSyntaxHighlightInspector.super.prototype.getReadyProcess.call( this, data )
		.next( function () {
			if ( this.language.input.getValue() ) {
				this.input.focus();
			} else {
				this.language.getMenu().toggle( true );
			}
		}, this );
};

/**
 * @inheritdoc
 */
ve.ui.MWSyntaxHighlightInspector.prototype.getSetupProcess = function ( data ) {
	return ve.ui.MWSyntaxHighlightInspector.super.prototype.getSetupProcess.call( this, data )
		.next( function () {
			var attrs = this.selectedNode.getAttribute( 'mw' ).attrs,
				language = attrs.lang || '',
				showLines = attrs.line !== undefined;

			if ( ve.dm.MWSyntaxHighlightNode.static.isLanguageSupported( language ) ) {
				this.language.input.setValue( language );
			}
			this.language.input.on( 'change', this.onChangeHandler );

			this.showLinesCheckbox.setSelected( showLines );
			this.showLinesCheckbox.on( 'change', this.onChangeHandler );
		}, this );
};

/**
 * @inheritdoc
 */
ve.ui.MWSyntaxHighlightInspector.prototype.getTeardownProcess = function ( data ) {
	return ve.ui.MWSyntaxHighlightInspector.super.prototype.getTeardownProcess.call( this, data )
		.first( function () {
			this.language.input.off( 'change', this.onChangeHandler );
			this.showLinesCheckbox.off( 'change', this.onChangeHandler );
		}, this );
};

/**
 * @inheritdoc
 */
ve.ui.MWSyntaxHighlightInspector.prototype.updateMwData = function ( mwData ) {
	var language, showLines;

	// Parent method
	ve.ui.MWSyntaxHighlightInspector.super.prototype.updateMwData.call( this, mwData );

	language = this.language.input.getValue();
	showLines = this.showLinesCheckbox.isSelected();

	mwData.attrs.lang = language || undefined;
	mwData.attrs.line = showLines ? '1' : undefined;
};

/* Registration */

ve.ui.windowFactory.register( ve.ui.MWSyntaxHighlightInspector );
