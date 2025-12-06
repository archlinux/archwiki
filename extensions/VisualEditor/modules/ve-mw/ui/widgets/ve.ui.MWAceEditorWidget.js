/*!
 * VisualEditor UserInterface MWAceEditorWidget class.
 *
 * @copyright See AUTHORS.txt
 */

/* global ace */

/**
 * Text input widget which use an Ace editor instance when available
 *
 * For the most part this can be treated just like a TextInputWidget with
 * a few extra considerations:
 *
 * - For performance it is recommended to destroy the editor when
 *   you are finished with it, using #teardown. If you need to use
 *   the widget again let the editor can be restored with #setup.
 * - After setting an initial value the undo stack can be reset
 *   using clearUndoStack so that you can't undo past the initial
 *   state.
 *
 * @class
 * @extends ve.ui.WhitespacePreservingTextInputWidget
 *
 * @constructor
 * @param {Object} [config] Configuration options
 * @param {string} [config.autocomplete='none'] Symbolic name of autocomplete
 * mode: 'none', 'basic' (requires the user to press Ctrl-Space) or
 * 'live' (shows a list of suggestions as the user types)
 * @param {Array} [config.autocompleteWordList=null] List of words to
 * autocomplete to
 */
ve.ui.MWAceEditorWidget = function VeUiMWAceEditorWidget( config ) {
	this.autocomplete = config.autocomplete || 'none';
	this.autocompleteWordList = config.autocompleteWordList || null;

	this.$ace = $( '<div>' ).attr( 'dir', 'ltr' );
	this.editor = null;
	// Initialise to a rejected promise for the setValue call in the parent constructor
	this.loadingPromise = ve.createDeferred().reject().promise();
	this.styleHeight = null;

	// Parent constructor
	ve.ui.MWAceEditorWidget.super.call( this, config );

	// Clear the fake loading promise and setup properly
	this.loadingPromise = null;
	this.setup();

	this.$element
		.append( this.$ace )
		.addClass( 've-ui-mwAceEditorWidget' );
};

/* Inheritance */

OO.inheritClass( ve.ui.MWAceEditorWidget, ve.ui.WhitespacePreservingTextInputWidget );

/* Events */

/**
 * The editor has resized
 *
 * @event ve.ui.MWAceEditorWidget#resize
 */

/* Methods */

/**
 * Setup the Ace editor instance
 */
ve.ui.MWAceEditorWidget.prototype.setup = function () {
	if ( !this.loadingPromise ) {
		this.loadingPromise = mw.loader.getState( 'ext.codeEditor.ace' ) ?
			mw.loader.using( 'ext.codeEditor.ace' ) :
			ve.createDeferred().reject().promise();
		// Resolved promises will run synchronously, so ensure #setupEditor
		// runs after this.loadingPromise is stored.
		this.loadingPromise.then( this.setupEditor.bind( this ) );
	}
};

/**
 * Destroy the Ace editor instance
 */
ve.ui.MWAceEditorWidget.prototype.teardown = function () {
	this.loadingPromise.then( () => {
		this.$input.removeClass( 'oo-ui-element-hidden' );
		this.editor.destroy();
		this.editor = null;
	} ).always( () => {
		this.loadingPromise = null;
	} );
};

/**
 * Setup the Ace editor
 *
 * @fires ve.ui.MWAceEditorWidget#resize
 */
ve.ui.MWAceEditorWidget.prototype.setupEditor = function () {
	let basePath = mw.config.get( 'wgExtensionAssetsPath', '' );

	if ( basePath.startsWith( '//' ) ) {
		// ACE uses web workers, which have importScripts, which don't like relative links.
		basePath = window.location.protocol + basePath;
	}
	ace.config.set( 'basePath', basePath + '/CodeEditor/modules/ace' );

	this.$input.addClass( 'oo-ui-element-hidden' );
	this.editor = ace.edit( this.$ace[ 0 ] );
	this.setMinRows( this.minRows );

	// Autocompletion
	this.editor.setOptions( {
		enableBasicAutocompletion: this.autocomplete !== 'none',
		enableLiveAutocompletion: this.autocomplete === 'live'
	} );
	if ( this.autocompleteWordList ) {
		const completer = {
			getCompletions: ( editor, session, pos, prefix, callback ) => {
				const wordList = this.autocompleteWordList;
				callback( null, wordList.map( ( word ) => ( {
					caption: word,
					value: word,
					meta: 'static'
				} ) ) );
			}
		};
		ace.require( 'ace/ext/language_tools' ).addCompleter( completer );
	}

	this.editor.getSession().on( 'change', this.onEditorChange.bind( this ) );
	this.editor.renderer.on( 'resize', this.onEditorResize.bind( this ) );
	this.setEditorValue( this.getValue() );
	// Force resize (T303964)

	this.editor.resize( true );
};

