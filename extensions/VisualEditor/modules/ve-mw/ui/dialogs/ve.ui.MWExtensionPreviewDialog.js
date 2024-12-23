/*!
 * VisualEditor UserInterface MWExtensionPreviewDialog class.
 *
 * @copyright See AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * Dialog for editing generic MediaWiki extensions with a preview.
 *
 * @class
 * @abstract
 * @extends ve.ui.MWExtensionDialog
 *
 * @constructor
 * @param {Object} [config] Configuration options
 */
ve.ui.MWExtensionPreviewDialog = function VeUiMWExtensionPreviewDialog() {
	// Parent constructor
	ve.ui.MWExtensionPreviewDialog.super.apply( this, arguments );

	this.updatePreviewDebounced = ve.debounce( this.updatePreview.bind( this ), 1000 );
};

/* Inheritance */

OO.inheritClass( ve.ui.MWExtensionPreviewDialog, ve.ui.MWExtensionDialog );

/* Methods */

/**
 * @inheritdoc
 */
ve.ui.MWExtensionPreviewDialog.prototype.initialize = function () {
	// Parent method
	ve.ui.MWExtensionPreviewDialog.super.prototype.initialize.call( this );

	// Properties
	this.previewNode = null;
	this.previewElement = new ve.ui.MWPreviewElement();

	// Initialization
	this.$element.addClass( 've-ui-mwExtensionPreviewDialog' );
};

/**
 * @inheritdoc
 */
ve.ui.MWExtensionPreviewDialog.prototype.getSetupProcess = function ( data ) {
	return ve.ui.MWExtensionPreviewDialog.super.prototype.getSetupProcess.call( this, data )
		.next( () => {
			let element;
			if ( this.selectedNode ) {
				element = this.selectedNode.getClonedElement();
			} else {
				element = this.getNewElement();
			}
			const linearData = [ element, { type: '/' + element.type } ];
			if ( ve.dm.nodeFactory.isNodeContent( element.type ) ) {
				linearData.unshift( { type: 'paragraph' } );
				linearData.push( { type: '/paragraph' } );
			}
			linearData.push(
				{ type: 'internalList' },
				{ type: '/internalList' }
			);
			// We assume that WindowAction pass
			const doc = data.fragment.getDocument().cloneWithData( linearData );

			const rootNode = doc.getDocumentNode().children[ 0 ];
			this.previewNode = doc.getNodesByType( element.type )[ 0 ];
			this.previewElement.setModel( rootNode );
		} );
};

/**
 * @inheritdoc
 */
ve.ui.MWExtensionPreviewDialog.prototype.onChange = function () {
	// Parent method
	ve.ui.MWExtensionPreviewDialog.super.prototype.onChange.call( this );

	this.updatePreviewDebounced();
};

/**
 * Update the node rendering to reflect the current content in the dialog.
 */
ve.ui.MWExtensionPreviewDialog.prototype.updatePreview = function () {
	const mwData = ve.copy( this.previewNode.getAttribute( 'mw' ) ),
		doc = this.previewNode.getDocument();

	this.updateMwData( mwData );

	doc.commit(
		ve.dm.TransactionBuilder.static.newFromAttributeChanges(
			doc, this.previewNode.getOuterRange().start, { mw: mwData }
		)
	);
	this.previewElement.updatePreview();
};
