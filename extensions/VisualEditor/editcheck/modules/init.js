/*
 * `ecenable` query string:
 *   1: override user eligibility criteria for all checks
 *   2: also load experimental checks
 * Can also be a comma-separated series of flags:
 *   experimental: also load experimental checks
 *   suggestions: enable suggestions mode
 */
const ecenable = mw.libs.ve.initialUrl.searchParams.get( 'ecenable' );
const abCheck = mw.config.get( 'wgVisualEditorConfig' ).editCheckABTest;
const abGroup = mw.config.get( 'wgVisualEditorConfig' ).editCheckABTestGroup;

mw.editcheck = {
	config: require( './config.json' ),
	forceEnable: !!( ecenable || window.MWVE_FORCE_EDIT_CHECK_ENABLED ),
	experimental: !!( mw.config.get( 'wgVisualEditorConfig' ).editCheckExperimental || ecenable === '2' ),
	checksShown: {}
};

if ( ecenable && /^[\w,]+$/.test( ecenable ) ) {
	const ecenableFlags = ecenable.split( ',' );
	mw.editcheck.experimental = mw.editcheck.experimental || ecenableFlags.includes( 'experimental' );
	mw.editcheck.suggestions = mw.editcheck.suggestions || ecenableFlags.includes( 'suggestions' );
}

// Checks which are loaded for logging but shouldn't show by default yet
const nonDefaultChecks = new Set();

require( './utils.js' );
require( './EditCheckPreSaveToolbarTools.js' );
require( './EditCheckFactory.js' );
require( './EditCheckAction.js' );
require( './EditCheckActionWidget.js' );
require( './EditCheckGutterSectionWidget.js' );
require( './dialogs/EditCheckDialog.js' );
require( './dialogs/FixedEditCheckDialog.js' );
require( './dialogs/SidebarEditCheckDialog.js' );
require( './dialogs/GutterSidebarEditCheckDialog.js' );
require( './editchecks/BaseEditCheck.js' );
require( './editchecks/AsyncTextCheck.js' );
require( './editchecks/AddReferenceEditCheck.js' );
require( './editchecks/ToneCheck.js' );
require( './editchecks/PasteCheck.js' );

nonDefaultChecks.add( 'tone' );

if ( mw.editcheck.experimental ) {
	mw.loader.using( 'ext.visualEditor.editCheck.experimental' );
	nonDefaultChecks.clear();
}
if ( abGroup === 'test' ) {
	nonDefaultChecks.delete( abCheck );
}

for ( const check of nonDefaultChecks ) {
	mw.editcheck.editCheckFactory.unregister( check );
}

if ( abCheck === 'paste' ) {
	// In the a/b test, force-enable/disable the check
	mw.editcheck.config.paste = ve.extendObject( mw.editcheck.config.paste || {}, { enabled: abGroup === 'test' } );
}

const isMainNamespace = mw.config.get( 'wgNamespaceNumber' ) === mw.config.get( 'wgNamespaceIds' )[ '' ];

// Helper functions for ve.init.mw.ArticleTarget save-tagging, keep logic
// in-sync with AddReferenceEditCheck and ToneCheck.

/**
 * Check if the document has content needing a reference, for AddReferenceEditCheck
 *
 * @param {ve.dm.Document} documentModel
 * @param {boolean} includeReferencedContent Include content that already contains a reference
 * @return {boolean}
 */
mw.editcheck.hasAddedContentNeedingReference = function ( documentModel, includeReferencedContent ) {
	// Tag anything in the main namespace, regardless of other eligibility checks
	if ( !isMainNamespace ) {
		return false;
	}
	// TODO: This should be factored out into a static method so we don't have to construct a dummy check
	const check = mw.editcheck.editCheckFactory.create( 'addReference', null, { enabled: true } );
	return check.findAddedContent( documentModel, includeReferencedContent ).length > 0;
};

