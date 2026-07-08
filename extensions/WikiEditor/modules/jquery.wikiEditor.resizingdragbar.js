const ResizingDragBar = require( './resizingdragbar/ResizingDragBar.js' );

/**
 * ResizingDragBar Module for wikiEditor
 *
 * @memberof module:ext.wikiEditor
 */
const resizingdragbarModule = {

	/**
	 * Internally used functions
	 */
	fn: {
		/**
		 * Creates a resizingdragbar module within a wikiEditor
		 *
		 * @param {Object} context Context object of editor to create module in
		 */
		create: function ( context ) {
			// Fix the height of the textarea, before adding a resizing bar below it.
			const $uiText = context.$ui.find( '.wikiEditor-ui-text' );
			const height = context.$textarea.height();
			$uiText.css( 'height', height + 'px' );
			context.$textarea.removeAttr( 'rows cols' );
			context.$textarea.addClass( 'ext-WikiEditor-resizable-textbox' );
			$uiText.closest( '.wikiEditor-ui-view' ).addClass( 'wikiEditor-ui-view-resizable' );

			// Add the resizing bar.
			const bottomDragBar = new ResizingDragBar( { isEW: false, id: 'ext-WikiEditor-bottom-dragbar' } );
			$uiText.after( bottomDragBar.$element );
		}
	}

};

module.exports = resizingdragbarModule;
