/*!
 * VisualEditor user interface MWLatexDialog class.
 *
 * @copyright 2015 VisualEditor Team and others; see AUTHORS.txt
 * @license MIT
 */

/**
 * Abstract dialog for inserting and editing different formulas
 * provided by the Math extension.
 *
 * @abstract
 * @class
 * @extends ve.ui.MWExtensionPreviewDialog
 *
 * @constructor
 * @param {Object} [config] Configuration options
 */
ve.ui.MWLatexDialog = function VeUiMWLatexDialog( config ) {
	// Parent constructor
	ve.ui.MWLatexDialog.super.call( this, config );
};

/* Inheritance */

OO.inheritClass( ve.ui.MWLatexDialog, ve.ui.MWExtensionPreviewDialog );

/* Static properties */

ve.ui.MWLatexDialog.static.size = 'larger';

ve.ui.MWLatexDialog.static.dir = 'ltr';

ve.ui.MWLatexDialog.static.symbols = null;

ve.ui.MWLatexDialog.static.symbolsModule = null;

/* static methods */

/**
 * Set the symbols property
 *
 * @param {Object} symbols The symbols and their group names
 */
ve.ui.MWLatexDialog.static.setSymbols = function ( symbols ) {
	this.symbols = symbols;
};

/* Methods */

/**
 * @inheritdoc
 */
ve.ui.MWLatexDialog.prototype.initialize = function () {
	var dialog = this;

	// Parent method
	ve.ui.MWLatexDialog.super.prototype.initialize.call( this );

	// Layout for the formula inserter (formula tab panel) and options form (options tab panel)
	this.indexLayout = new OO.ui.IndexLayout();

	var formulaTabPanel = new OO.ui.TabPanelLayout( 'formula', {
		label: ve.msg( 'math-visualeditor-mwlatexdialog-card-formula' ),
		padded: true
	} );
	var optionsTabPanel = new OO.ui.TabPanelLayout( 'options', {
		label: ve.msg( 'math-visualeditor-mwlatexdialog-card-options' ),
		padded: true
	} );

	this.indexLayout.addTabPanels( [
		formulaTabPanel,
		optionsTabPanel
	] );

	// Layout for symbol picker (menu) and input and preview (content)
	this.menuLayout = new OO.ui.MenuLayout( {
		menuPosition: 'bottom',
		classes: [ 've-ui-mwLatexDialog-menuLayout' ]
	} );

	this.previewElement.$element.addClass(
		've-ui-mwLatexDialog-preview'
	);

	this.input = new ve.ui.MWAceEditorWidget( {
		rows: 1, // This will be recalculated later in onWindowManagerResize
		autocomplete: 'live',
		autocompleteWordList: this.constructor.static.autocompleteWordList
	} ).setLanguage( 'latex' );

	this.input.togglePrintMargin( false );

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

	var formulaPanel = new OO.ui.PanelLayout( {
		scrollable: true,
		padded: true
	} );

	// Layout for the symbol picker
	this.bookletLayout = new OO.ui.BookletLayout( {
		classes: [ 've-ui-mwLatexDialog-symbols' ],
		menuPosition: 'before',
		outlined: true,
		continuous: true
	} );
	this.pages = [];
	this.symbolsPromise = mw.loader.using( this.constructor.static.symbolsModule ).done( function () {
		var symbols = dialog.constructor.static.symbols;
		for ( var category in symbols ) {
			dialog.pages.push(
				new ve.ui.MWLatexPage(
					// eslint-disable-next-line mediawiki/msg-doc
					ve.msg( category ),
					{
						// eslint-disable-next-line mediawiki/msg-doc
						label: ve.msg( category ),
						symbols: symbols[ category ]
					}
				)
			);
		}
		dialog.bookletLayout.addPages( dialog.pages );
		dialog.bookletLayout.$element.on(
			'click',
			'.ve-ui-mwLatexPage-symbol',
			dialog.onListClick.bind( dialog )
		);

		// Append everything
		formulaPanel.$element.append(
			dialog.previewElement.$element,
			inputField.$element
		);
		dialog.menuLayout.setMenuPanel( dialog.bookletLayout );
		dialog.menuLayout.setContentPanel( formulaPanel );

		formulaTabPanel.$element.append(
			dialog.menuLayout.$element
		);
		optionsTabPanel.$element.append(
			displayField.$element,
			idField.$element
		);

		dialog.$body
			.addClass( 've-ui-mwLatexDialog-content' )
			.append( dialog.indexLayout.$element );
	} );

};

/**
 * @inheritdoc
 */