mw.editcheck.hasFailingToneCheck = function ( surfaceModel ) {
	// Check might not be registered so we can't use the factory.
	const check = new mw.editcheck.ToneCheck( null, mw.editcheck.editCheckFactory.buildConfig( 'tone', { enabled: true } ) );
	// Run actual check eligibility before calling API
	if ( !check.canBeShown() ) {
		return ve.createDeferred().resolve( false ).promise();
	}
	return Promise.all( check.handleListener( 'onCheckAll', surfaceModel ) )
		.then( ( results ) => results.some( ( result ) => result !== null ) );
};

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

		let hasFailingToneCheck = null;
		target.getPreSaveProcess().first( () => {
			// Start checking for tone in the pre-save process, but don't block the save dialog
			// from appearing. If the tone check isn't finished by save time we will just log
			// an error.
			hasFailingToneCheck = null;
			mw.editcheck.hasFailingToneCheck( target.getSurface().getModel() ).then( ( result ) => {
				hasFailingToneCheck = result;
			} );
		} );

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
			if ( mw.editcheck.checksShown.addReference ) {
				tags.push( 'editcheck-references-shown' );
			}
			if ( mw.editcheck.checksShown.tone ) {
				tags.push( 'editcheck-tone-shown' );
			}
			if ( mw.editcheck.checksShown.paste ) {
				tags.push( 'editcheck-paste-shown' );
			}
			if ( hasFailingToneCheck ) {
				tags.push( 'editcheck-tone' );
			} else if ( hasFailingToneCheck === null ) {
				ve.track( 'activity.editCheck-tone', { action: 'save-before-check-finalized' } );
			}
			return tags.join( ',' );
		};
	} );
	mw.hook( 've.deactivationComplete' ).add( () => {
		const target = ve.init.target;
		delete target.saveFields.vetags;
	} );
}

if ( mw.config.get( 'wgVisualEditorConfig' ).editCheck || mw.editcheck.forceEnable ) {
	const Controller = require( './controller.js' ).Controller;
	mw.hook( 've.newTarget' ).add( ( target ) => {
		if ( target.constructor.static.name !== 'article' ) {
			return;
		}
		const controller = new Controller( target, {
			suggestions: mw.editcheck.suggestions
		} );
		controller.setup();

		target.editcheckController = controller;

		// Temporary logging for T394952
		if ( abCheck === 'tone' && abGroup === 'control' ) {
			const checkForTone = function ( listener ) {
				mw.editcheck.hasFailingToneCheck( controller.surface.getModel() ).then( ( result ) => {
					if ( result ) {
						ve.track( 'activity.editCheck-tone', { action: 'check-control-' + listener } );
					}
				} );
			};
			controller.on( 'branchNodeChange', () => {
				checkForTone( 'branchNodeChange' );
			} );
			controller.on( 'onBeforeSave', () => {
				checkForTone( 'onBeforeSave' );
			} );
		}
		// Temporary logging for T402460
		target.on( 'surfaceReady', () => {
			target.getSurface().getView().on( 'paste', ( data ) => {
				const defaults = mw.editcheck.editCheckFactory.buildConfig( 'paste' );
				const check = mw.editcheck.editCheckFactory.create( 'paste', null, { enabled: true } );
				if ( check.canBeShown() && data.fragment.getSelection().getCoveringRange().getLength() >= check.config.minimumCharacters ) {
					// The check would be shown for the current viewer, and there's enough content that we care about it:
					if ( data.source ) {
						// Known-source pastes that we're not showing regardless of the check being enabled/disabled
						ve.track( 'activity.editCheck-paste', { action: 'ignored-paste-' + data.source } );
					} else if ( !defaults.enabled ) {
						// The check is disabled, and there's no source so we would have shown the check otherwise
						ve.track( 'activity.editCheck-paste', { action: 'relevant-paste' } );
					}
				}
			} );
		} );
	} );
}
