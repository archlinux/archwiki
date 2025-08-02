mw.editcheck.ImportCopyvioEditCheck = function MWImportCopyvioEditCheck( /* config */ ) {
	// Parent constructor
	mw.editcheck.ImportCopyvioEditCheck.super.apply( this, arguments );
};

OO.inheritClass( mw.editcheck.ImportCopyvioEditCheck, mw.editcheck.BaseEditCheck );

mw.editcheck.ImportCopyvioEditCheck.static.defaultConfig = ve.extendObject( {}, mw.editcheck.BaseEditCheck.static.defaultConfig, {
	minimumCharacters: 50
} );

mw.editcheck.ImportCopyvioEditCheck.static.title = ve.msg( 'editcheck-copyvio-title' );

mw.editcheck.ImportCopyvioEditCheck.static.name = 'importCopyvio';

mw.editcheck.ImportCopyvioEditCheck.static.choices = [
	{
		action: 'rewrite',
		label: 'Rewrite', // ve.msg( 'editcheck-dialog-action-yes' ),
		icon: 'edit'
	},
	{
		action: 'dismiss',
		label: 'Keep', // ve.msg( 'editcheck-dialog-action-no' ),
		icon: 'check'
	}
];

mw.editcheck.ImportCopyvioEditCheck.prototype.onDocumentChange = function ( surfaceModel ) {
	const pastesById = {};
	surfaceModel.documentModel.documentNode.getAnnotationRanges().forEach( ( annRange ) => {
		const annotation = annRange.annotation;
		if ( annotation instanceof ve.dm.ImportedDataAnnotation && !annotation.getAttribute( 'source' ) ) {
			const id = annotation.getAttribute( 'eventId' );
			if ( this.isDismissedId( id ) ) {
				return;
			}
			if ( annRange.range.getLength() < this.config.minimumCharacters ) {
				return;
			}
			pastesById[ id ] = pastesById[ id ] || [];
			pastesById[ id ].push( annRange.range );
		}
	} );
	return Object.keys( pastesById ).map( ( id ) => {
		const fragments = pastesById[ id ].map( ( range ) => surfaceModel.getLinearFragment( range ) );
		return new mw.editcheck.EditCheckAction( {
			fragments: fragments,
			message: ve.msg( 'editcheck-copyvio-description' ),
			id: id,
			check: this
		} );
	} );
};

mw.editcheck.ImportCopyvioEditCheck.prototype.act = function ( choice, action, surface ) {
	switch ( choice ) {
		case 'dismiss':
			this.dismiss( action );
			break;
		case 'rewrite':
			action.fragments.forEach( ( fragment ) => {
				fragment.removeContent();
			} );
			// Auto-scrolling causes selection and focus changes...
			setTimeout( () => {
				action.fragments[ action.fragments.length - 1 ].select();
				surface.getView().focus();
			}, 500 );
			break;
	}
};

mw.editcheck.editCheckFactory.register( mw.editcheck.ImportCopyvioEditCheck );