/**
 * Set the autocomplete property
 *
 * @param {string} mode Symbolic name of autocomplete mode
 * @return {ve.ui.MWAceEditorWidget}
 * @chainable
 */
ve.ui.MWAceEditorWidget.prototype.setAutocomplete = function ( mode ) {
	this.autocomplete = mode;
	this.loadingPromise.then( () => {
		this.editor.renderer.setOptions( {
			enableBasicAutocompletion: this.autocomplete !== 'none',
			enableLiveAutocompletion: this.autocomplete === 'live'
		} );
	} );
	return this;
};

/**
 * @inheritdoc
 */
ve.ui.MWAceEditorWidget.prototype.setValue = function ( value ) {
	// Always do something synchronously so that getValue can be used immediately.
	// setEditorValue is called once when the loadingPromise resolves in setupEditor.
	if ( this.loadingPromise.state() === 'resolved' ) {
		this.setEditorValue( value );
	} else {
		ve.ui.MWAceEditorWidget.super.prototype.setValue.call( this, value );
	}
	return this;
};

/**
 * Set the value of the Ace editor widget
 *
 * @param {string} value
 * @return {ve.ui.MWAceEditorWidget}
 * @chainable
 */
ve.ui.MWAceEditorWidget.prototype.setEditorValue = function ( value ) {
	if ( value !== this.editor.getValue() ) {
		const selectionState = this.editor.session.selection.toJSON();
		this.editor.setValue( value );
		this.editor.session.selection.fromJSON( selectionState );
	}
	return this;
};

/**
 * Set the minimum number of rows in the Ace editor widget
 *
 * @param {number} minRows The minimum number of rows
 * @return {ve.ui.MWAceEditorWidget}
 * @chainable
 */
ve.ui.MWAceEditorWidget.prototype.setMinRows = function ( minRows ) {
	this.minRows = minRows;
	this.loadingPromise.then( () => {
		this.editor.setOptions( {
			minLines: this.minRows || 3,
			maxLines: this.autosize ? this.maxRows : this.minRows || 3
		} );
	} );
	// TODO: Implement minRows setter for OO.ui.TextInputWidget
	// and call it here in loadingPromise.fail
	return this;
};

/**
 * @inheritdoc
 */
ve.ui.MWAceEditorWidget.prototype.setReadOnly = function ( readOnly ) {
	// Parent method
	ve.ui.MWAceEditorWidget.super.prototype.setReadOnly.call( this, readOnly );

	this.loadingPromise.then( () => {
		this.editor.setReadOnly( this.isReadOnly() );
	} );

	this.$element.toggleClass( 've-ui-mwAceEditorWidget-readOnly', !!this.isReadOnly() );
	return this;
};

/**
 * @inheritdoc
 */
ve.ui.MWAceEditorWidget.prototype.getRange = function () {
	let lines;
	function posToOffset( row, col ) {
		let offset = 0;

		for ( let r = 0; r < row; r++ ) {
			offset += lines[ r ].length;
			offset++; // for the newline character
		}
		return offset + col;
	}

	if ( this.editor ) {
		lines = this.editor.getSession().getDocument().getAllLines();

		const selection = this.editor.getSelection();
		const isBackwards = selection.isBackwards();
		const range = selection.getRange();
		const start = posToOffset( range.start.row, range.start.column );
		const end = posToOffset( range.end.row, range.end.column );

		return {
			from: isBackwards ? end : start,
			to: isBackwards ? start : end
		};
	} else {
		return ve.ui.MWAceEditorWidget.super.prototype.getRange.call( this );
	}
};

/**
 * @inheritdoc
 */