ve.ui.MWLatexDialog.prototype.getSetupProcess = function ( data ) {
	return ve.ui.MWLatexDialog.super.prototype.getSetupProcess.call( this, data )
		.next( function () {
			var attributes = this.selectedNode && this.selectedNode.getAttribute( 'mw' ).attrs,
				display = attributes && attributes.display || 'default',
				id = attributes && attributes.id || '',
				isReadOnly = this.isReadOnly();

			// Populate form
			// TODO: This widget is not readable when disabled
			this.displaySelect.selectItemByData( display ).setDisabled( isReadOnly );
			this.idInput.setValue( id ).setReadOnly( isReadOnly );

			// Add event handlers
			this.input.on( 'change', this.onChangeHandler );
			this.displaySelect.on( 'choose', this.onChangeHandler );
			this.idInput.on( 'change', this.onChangeHandler );
		}, this );
};

/**
 * @inheritdoc
 */
ve.ui.MWLatexDialog.prototype.getReadyProcess = function ( data ) {
	return ve.ui.MWLatexDialog.super.prototype.getReadyProcess.call( this, data )
		.next( function () {
			return this.symbolsPromise;
		}, this )
		.next( function () {
			// Resize the input once the dialog has been appended
			this.input.adjustSize( true ).focus().moveCursorToEnd();
			this.getManager().connect( this, { resize: 'onWindowManagerResize' } );
			this.onWindowManagerResize();
		}, this );
};

/**
 * @inheritdoc
 */
ve.ui.MWLatexDialog.prototype.getTeardownProcess = function ( data ) {
	return ve.ui.MWLatexDialog.super.prototype.getTeardownProcess.call( this, data )
		.first( function () {
			this.input.off( 'change', this.onChangeHandler );
			this.displaySelect.off( 'choose', this.onChangeHandler );
			this.idInput.off( 'change', this.onChangeHandler );
			this.getManager().disconnect( this );
			this.indexLayout.setTabPanel( 'formula' );
			this.indexLayout.resetScroll();
			this.menuLayout.resetScroll();
			this.bookletLayout.resetScroll();
		}, this );
};

/**
 * @inheritdoc
 */
ve.ui.MWLatexDialog.prototype.updateMwData = function ( mwData ) {
	// Parent method
	ve.ui.MWLatexDialog.super.prototype.updateMwData.call( this, mwData );

	// Get data from dialog
	var display = this.displaySelect.findSelectedItem().getData();
	var id = this.idInput.getValue();

	// Update attributes
	mwData.attrs.display = display !== 'default' ? display : undefined;
	mwData.attrs.id = id || undefined;
};

/**
 * @inheritdoc
 */
ve.ui.MWLatexDialog.prototype.getBodyHeight = function () {
	return 600;
};

/**
 * Handle the window resize event
 */
ve.ui.MWLatexDialog.prototype.onWindowManagerResize = function () {
	var dialog = this;
	this.input.loadingPromise.always( function () {
		// Toggle short mode as necessary
		// NB a change of mode triggers a transition...
		dialog.menuLayout.$element.toggleClass(
			've-ui-mwLatexDialog-menuLayout-short', dialog.menuLayout.$element.height() < 450
		);

		// ...So wait for the possible menuLayout transition to finish
		setTimeout( function () {
			// Give the input the right number of rows to fit the space
			var availableSpace = dialog.menuLayout.$content.height() - dialog.input.$element.position().top;
			// TODO: Compute this line height from the skin
			var singleLineHeight = 21;
			var border = 1;
			var padding = 3;
			var borderAndPadding = 2 * ( border + padding );
			var maxInputHeight = availableSpace - borderAndPadding;
			var minRows = Math.floor( maxInputHeight / singleLineHeight );
			dialog.input.loadingPromise.done( function () {
				dialog.input.setMinRows( minRows );
			} ).fail( function () {
				dialog.input.$input.attr( 'rows', minRows );
			} );
		}, OO.ui.theme.getDialogTransitionDuration() );
	} );
};

/**
 * Handle the click event on the list
 *
 * @param {jQuery.Event} e Mouse click event
 */
ve.ui.MWLatexDialog.prototype.onListClick = function ( e ) {
	if ( this.isReadOnly() ) {
		return;
	}

	var symbol = $( e.target ).data( 'symbol' ),
		encapsulate = symbol.encapsulate;

	if ( encapsulate ) {
		var range = this.input.getRange();
		if ( range.from === range.to ) {
			this.input.insertContent( encapsulate.placeholder );
			this.input.selectRange( range.from, range.from + encapsulate.placeholder.length );
		}
		this.input.encapsulateContent( encapsulate.pre, encapsulate.post );
	} else {
		var insert = symbol.insert;
		this.input.insertContent( insert );
	}
};
