mw.editcheck.ImageCaptionEditCheck = function () {
	// Parent constructor
	mw.editcheck.ImageCaptionEditCheck.super.apply( this, arguments );
};

OO.inheritClass( mw.editcheck.ImageCaptionEditCheck, mw.editcheck.BaseEditCheck );

mw.editcheck.ImageCaptionEditCheck.static.name = 'imageCaption';
mw.editcheck.ImageCaptionEditCheck.static.title = 'Image needs caption';
mw.editcheck.ImageCaptionEditCheck.static.description = 'This image is lacking a caption, which can be important to readers to explain why the image is present. Not every image needs a caption; some are simply decorative. Relatively few may be genuinely self-explanatory. Does this image need a caption?';

mw.editcheck.ImageCaptionEditCheck.prototype.onBeforeSave = function ( surfaceModel ) {
	return this.getAddedNodes( surfaceModel.getDocument(), 'mwBlockImage' )
		.filter( ( image ) => !this.isDismissedRange( image.getOuterRange() ) )
		.filter( ( image ) => image.children[ 0 ] && image.children[ 0 ].getType() === 'mwImageCaption' && image.children[ 0 ].length === 2 )
		.map( ( image ) => new mw.editcheck.EditCheckAction( {
			check: this,
			fragments: [ surfaceModel.getFragment( new ve.dm.LinearSelection( image.getOuterRange() ) ) ]
		} ) );
};

mw.editcheck.ImageCaptionEditCheck.prototype.onBranchNodeChange = mw.editcheck.ImageCaptionEditCheck.prototype.onBeforeSave;

mw.editcheck.ImageCaptionEditCheck.prototype.act = function ( choice, action, surface ) {
	const windowAction = ve.ui.actionFactory.create( 'window', surface, 'check' );
	switch ( choice ) {
		case 'accept':
			action.fragments[ 0 ].select();
			return windowAction.open( 'media' ).then( ( instance ) => instance.closing );
		case 'reject':
			this.dismiss( action );
			return ve.createDeferred().resolve( true ).promise();
	}
};

mw.editcheck.editCheckFactory.register( mw.editcheck.ImageCaptionEditCheck );