ve.ui.MWAceEditorWidget.prototype.selectRange = function ( from, to ) {
	this.focus();
	this.loadingPromise.then( () => {
		const doc = this.editor.getSession().getDocument(),
			lines = doc.getAllLines();

		to = to || from;

		function offsetToPos( offset ) {
			let row = 0,
				pos = 0;

			while ( row < lines.length && pos + lines[ row ].length < offset ) {
				pos += lines[ row ].length;
				pos++; // for the newline character
				row++;
			}
			const col = offset - pos;
			return { row: row, column: col };
		}

		const fromOffset = offsetToPos( from );
		const toOffset = offsetToPos( to );

		const selection = this.editor.getSelection();
		const range = selection.getRange();
		range.setStart( fromOffset.row, fromOffset.column );
		range.setEnd( toOffset.row, toOffset.column );
		selection.setSelectionRange( range );
	}, () => {
		ve.ui.MWAceEditorWidget.super.prototype.selectRange.call( this, from, to );
	} );
	return this;
};

/**
 * Handle change events from the Ace editor
 */
ve.ui.MWAceEditorWidget.prototype.onEditorChange = function () {
	// Call setValue on the parent to keep the value property in sync with the editor
	ve.ui.MWAceEditorWidget.super.prototype.setValue.call( this, this.editor.getValue() );
};

/**
 * Handle resize events from the Ace editor
 *
 * @fires ve.ui.MWAceEditorWidget#resize
 */
ve.ui.MWAceEditorWidget.prototype.onEditorResize = function () {
	// On the first setup the editor doesn't resize until the end of the cycle
	setTimeout( this.emit.bind( this, 'resize' ) );
};

/**
 * Clear the editor's undo stack
 *
 * @return {ve.ui.MWAceEditorWidget}
 * @chainable
 */
ve.ui.MWAceEditorWidget.prototype.clearUndoStack = function () {
	this.loadingPromise.then( () => {
		this.editor.session.setUndoManager(
			new ace.UndoManager()
		);
	} );
	return this;
};

/**
 * Toggle the visibility of line numbers
 *
 * @param {boolean} visible
 * @return {ve.ui.MWAceEditorWidget}
 * @chainable
 */
ve.ui.MWAceEditorWidget.prototype.toggleLineNumbers = function ( visible ) {
	this.loadingPromise.then( () => {
		this.editor.setOption( 'showLineNumbers', visible );
	} );
	return this;
};

/**
 * Toggle the visibility of the print margin
 *
 * @param {boolean} visible
 * @return {ve.ui.MWAceEditorWidget}
 * @chainable
 */
ve.ui.MWAceEditorWidget.prototype.togglePrintMargin = function ( visible ) {
	this.loadingPromise.then( () => {
		this.editor.renderer.setShowPrintMargin( visible );
	} );
	return this;
};

/**
 * Set the language mode of the editor (programming language)
 *
 * @param {string} lang Language
 * @return {ve.ui.MWAceEditorWidget}
 * @chainable
 */
ve.ui.MWAceEditorWidget.prototype.setLanguage = function ( lang ) {
	this.loadingPromise.then( () => {
		ace.config.loadModule( 'ace/ext/modelist', ( modelist ) => {
			if ( !modelist || !modelist.modesByName[ lang ] ) {
				lang = 'text';
			}
			this.editor.getSession().setMode( 'ace/mode/' + lang );
		} );
	} );
	return this;
};

/**
 * Focus the editor
 *
 * @return {ve.ui.MWAceEditorWidget}
 * @chainable
 */
ve.ui.MWAceEditorWidget.prototype.focus = function () {
	this.loadingPromise.then( () => {
		this.editor.focus();
	}, () => {
		ve.ui.MWAceEditorWidget.super.prototype.focus.call( this );
	} );
	return this;
};

/**
 * @inheritdoc
 * @param {boolean} [force=false] Force a resize call on Ace editor
 */
ve.ui.MWAceEditorWidget.prototype.adjustSize = function ( force ) {
	// If the editor has loaded, resize events are emitted from #onEditorResize
	// so do nothing here unless this is a user triggered resize, otherwise call the parent method.
	if ( force ) {
		this.loadingPromise.then( () => {
			this.editor.resize();
		} );
	}
	this.loadingPromise.then( null, () => {
		// Parent method
		ve.ui.MWAceEditorWidget.super.prototype.adjustSize.call( this );
	} );
	return this;
};
