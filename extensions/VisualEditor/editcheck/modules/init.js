/*
 * `ecenable` query string:
 *   1: override user eligibility criteria for all checks
 *   2: also load experimental checks
 * Can also be a comma-separated series of flags:
 *   experimental: also load experimental checks
 *   suggestions: enable suggestion mode
 *
 * NOTE: everything here involving enabling edit check and experimental mode
 * needs to be kept in sync with ve.init.mw.ArticleTargetLoader, which is
 * responsible for actually loading the modules.
 */
let ecenable = mw.libs.ve.initialUrl.searchParams.get( 'ecenable' );
if ( window.MWVE_FORCE_EDIT_CHECK_ENABLED && ecenable !== '0' ) {
	// if edit check isn't forcibly disabled, override from this global
	ecenable = window.MWVE_FORCE_EDIT_CHECK_ENABLED;
}

// any setting for forceEnable will bypass account-specific configs, though it will still honor the other configs
mw.editcheck = {
	config: require( './config.json' ),
	forceEnable: !!ecenable,
	experimental: !!( mw.config.get( 'wgVisualEditorConfig' ).enableEditCheckExperimental || ecenable === '2' ),
	suggestionsModeAvailable: !!mw.user.options.get( 'visualeditor-editcheck-suggestions' ),
	// runtime performance logging config that we can adjust from the console
	sessionPerfConfig: { checksMax: 5000, typingMaxSamples: 5000 },
	resetSessionState: function () {
		this.checksShown = {};
		this.checksSeen = {};
		this.checksUsed = {};
		this.suggestionsSeen = {};
		this.suggestionsUsed = {};
		this.erroredChecks = {};
	}
};
mw.editcheck.resetSessionState();

if ( ecenable && /^[\w,]+$/.test( ecenable ) ) {
	const ecenableFlags = ecenable.split( ',' );
	mw.editcheck.experimental = mw.editcheck.experimental || ecenableFlags.includes( 'experimental' );
	mw.editcheck.suggestionsModeAvailable = mw.editcheck.suggestionsModeAvailable || ecenableFlags.includes( 'suggestions' );
}

const abCheck = mw.config.get( 'wgVisualEditorConfig' ).editCheckABTest;
const abGroup = mw.config.get( 'wgVisualEditorConfig' ).editCheckABTestGroup;
const enableAbCheck = abGroup === 'test' || mw.editcheck.forceEnable;

// Checks which are loaded for logging but shouldn't show by default yet
const nonDefaultChecks = new Set();

require( './utils.js' );
require( './EditCheckPerformance.js' );
require( './EditCheckPreSaveToolbarTools.js' );
require( './EditCheckFactory.js' );
require( './EditCheckAction.js' );
require( './EditCheckActionWidget.js' );
require( './EditCheckGutterSectionWidget.js' );
require( './dialogs/EditCheckScrollIntoViewWidget.js' );
require( './dialogs/EditCheckDialog.js' );
require( './dialogs/FixedEditCheckDialog.js' );
require( './dialogs/SidebarEditCheckDialog.js' );
require( './dialogs/GutterSidebarEditCheckDialog.js' );
require( './editchecks/BaseEditCheck.js' );
require( './editchecks/ContentBranchNodeCheck.js' );
require( './editchecks/LinkEditCheck.js' );
require( './editchecks/AsyncTextCheck.js' );

if ( mw.editcheck.experimental ) {
	// ext.visualEditor.editCheck.experimental already loaded by ve.init.mw.ArticleTargetLoader
	nonDefaultChecks.clear();
}

if ( enableAbCheck ) {
	nonDefaultChecks.delete( abCheck );
} else if ( abGroup === 'control' ) {
	// This allows us to make default checks a/b testable
	nonDefaultChecks.add( abCheck );
}

for ( const check of nonDefaultChecks ) {
	mw.editcheck.editCheckFactory.unregister( check );
}

if ( abCheck === 'paste' ) {
	// In the a/b test, force-enable/disable the check
	mw.editcheck.config.paste = ve.extendObject( mw.editcheck.config.paste || {}, { showAsCheck: enableAbCheck } );
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
	// Check might not be registered so we can't use the factory.
	const check = new mw.editcheck.AddReferenceEditCheck( null, mw.editcheck.editCheckFactory.buildConfig( 'addReference', { showAsCheck: true } ) );
	try {
		return check.findAddedContent( documentModel, includeReferencedContent ).length > 0;
	} catch ( e ) {
		mw.log.error( 'Error checking hasAddedContentNeedingReference', e );
		return false;
	}
};

