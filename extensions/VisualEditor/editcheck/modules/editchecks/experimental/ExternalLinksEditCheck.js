mw.editcheck.ExternalLinksEditCheck = function MWExternalLinksEditCheck() {
	// Parent constructor
	mw.editcheck.ExternalLinksEditCheck.super.apply( this, arguments );
};

OO.inheritClass( mw.editcheck.ExternalLinksEditCheck, mw.editcheck.BaseEditCheck );

mw.editcheck.ExternalLinksEditCheck.static.title = 'External link';

mw.editcheck.ExternalLinksEditCheck.static.name = 'externalLink';

mw.editcheck.ExternalLinksEditCheck.static.description = 'Generally, external links should not appear in the body of the article. Please refer to WP:ELNO. Edit this link?';

mw.editcheck.ExternalLinksEditCheck.static.defaultConfig = ve.extendObject( {}, mw.editcheck.BaseEditCheck.static.defaultConfig, {
	ignoreSections: [ 'External links' ]
} );

mw.editcheck.ExternalLinksEditCheck.static.choices = [
	{
		action: 'edit',
		label: 'Edit link',
		icon: 'edit'
	},
	{
		action: 'dismiss',
		label: ve.msg( 'editcheck-dialog-action-no' ),
		icon: 'check'
	}
];

mw.editcheck.ExternalLinksEditCheck.prototype.onDocumentChange = function ( surfaceModel ) {
	const modified = this.getModifiedContentRanges( surfaceModel.getDocument() );
	return surfaceModel.documentModel.documentNode.getAnnotationRanges()
		.filter( ( annRange ) => annRange.annotation instanceof ve.dm.MWExternalLinkAnnotation &&
			!this.isDismissedRange( annRange.range ) &&
			this.isRangeInValidSection( annRange.range, surfaceModel.documentModel ) &&
			modified.some( ( modifiedRange ) => modifiedRange.containsRange( annRange.range ) )
		).map( ( annRange ) => new mw.editcheck.EditCheckAction( {
			fragments: [ surfaceModel.getLinearFragment( annRange.range ) ],
			check: this
		} ) );
};

mw.editcheck.ExternalLinksEditCheck.prototype.act = function ( choice, action, surface ) {
	switch ( choice ) {
		case 'dismiss':
			this.dismiss( action );
			break;
		case 'edit':
			setTimeout( () => {
				action.fragments[ 0 ].select();
				surface.execute( 'window', 'open', 'link' );
			} );
			break;
	}
};

mw.editcheck.editCheckFactory.register( mw.editcheck.ExternalLinksEditCheck );
