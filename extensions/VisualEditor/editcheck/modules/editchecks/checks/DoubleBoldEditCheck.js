/**
 * Edit check to detect unnecessary bold formatting
 *
 * @class
 * @extends mw.editcheck.BaseEditCheck
 *
 * @constructor
 * @param {mw.editcheck.Controller} controller
 * @param {Object} [config]
 * @param {boolean} [includeSuggestions=false]
 */
mw.editcheck.DoubleBoldEditCheck = function MWDoubleBoldEditCheck() {
	// Parent constructor
	mw.editcheck.DoubleBoldEditCheck.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( mw.editcheck.DoubleBoldEditCheck, mw.editcheck.BaseEditCheck );

/* Static properties */

mw.editcheck.DoubleBoldEditCheck.static.defaultConfig = ve.extendObject( {}, mw.editcheck.DoubleBoldEditCheck.super.static.defaultConfig, {
	showAsCheck: false
} );

mw.editcheck.DoubleBoldEditCheck.static.name = 'doubleBold';

mw.editcheck.DoubleBoldEditCheck.static.title = OO.ui.deferMsg( 'editcheck-double-bold-title' );

mw.editcheck.DoubleBoldEditCheck.static.description = ve.deferJQueryMsg( 'editcheck-double-bold-description' );

mw.editcheck.DoubleBoldEditCheck.static.choices = [
	{
		action: 'remove',
		label: OO.ui.deferMsg( 'editcheck-double-bold-action-remove' )
	},
	{
		action: 'dismiss',
		label: OO.ui.deferMsg( 'ooui-dialog-process-dismiss' )
	}
];

/* Methods */

mw.editcheck.DoubleBoldEditCheck.prototype.onDocumentChange = function ( surfaceModel ) {
	const documentModel = surfaceModel.getDocument();
	let node, heading, cell, listItem;

	const modified = this.getModifiedContentRanges( documentModel );
	return documentModel.getDocumentNode().getAnnotationRanges()
		.filter( ( annRange ) => annRange.annotation instanceof ve.dm.BoldAnnotation &&
			!this.isDismissedRange( annRange.range ) &&
			this.isRangeInValidSection( annRange.range, documentModel ) &&
			modified.some( ( modifiedRange ) => modifiedRange.containsRange( annRange.range ) ) &&
			( node = documentModel.getBranchNodeFromOffset( annRange.range.start ) ) &&
			(
				(
					( heading = node.findParent( ve.dm.MWHeadingNode ) ) &&
					heading.getAttribute( 'level' ) >= 3
				) ||
				(
					( cell = node.findParent( ve.dm.TableCellNode ) ) &&
					cell.getAttribute( 'style' ) === 'header'
				) ||
				(
					( listItem = node.findParent( ve.dm.DefinitionListItemNode ) ) &&
					listItem.getAttribute( 'style' ) === 'term'
				)
			)
		).map( ( annRange ) => new mw.editcheck.EditCheckAction( {
			fragments: [ surfaceModel.getLinearFragment( annRange.range ) ],
			check: this
		} ) );
};

mw.editcheck.DoubleBoldEditCheck.prototype.act = function ( choice, action, surface ) {
	if ( choice === 'remove' ) {
		action.fragments[ 0 ].annotateContent( 'clear', 'textStyle/bold' );
		action.select( surface, this );
		return;
	}
	// Parent method
	mw.editcheck.DoubleBoldEditCheck.super.prototype.act.apply( this, arguments );
};

/* Registration */

mw.editcheck.editCheckFactory.register( mw.editcheck.DoubleBoldEditCheck );