mw.editcheck.hasFailingToneCheck = function ( surfaceModel ) {
	// Check might not be registered so we can't use the factory.
	const check = new mw.editcheck.ToneCheck( null, mw.editcheck.editCheckFactory.buildConfig( 'tone', { showAsCheck: true } ) );
	// Run actual check eligibility before calling API
	let canBeShown;
	try {
		canBeShown = check.canBeShown( surfaceModel.getDocument(), false );
	} catch ( e ) {
		mw.log.error( 'Error checking hasFailingToneCheck', e );
		return Promise.resolve( false );
	}
	if ( !canBeShown ) {
		return ve.createDeferred().resolve( false ).promise();
	}
	try {
		return Promise.all( check.handleListener( 'onCheckAll', surfaceModel ) )
			.then( ( results ) => results.some( ( result ) => result !== null ) )
			.catch( () => {} );
	} catch ( e ) {
		mw.log.error( 'Error checking hasFailingToneCheck', e );
		return Promise.resolve( false );
	}
};

if ( mw.config.get( 'wgVisualEditorConfig' ).editCheckTagging ) {
	mw.hook( 've.newTarget' ).add( ( target ) => {
		if ( target.constructor.static.name !== 'article' ) {
			return;
		}

		target.on( 'surfaceReady', () => {
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
				if ( Object.keys( mw.editcheck.suggestionsSeen ).length > 0 ) {
					tags.push( 'editsuggestion-seen' );
				}
				if ( Object.keys( mw.editcheck.suggestionsUsed ).length > 0 ) {
					tags.push( 'editsuggestion-used' );
				}
				if ( hasFailingToneCheck ) {
					tags.push( 'editcheck-tone' );
				} else if ( hasFailingToneCheck === null ) {
					ve.track( 'activity.editCheck-tone', { action: 'save-before-check-finalized' } );
				}
				return tags.join( ',' );
			};
		} );

		target.on( 'teardown', () => {
			delete target.saveFields.vetags;
		} );
	} );
}

if ( mw.config.get( 'wgVisualEditorConfig' ).editCheck || mw.editcheck.forceEnable ) {
	if ( mw.editcheck.suggestionsModeAvailable ) {
		require( './EditCheckSuggestionsTool.js' );
	}

	const Controller = require( './controller.js' ).Controller;
	mw.hook( 've.newTarget' ).add( ( target ) => {
		if ( target.constructor.static.name !== 'article' ) {
			return;
		}
		const controller = new Controller( target, {
			suggestionsModeAvailable: mw.editcheck.suggestionsModeAvailable
		} );
		controller.setup();

		target.editcheckController = controller;

		// Temporary logging for T394952
		if ( abCheck === 'tone' && !enableAbCheck ) {
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
		if ( mw.editcheck.experimental ) {
			ve.track( 'activity.editCheck', { action: 'session-initialized-with-experimental' } );
		}
		if ( mw.editcheck.suggestionsModeAvailable ) {
			ve.track( 'activity.editCheck', { action: 'session-initialized-with-suggestions' } );
		}
		target.on( 'surfaceReady', () => {
			target.getSurface().on( 'destroy', () => {
				mw.editcheck.resetSessionState();
			} );
			// Temporary logging for T402460
			target.getSurface().getView().on( 'paste', ( data ) => {
				const defaults = mw.editcheck.editCheckFactory.buildConfig( 'paste' );
				// Check might not be registered so we can't use the factory.
				const check = new mw.editcheck.PasteCheck( null, mw.editcheck.editCheckFactory.buildConfig( 'paste', { showAsCheck: true } ) );
				if ( check.canBeShown( target.getSurface().getModel().getDocument(), false ) && data.fragment.getSelection().getCoveringRange().getLength() >= check.config.minimumCharacters ) {
					// The check would be shown for the current viewer, and there's enough content that we care about it:
					if ( data.source ) {
						// Known-source pastes that we're not showing regardless of the check being enabled/disabled
						ve.track( 'activity.editCheck-paste', { action: 'ignored-paste-' + data.source } );
					} else if ( !defaults.showAsCheck ) {
						// The check is disabled, and there's no source so we would have shown the check otherwise
						ve.track( 'activity.editCheck-paste', { action: 'relevant-paste' } );
					}
				}
			} );
		} );
	} );
}
