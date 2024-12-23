mw.editcheck.ConvertReferenceEditCheck = function MWConvertReferenceEditCheck( /* config */ ) {
	// Parent constructor
	mw.editcheck.ConvertReferenceEditCheck.super.apply( this, arguments );
};

OO.inheritClass( mw.editcheck.ConvertReferenceEditCheck, mw.editcheck.BaseEditCheck );

mw.editcheck.ConvertReferenceEditCheck.static.name = 'convertReference';

mw.editcheck.ConvertReferenceEditCheck.prototype.onDocumentChange = function ( surfaceModel ) {
	const seenIndexes = {};
	const documentModel = surfaceModel.getDocument();
	return documentModel.getNodesByType( 'mwReference' ).map( ( node ) => {
		const refModel = ve.dm.MWReferenceModel.static.newFromReferenceNode( node );
		const index = refModel.getListIndex();
		if ( seenIndexes[ index ] ) {
			return null;
		}
		seenIndexes[ index ] = true;
		const referenceNode = documentModel.getInternalList().getItemNode( index );
		const href = ve.ui.CitoidReferenceContextItem.static.getConvertibleHref( referenceNode );
		if ( href ) {
			const fragment = surfaceModel().getFragment( new ve.dm.LinearSelection( node.getOuterRange() ) );
			return new mw.editcheck.EditCheckAction( {
				highlight: fragment,
				selection: fragment,
				message: ve.msg( 'citoid-referencecontextitem-convert-message' ),
				check: this
			} );
		} else {
			return null;
		}
	} ).filter( ( obj ) => obj );
};

mw.editcheck.editCheckFactory.register( mw.editcheck.ConvertReferenceEditCheck );
