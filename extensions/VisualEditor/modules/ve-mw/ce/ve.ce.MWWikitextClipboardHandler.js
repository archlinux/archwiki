/*!
 * VisualEditor ContentEditable MWWikitextClipboardHandler class.
 *
 * @copyright See AUTHORS.txt
 */

/**
 * @param {ve.ce.Surface} surface
 */
ve.ce.MWWikitextClipboardHandler = function VeCeMwWikitextClipboardHandler() {
	// Parent constructor
	ve.ce.MWWikitextClipboardHandler.super.apply( this, arguments );

	this.plainInput = new OO.ui.MultilineTextInputWidget();
};

/* Inheritance */

OO.inheritClass( ve.ce.MWWikitextClipboardHandler, ve.ce.ClipboardHandler );

/* Methods */

/**
 * @inheritdoc
 */
ve.ce.MWWikitextClipboardHandler.prototype.onCopy = function ( e ) {
	const clipboardData = e.originalEvent.clipboardData,
		surface = this.getSurface(),
		text = surface.getModel().getFragment().getText( true ).replace( /\n\n/g, '\n' );

	if ( !text ) {
		return;
	}

	if ( clipboardData ) {
		// Disable the default event so we can override the data
		e.preventDefault();
		clipboardData.setData( 'text/plain', text );
		// We're not going to set HTML, but for browsers that support custom data, set a clipboard key
		if ( ve.isClipboardDataFormatsSupported( e, true ) ) {
			const slice = surface.getModel().getDocument().shallowCloneFromSelection( surface.getModel().getSelection() );
			this.clipboardIndex++;
			const clipboardKey = this.clipboardId + '-' + this.clipboardIndex;
			this.clipboard = { slice: slice, hash: null };
			// Clone the elements in the slice
			slice.data.cloneElements( true );
			clipboardData.setData( this.constructor.static.clipboardKeyMimeType, clipboardKey );

			// Explicitly store wikitext as text/x-wiki, so that wikitext-aware paste
			// contexts can accept it without having to do any content-
			// sniffing.
			clipboardData.setData( 'text/x-wiki', text );
		}
	} else {
		const originalSelection = new ve.SelectionState( surface.nativeSelection );

		// Save scroll position before changing focus to "offscreen" clipboard target
		const scrollTop = surface.getSurface().$scrollContainer.scrollTop();

		// Prevent surface observation due to native range changing
		surface.surfaceObserver.disable();
		this.$element.empty().append( this.plainInput.$element );
		this.plainInput.setValue( text ).select();

		// Restore scroll position after changing focus
		surface.getSurface().$scrollContainer.scrollTop( scrollTop );

		// setTimeout: postpone until after the default copy action
		setTimeout( () => {
			// Change focus back
			surface.$attachedRootNode[ 0 ].focus();
			surface.showSelectionState( originalSelection );
			// Restore scroll position
			surface.getSurface().$scrollContainer.scrollTop( scrollTop );
			surface.surfaceObserver.clear();
			surface.surfaceObserver.enable();
			// Detach input
			this.plainInput.$element.detach();
		} );
	}
};

/**
 * @inheritdoc
 */
ve.ce.MWWikitextClipboardHandler.prototype.afterPasteInsertExternalData = function ( targetFragment, pastedDocumentModel, contextRange ) {
	const wasSpecial = this.isPasteSpecial(),
		// TODO: This check returns true if the paste contains meaningful structure (tables, lists etc.)
		// but no annotations (bold, links etc.).
		wasPlain = wasSpecial || pastedDocumentModel.data.isPlainText( contextRange, true, undefined, true );

	const plainPastedDocumentModel = pastedDocumentModel.shallowCloneFromRange( contextRange );
	plainPastedDocumentModel.data.sanitize( { plainText: true, keepEmptyContentBranches: true } );
	// We just turned this into plaintext, which probably
	// affected the content-length. Luckily, because of
	// the earlier clone, we know we just want the whole
	// document, and because of the major change to
	// plaintext, the difference between originalRange and
	// balancedRange don't really apply. As such, clear
	// out newDocRange. (Can't just make it undefined;
	// need to exclude the internal list, and since we're
	// from a paste we also have to exclude the
	// opening/closing paragraph.)
	const plainContextRange = new ve.Range( plainPastedDocumentModel.getDocumentRange().from + 1, plainPastedDocumentModel.getDocumentRange().to - 1 );
	this.prepareForPasteSpecial();

	// isPlainText is true but we still need sanitize (e.g. remove lists)
	const promise = ve.ce.MWWikitextClipboardHandler.super.prototype.afterPasteInsertExternalData.call( this, targetFragment, plainPastedDocumentModel, plainContextRange );
	if ( !wasPlain ) {
		promise.then( () => {
			// We need to wait for the selection change after paste as that triggers
			// a contextChange event. Really we should wait for the afterPaste promise to resolve.
			setTimeout( () => {
				const surface = this.getSurface(),
					context = surface.getSurface().getContext();
				// Ensure surface is deactivated on mobile so context can be shown (T336073)
				if ( context.isMobile() ) {
					surface.deactivate();
				}
				context.addPersistentSource( {
					embeddable: false,
					name: 'wikitextPaste',
					data: {
						doc: pastedDocumentModel,
						contextRange: contextRange,
						fragment: targetFragment
					}
				} );
				surface.getModel().once( 'select', () => {
					context.removePersistentSource( 'wikitextPaste' );
				} );
			} );
		} );
	}
	return promise;
};
