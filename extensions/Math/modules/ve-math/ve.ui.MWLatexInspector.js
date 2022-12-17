/*!
 * VisualEditor UserInterface MWLatexInspector class.
 *
 * @copyright 2011-2015 VisualEditor Team and others; see AUTHORS.txt
 * @license MIT
 */

/**
 * MediaWiki abstract inspector for quick editing of different formulas
 * that the Math extension provides.
 *
 * @abstract
 * @class
 * @extends ve.ui.MWLiveExtensionInspector
 *
 * @constructor
 * @param {Object} [config] Configuration options
 */
ve.ui.MWLatexInspector = function VeUiMWLatexInspector( config ) {
	// Parent constructor
	ve.ui.MWLatexInspector.super.call( this, config );
};

/* Inheritance */

OO.inheritClass( ve.ui.MWLatexInspector, ve.ui.MWLiveExtensionInspector );

/* Static properties */

ve.ui.MWLatexInspector.static.dir = 'ltr';

/* Methods */

/**
 * @inheritdoc
 */
ve.ui.MWLatexInspector.prototype.initialize = function () {
	// Parent method
	ve.ui.MWLatexInspector.super.prototype.initialize.call( this );

	this.displaySelect = new OO.ui.ButtonSelectWidget( {
		items: [
			new OO.ui.ButtonOptionWidget( {
				data: 'default',
				icon: 'mathematicsDisplayDefault',
				label: ve.msg( 'math-visualeditor-mwlatexinspector-display-default' )
			} ),
			new OO.ui.ButtonOptionWidget( {
				data: 'inline',
				icon: 'mathematicsDisplayInline',
				label: ve.msg( 'math-visualeditor-mwlatexinspector-display-inline' )
			} ),
			new OO.ui.ButtonOptionWidget( {
				data: 'block',
				icon: 'mathematicsDisplayBlock',
				label: ve.msg( 'math-visualeditor-mwlatexinspector-display-block' )
			} )
		]
	} );

	this.idInput = new OO.ui.TextInputWidget();
	this.qidInput = new mw.widgets.MathWbEntitySelector();

	var inputField = new OO.ui.FieldLayout( this.input, {
		align: 'top',
		label: ve.msg( 'math-visualeditor-mwlatexdialog-card-formula' )
	} );
	var displayField = new OO.ui.FieldLayout( this.displaySelect, {
		align: 'top',
		label: ve.msg( 'math-visualeditor-mwlatexinspector-display' )
	} );
	var idField = new OO.ui.FieldLayout( this.idInput, {
		align: 'top',
		label: ve.msg( 'math-visualeditor-mwlatexinspector-id' )
	} );
	var qidField = new OO.ui.FieldLayout( this.qidInput, {
		align: 'top',
		label: ve.msg( 'math-visualeditor-mwlatexinspector-qid' )
	} );

	// Initialization
	this.$content.addClass( 've-ui-mwLatexInspector-content' );
	this.form.$element.append(
		inputField.$element,
		this.generatedContentsError.$element,
		displayField.$element,
		idField.$element,
		qidField.$element
	);
};

/**
 * @inheritdoc
 */
ve.ui.MWLatexInspector.prototype.getSetupProcess = function ( data ) {
	return ve.ui.MWLatexInspector.super.prototype.getSetupProcess.call( this, data )
		.next( function () {
			var display = this.selectedNode.getAttribute( 'mw' ).attrs.display || 'default';
			var attributes = this.selectedNode && this.selectedNode.getAttribute( 'mw' ).attrs,
				id = attributes && attributes.id || '',
				qid = attributes && attributes.qid || '',
				isReadOnly = this.isReadOnly();

			// Populate form
			// TODO: This widget is not readable when disabled
			this.idInput.setValue( id ).setReadOnly( isReadOnly );
			this.qidInput.setValue( qid ).setReadOnly( isReadOnly );

			// Add event handlers
			this.input.on( 'change', this.onChangeHandler );
			this.displaySelect.on( 'choose', this.onChangeHandler );
			this.idInput.on( 'change', this.onChangeHandler );
			this.qidInput.on( 'change', this.onChangeHandler );
			this.displaySelect.selectItemByData( display );
			this.displaySelect.on( 'choose', this.onChangeHandler );
		}, this );
};

/**
 * @inheritdoc
 */
ve.ui.MWLatexInspector.prototype.getTeardownProcess = function ( data ) {
	return ve.ui.MWLatexInspector.super.prototype.getTeardownProcess.call( this, data )
		.first( function () {
			this.displaySelect.off( 'choose', this.onChangeHandler );
		}, this );
};

/**
 * @inheritdoc
 */
ve.ui.MWLatexInspector.prototype.updateMwData = function ( mwData ) {
	// Parent method
	ve.ui.MWLatexInspector.super.prototype.updateMwData.call( this, mwData );

	var display = this.displaySelect.findSelectedItem().getData();
	var id = this.idInput.getValue();
	var qid = this.qidInput.getValue();

	mwData.attrs.display = display !== 'default' ? display : undefined;
	mwData.attrs.id = id || undefined;
	mwData.attrs.qid = qid || undefined;
};

/**
 * @inheritdoc
 */
ve.ui.MWLatexInspector.prototype.formatGeneratedContentsError = function ( $element ) {
	return $element.text().trim();
};
