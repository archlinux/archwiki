/**
 * Edit check to detect images without captions
 *
 * @class
 * @extends mw.editcheck.BaseEditCheck
 *
 * @constructor
 * @param {mw.editcheck.Controller} controller
 * @param {Object} [config]
 * @param {boolean} [includeSuggestions=false]
 */
mw.editcheck.ImageCaptionEditCheck = function () {
	// Parent constructor
	mw.editcheck.ImageCaptionEditCheck.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( mw.editcheck.ImageCaptionEditCheck, mw.editcheck.BaseEditCheck );

/* Static properties */

mw.editcheck.ImageCaptionEditCheck.static.defaultConfig = ve.extendObject( {}, mw.editcheck.ImageCaptionEditCheck.super.static.defaultConfig, {
	showAsCheck: false
} );

mw.editcheck.ImageCaptionEditCheck.static.name = 'imageCaption';

mw.editcheck.ImageCaptionEditCheck.static.title = OO.ui.deferMsg( 'editcheck-image-caption-title' );

mw.editcheck.ImageCaptionEditCheck.static.description = ve.deferJQueryMsg( 'editcheck-image-caption-description' );

mw.editcheck.ImageCaptionEditCheck.static.choices = [
	{
		action: 'edit',
		label: OO.ui.deferMsg( 'editcheck-action-add-caption' )
	},
	{
		action: 'dismiss',
		label: OO.ui.deferMsg( 'ooui-dialog-process-dismiss' )
	}
];

/* Methods */

mw.editcheck.ImageCaptionEditCheck.prototype.onBeforeSave = function ( surfaceModel ) {
	return this.getAddedNodes( surfaceModel.getDocument(), 'mwBlockImage' )
		.filter( ( image ) => !this.isDismissedRange( image.getOuterRange() ) )
		.filter( ( image ) => (
			// Only thumbnail style images require a caption
			image.getAttribute( 'type' ) === 'thumb' &&
			// The image contains a caption
			image.children[ 0 ] && image.children[ 0 ].getType() === 'mwImageCaption' &&
			// There's no content inside the caption node (it'll always contain at least an empty paragraph)
			image.children[ 0 ].length === 2 &&
			// But make sure the node inside the caption *could* contain
			// content; if not, it's probably a template or similar, and
			// should count as a caption being set
			image.children[ 0 ].children[ 0 ].canContainContent()
		) )
		.map( ( image ) => new mw.editcheck.EditCheckAction( {
			check: this,
			fragments: [ surfaceModel.getLinearFragment( image.getOuterRange() ) ]
		} ) );
};

mw.editcheck.ImageCaptionEditCheck.prototype.onBranchNodeChange = mw.editcheck.ImageCaptionEditCheck.prototype.onBeforeSave;

mw.editcheck.ImageCaptionEditCheck.prototype.act = function ( choice, action, surface ) {
	if ( choice === 'edit' ) {
		action.fragments[ 0 ].select();
		surface.executeCommand( 'media' );
		return;
	}
	// Parent method
	return mw.editcheck.ImageCaptionEditCheck.super.prototype.act.apply( this, arguments );
};

/* Registration */

mw.editcheck.editCheckFactory.register( mw.editcheck.ImageCaptionEditCheck );
