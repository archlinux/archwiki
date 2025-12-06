mw.editcheck.ConvertReferenceEditCheck = function MWConvertReferenceEditCheck() {
	// Parent constructor
	mw.editcheck.ConvertReferenceEditCheck.super.apply( this, arguments );
};

OO.inheritClass( mw.editcheck.ConvertReferenceEditCheck, mw.editcheck.BaseEditCheck );

mw.editcheck.ConvertReferenceEditCheck.static.title = ve.msg( 'citoid-referencecontextitem-convert-button' );

mw.editcheck.ConvertReferenceEditCheck.static.name = 'convertReference';

mw.editcheck.ConvertReferenceEditCheck.static.choices = [
	{
		action: 'convert',
		label: ve.msg( 'citoid-referencecontextitem-convert-button' )
	},
	{
		action: 'dismiss',
		label: ve.msg( 'ooui-dialog-process-dismiss' )
	}
];

mw.editcheck.ConvertReferenceEditCheck.prototype.onDocumentChange = function ( surfaceModel ) {
	const seenIndexes = {};
	const documentModel = surfaceModel.getDocument();
	return this.getAddedNodes( documentModel, 'mwReference' ).map( ( node ) => {
		const index = node.getIndexNumber();
		if ( seenIndexes[ index ] ) {
			return null;
		}
		seenIndexes[ index ] = true;
		const referenceNode = node.getInternalItem();
		const href = ve.ui.CitoidReferenceContextItem.static.getConvertibleHref( referenceNode );
		if ( href ) {
			return node.getOuterRange();
		} else {
			return null;
		}
	} ).filter( ( obj ) => obj ).filter( ( range ) => !this.isDismissedRange( range ) ).map( ( range ) => (
		new mw.editcheck.EditCheckAction( {
			fragments: [ surfaceModel.getLinearFragment( range ) ],
			message: ve.msg( 'citoid-referencecontextitem-convert-message' ),
			check: this
		} )
	) );
};

mw.editcheck.ConvertReferenceEditCheck.prototype.act = function ( choice, action, surface ) {
	switch ( choice ) {
		case 'convert': {
			action.fragments[ 0 ].select();
			const node = action.fragments[ 0 ].getSelectedNode();
			const href = ve.ui.CitoidReferenceContextItem.static.getConvertibleHref( node.getInternalItem() );
			const citoidAction = ve.ui.actionFactory.create( 'citoid', surface );
			citoidAction.open( true, href );
			break;
		}
		case 'dismiss':
			this.dismiss( action );
			break;
	}
};

mw.editcheck.editCheckFactory.register( mw.editcheck.ConvertReferenceEditCheck );
