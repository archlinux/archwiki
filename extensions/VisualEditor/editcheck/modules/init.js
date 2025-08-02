mw.editcheck = {
	config: require( './config.json' ),
	ecenable: !!( new URL( location.href ).searchParams.get( 'ecenable' ) || window.MWVE_FORCE_EDIT_CHECK_ENABLED )
};

require( './EditCheckDialog.js' );
require( './EditCheckGutterSidebarDialog.js' );
require( './EditCheckFactory.js' );
require( './EditCheckAction.js' );
require( './BaseEditCheck.js' );

require( './editchecks/AddReferenceEditCheck.js' );

if ( mw.config.get( 'wgVisualEditorConfig' ).editCheckExperimental ) {
	mw.loader.using( 'ext.visualEditor.editCheck.experimental' );
}

/**
 * Check if the document has content needing a reference, for AddReferenceEditCheck
 *
 * @param {ve.dm.Document} documentModel
 * @param {boolean} includeReferencedContent Include contents that already contains a reference
 * @return {boolean}
 */
mw.editcheck.hasAddedContentNeedingReference = function ( documentModel, includeReferencedContent ) {
	// helper for ve.init.mw.ArticleTarget save-tagging, keep logic below in-sync with AddReferenceEditCheck.
	// This is bypassing the normal "should this check apply?" logic for creation, so we need to manually
	// apply the "only the main namespace" rule.
	if ( mw.config.get( 'wgNamespaceNumber' ) !== mw.config.get( 'wgNamespaceIds' )[ '' ] ) {
		return false;
	}
	const check = mw.editcheck.editCheckFactory.create( 'addReference', null, mw.editcheck.config.addReference );
	// TODO: This should be factored out into a static method so we don't have to construct a dummy check
	return check.findAddedContent( documentModel, includeReferencedContent ).length > 0;
};

mw.editcheck.refCheckShown = false;

if ( mw.config.get( 'wgVisualEditorConfig' ).editCheckTagging ) {
	mw.hook( 've.activationComplete' ).add( () => {
		const target = ve.init.target;

		function getRefNodes() {
			// The firstNodes list is a numerically indexed array of reference nodes in the document.
			// The list is append only, and removed references are set to undefined in place.
			// To check if a new reference is being published, we just need to know if a reference
			// with an index beyond the initial list (initLength) is still set.
			const internalList = target.getSurface().getModel().getDocument().getInternalList();
			const group = internalList.getNodeGroup( 'mwReference/' );
			return group ? group.firstNodes || [] : [];
		}

		const initLength = getRefNodes().length;
		target.saveFields.vetags = function () {
			const refNodes = getRefNodes();
			const newLength = refNodes.length;
			let newNodesInDoc = false;
			for ( let i = initLength; i < newLength; i++ ) {
				if ( refNodes[ i ] ) {
					newNodesInDoc = true;
					break;
				}
			}
			const tags = [];
			if ( newNodesInDoc ) {
				tags.push( 'editcheck-newreference' );
			}
			if ( mw.editcheck.refCheckShown ) {
				tags.push( 'editcheck-references-shown' );
			}
			return tags.join( ',' );
		};
	} );
	mw.hook( 've.deactivationComplete' ).add( () => {
		const target = ve.init.target;
		delete target.saveFields.vetags;
	} );
}

if ( mw.config.get( 'wgVisualEditorConfig' ).editCheck || mw.editcheck.ecenable ) {
	const Controller = require( './controller.js' ).Controller;
	mw.hook( 've.newTarget' ).add( ( target ) => {
		if ( target.constructor.static.name !== 'article' ) {
			return;
		}
		const controller = new Controller( target );
		controller.setup();
	} );
}

// This is for the toolbar:

ve.ui.EditCheckBack = function VeUiEditCheckBack() {
	// Parent constructor
	ve.ui.EditCheckBack.super.apply( this, arguments );

	this.setDisabled( false );
};
OO.inheritClass( ve.ui.EditCheckBack, ve.ui.Tool );
ve.ui.EditCheckBack.static.name = 'editCheckBack';
ve.ui.EditCheckBack.static.icon = 'previous';
ve.ui.EditCheckBack.static.autoAddToCatchall = false;
ve.ui.EditCheckBack.static.autoAddToGroup = false;
ve.ui.EditCheckBack.static.title =
	OO.ui.deferMsg( 'visualeditor-backbutton-tooltip' );
ve.ui.EditCheckBack.prototype.onSelect = function () {
	const surface = this.toolbar.getSurface();
	surface.getContext().hide();
	surface.execute( 'window', 'close', 'fixedEditCheckDialog' );
	this.setActive( false );
	ve.track( 'activity.' + this.getName(), { action: 'tool-used' } );
};
ve.ui.EditCheckBack.prototype.onUpdateState = function () {
	this.setDisabled( false );
};
ve.ui.toolFactory.register( ve.ui.EditCheckBack );

ve.ui.EditCheckSaveDisabled = function VeUiEditCheckSaveDisabled() {
	// Parent constructor
	ve.ui.EditCheckSaveDisabled.super.apply( this, arguments );
};
OO.inheritClass( ve.ui.EditCheckSaveDisabled, ve.ui.MWSaveTool );
ve.ui.EditCheckSaveDisabled.static.name = 'showSaveDisabled';
ve.ui.EditCheckSaveDisabled.static.autoAddToCatchall = false;
ve.ui.EditCheckSaveDisabled.static.autoAddToGroup = false;
ve.ui.EditCheckSaveDisabled.prototype.onUpdateState = function () {
	this.setDisabled( true );
};

ve.ui.toolFactory.register( ve.ui.EditCheckSaveDisabled );
