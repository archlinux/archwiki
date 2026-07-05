/*!
 * VisualEditor MediaWiki test utilities.
 *
 * @copyright See AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

{
	const MWDummyTarget = function MWDummyTarget() {
		MWDummyTarget.super.call( this );
	};
	OO.inheritClass( MWDummyTarget, ve.test.utils.DummyTarget );
	MWDummyTarget.prototype.setDefaultMode = () => {};
	MWDummyTarget.prototype.isSaveable = () => true;
	// Ensure a mock server is used (e.g. as in ve.ui.MWWikitextStringTransferHandler)
	MWDummyTarget.prototype.parseWikitextFragment = () => new mw.Api().post();
	MWDummyTarget.prototype.getContentApi = () => new mw.Api();
	MWDummyTarget.prototype.createSurface = ve.init.mw.Target.prototype.createSurface;
	MWDummyTarget.prototype.getSurfaceConfig = ve.init.mw.Target.prototype.getSurfaceConfig;
	MWDummyTarget.prototype.getSurfaceClasses = ve.init.mw.Target.prototype.getSurfaceClasses;
	MWDummyTarget.prototype.isInterwikiUrl = () => ve.createDeferred().resolve( false ).promise();
	// Copy import rules from mw target, for paste tests.
	MWDummyTarget.static.importRules = ve.init.mw.Target.static.importRules;

	ve.test.utils.MWDummyTarget = MWDummyTarget;

	const MWDummyPlatform = function MWDummyPlatform() {
		MWDummyPlatform.super.apply( this, arguments );
		// Disable some API requests from platform
		this.imageInfoCache = null;
	};
	OO.inheritClass( MWDummyPlatform, ve.init.mw.Platform );
	MWDummyPlatform.prototype.getMessage = ( ...args ) => args.join( ',' );
	MWDummyPlatform.prototype.getHtmlMessage = ( ...args ) => {
		const $wrapper = $( '<div>' );
		args.forEach( ( arg, i ) => {
			if ( i > 0 ) {
				$wrapper[ 0 ].appendChild( document.createTextNode( ',' ) );
			}
			// Strings are converted to text nodes
			// eslint-disable-next-line no-jquery/no-append-html
			$wrapper.append( typeof arg === 'string' ? document.createTextNode( arg ) : arg );
		} );
		// Merge text nodes
		$wrapper[ 0 ].normalize();
		return $wrapper.contents().toArray();
	};
	let nextUniqueId = 1;
	MWDummyPlatform.prototype.generateUniqueId = function () {
		return 'test' + ( nextUniqueId++ );
	};
	MWDummyPlatform.prototype.resetUniqueIdCounter = function () {
		nextUniqueId = 1;
	};
	ve.test.utils.MWDummyPlatform = MWDummyPlatform;

	{
		const setEditorPreference = mw.libs.ve.setEditorPreference,
			dummySetEditorPreference = () => ve.createDeferred().resolve().promise(),
			modelOverrides = [
				ve.dm.MWHeadingNode,
				ve.dm.MWPreformattedNode,
				ve.dm.MWTableNode,
				ve.dm.MWExternalLinkAnnotation,
				ve.dm.MWInternalLinkAnnotation
			],
			modelOverridden = [
				ve.dm.InlineImageNode,
				ve.dm.BlockImageNode
			],
			viewOverrides = [
				ve.ce.MWHeadingNode,
				ve.ce.MWPreformattedNode,
				ve.ce.MWTableNode,
				ve.ce.MWExternalLinkAnnotation,
				ve.ce.MWInternalLinkAnnotation
			],
			viewOverridden = [
				ve.ce.InlineImageNode,
				ve.ce.BlockImageNode
			],
			windowOverrides = [
				ve.ui.MWLinkAnnotationInspector,
				ve.ui.MWCommandHelpDialog,
				ve.ui.MWTableDialog
			],
			windowOverridden = [
				ve.ui.LinkAnnotationInspector,
				ve.ui.CommandHelpDialog,
				ve.ui.TableDialog
			],
			actionOverrides = [
				ve.ui.MWLinkAction
			],
			actionOverridden = [
				ve.ui.LinkAction
			];

		const corePlatform = ve.init.platform,
			coreTarget = ve.init.target,
			mwPlatform = new ve.test.utils.MWDummyPlatform();
		// Unregister mwPlatform
		ve.init.platform = corePlatform;

		const mwTarget = new ve.test.utils.MWDummyTarget();
		// Unregister mwTarget
		ve.init.target = coreTarget;

		const getViewFactory = ( view ) => view.prototype instanceof ve.ce.Node ? ve.ce.nodeFactory : ve.ce.annotationFactory;
		const getModelFactory = ( model ) => model.prototype instanceof ve.dm.Node ? ve.dm.nodeFactory : ve.dm.annotationFactory;

		const applyOverrides = ( factory, registers, unregisters ) => {
			unregisters.forEach( ( item ) => ( typeof factory === 'function' ? factory( item ) : factory ).unregister( item ) );
			registers.forEach( ( item ) => ( typeof factory === 'function' ? factory( item ) : factory ).register( item ) );
		};

		const setupOverrides = function () {
			applyOverrides( ve.dm.modelRegistry, modelOverrides, modelOverridden );
			applyOverrides( getModelFactory, modelOverrides, modelOverridden );
			applyOverrides( getViewFactory, viewOverrides, viewOverridden );
			applyOverrides( ve.ui.actionFactory, actionOverrides, actionOverridden );
			applyOverrides( ve.ui.windowFactory, windowOverrides, windowOverridden );

			ve.init.platform = mwPlatform;
			ve.init.platform.resetUniqueIdCounter();
			// Reset caches
			[ 'linkCache', 'imageInfoCache', 'galleryImageInfoCache', 'templateDataCache' ].forEach( ( cache ) => {
				if ( ve.init.platform[ cache ] ) {
					ve.init.platform[ cache ].init();
				}
			} );
			ve.init.target = mwTarget;
			mw.libs.ve.setEditorPreference = dummySetEditorPreference;
			// Ensure the current target is appended to the current fixture
			// eslint-disable-next-line no-jquery/no-global-selector
			$( '#qunit-fixture' ).append( ve.init.target.$element );
		};

		const teardownOverrides = function () {
			applyOverrides( ve.dm.modelRegistry, modelOverridden, modelOverrides );
			applyOverrides( getModelFactory, modelOverridden, modelOverrides );
			applyOverrides( getViewFactory, viewOverridden, viewOverrides );
			applyOverrides( ve.ui.actionFactory, actionOverridden, actionOverrides );
			applyOverrides( ve.ui.windowFactory, windowOverridden, windowOverrides );

			ve.ui.windowFactory.unregister( ve.ui.MWLinkAnnotationInspector );
			ve.ui.windowFactory.register( ve.ui.LinkAnnotationInspector );

			ve.init.platform = corePlatform;
			ve.init.target = coreTarget;
			mw.libs.ve.setEditorPreference = setEditorPreference;
		};

		// On load, teardown overrides so the first core tests run correctly
		teardownOverrides();

		ve.test.utils.newMwEnvironment = function ( env = {} ) {
			return QUnit.newMwEnvironment( ve.extendObject( {}, env, {
				beforeEach: function () {
					setupOverrides();
					if ( env.beforeEach ) {
						env.beforeEach.call( this );
					}
				},
				afterEach: function () {
					teardownOverrides();
					if ( env.afterEach ) {
						env.afterEach.call( this );
					}
				}
			} ) );
		};
	}

	const getDomElementSummaryCore = ve.getDomElementSummary;

	/**
	 * Override getDomElementSummary to extract HTML from data-mw/body.html
	 * and make it comparable.
	 *
	 * @inheritdoc ve#getDomElementSummary
	 */
	ve.getDomElementSummary = function ( element, includeHtml ) {
		// "Parent" method
		return getDomElementSummaryCore( element, includeHtml, ( name, value ) => {
			if ( name === 'data-mw' ) {
				const obj = JSON.parse( value ),
					html = ve.getProp( obj, 'body', 'html' );
				if ( html ) {
					obj.body.html = ve.getDomElementSummary( $( '<div>' ).html( html )[ 0 ] );
				}
				return obj;
			}
			return value;
		} );
	};

	// Setup overrides for core environment (done in ve.qunit.local.js in core)

	const origModule = QUnit.module;

	const wrappedModule = function ( name, localEnv = {} ) {
		// Copy all hooks so before/after/each are preserved
		const hooks = Object.assign( {}, localEnv );
		const origBeforeEach = hooks.beforeEach;
		hooks.beforeEach = function () {
			// Ensure the current target is appended to the current fixture
			// eslint-disable-next-line no-jquery/no-global-selector
			$( '#qunit-fixture' ).append( ve.init.target.$element );
			if ( origBeforeEach ) {
				return origBeforeEach.apply( this, arguments );
			}
		};
		origModule( name, hooks );
	};

	// Preserve other properties (only, skip, if, ...)
	Object.keys( origModule ).forEach( ( key ) => {
		wrappedModule[ key ] = origModule[ key ];
	} );

	QUnit.module = wrappedModule;
}
