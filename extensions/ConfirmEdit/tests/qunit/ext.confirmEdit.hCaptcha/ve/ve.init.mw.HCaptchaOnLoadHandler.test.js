const hCaptchaUtils = require( 'ext.confirmEdit.hCaptcha/ext.confirmEdit.hCaptcha/utils.js' );
const hCaptchaOnLoadHandler = require( 'ext.confirmEdit.hCaptcha/ext.confirmEdit.hCaptcha/ve/ve.init.mw.HCaptchaOnLoadHandler.js' );
const hCaptchaConfig = require( 'ext.confirmEdit.hCaptcha/ext.confirmEdit.hCaptcha/config.json' );

QUnit.module.if( 'ext.confirmEdit.hCaptcha.ve.HCaptchaOnLoadHandler', mw.loader.getState( 'ext.visualEditor.targetLoader' ), QUnit.newMwEnvironment(), ( hooks ) => {

	hooks.beforeEach( function () {
		mw.config.set( 'wgConfirmEditHCaptchaSiteKey', 'test-site-key' );

		sinon.replace( ve.init.target, 'surface', {} );
		sinon.replace( hCaptchaConfig, 'HCaptchaSiteKey', 'test-default-site-key' );
		sinon.replace( hCaptchaConfig, 'HCaptchaInvisibleMode', false );

		this.loadHCaptcha = sinon.stub( hCaptchaUtils, 'loadHCaptcha' );
		this.executeHCaptcha = sinon.stub( hCaptchaUtils, 'executeHCaptcha' );

		this.window = {
			hcaptcha: {
				render: sinon.stub()
			},
			document: {
				body: $( '#qunit-fixture' )
			}
		};

		// In a real environment, initPlugins.js does this for us. However, to avoid
		// side effects, we don't use that method of loading the code we are testing.
		// Therefore, run this ourselves.
		require( 'ext.confirmEdit.hCaptcha/ext.confirmEdit.hCaptcha/ve/ve.init.mw.HCaptcha.js' )();
	} );
	hooks.afterEach( () => {
		ve.init.mw.HCaptchaOnLoadHandler.static.readyPromise = null;
	} );

	QUnit.test( 'transact event in VisualEditor surface causes hCaptcha load once', function ( assert ) {
		mw.config.set( 'wgConfirmEditCaptchaNeededForGenericEdit', 'hcaptcha' );
		mw.config.set( 'wgConfirmEditHCaptchaVisualEditorOnLoadIntegrationEnabled', true );

		this.loadHCaptcha.returns( Promise.resolve() );

		const fakeDocument = new OO.EventEmitter();

		// Mock the surface to allow the code we are testing to interact with
		// the fake VisualEditor editor document created above
		ve.init.target.surface = {
			getModel: () => ( {
				getDocument: () => fakeDocument
			} )
		};

		hCaptchaOnLoadHandler();

		ve.init.mw.HCaptchaOnLoadHandler.static.onActivationComplete( ve.init.target );

		assert.true(
			this.loadHCaptcha.notCalled,
			'loadHCaptcha is not called before transact event is fired'
		);

		// Trigger the transact event multiple times so we can test loading hCaptcha only happens
		// once for all of these events
		fakeDocument.emit( 'transact' );
		fakeDocument.emit( 'transact' );
		fakeDocument.emit( 'transact' );

		assert.true(
			this.loadHCaptcha.calledOnce,
			'loadHCaptcha is called once after transact event is fired'
		);
		assert.deepEqual(
			this.loadHCaptcha.firstCall.args,
			[ window, 'visualeditor', { render: 'explicit' } ],
			'loadHCaptcha arguments are as expected'
		);
	} );

	QUnit.test.each( 'shouldRun correctly matches', {
		'wgConfirmEditCaptchaNeededForGenericEdit is undefined': {
			neededForEditConfigVariableValue: undefined,
			enabledConfigVariableValue: undefined,
			expected: false
		},
		'wgConfirmEditCaptchaNeededForGenericEdit is false': {
			neededForEditConfigVariableValue: false,
			enabledConfigVariableValue: undefined,
			expected: false
		},
		'wgConfirmEditCaptchaNeededForGenericEdit is fancycaptcha': {
			neededForEditConfigVariableValue: 'fancycaptcha',
			enabledConfigVariableValue: undefined,
			expected: false
		},
		'wgConfirmEditCaptchaNeededForGenericEdit is hcaptcha and integration is disabled': {
			neededForEditConfigVariableValue: 'hcaptcha',
			enabledConfigVariableValue: false,
			expected: false
		},
		'wgConfirmEditCaptchaNeededForGenericEdit is hcaptcha and integration is enabled': {
			neededForEditConfigVariableValue: 'hcaptcha',
			enabledConfigVariableValue: true,
			expected: true
		}
	}, ( assert, options ) => {
		mw.config.set( 'wgConfirmEditCaptchaNeededForGenericEdit', options.neededForEditConfigVariableValue );
		mw.config.set( 'wgConfirmEditHCaptchaVisualEditorOnLoadIntegrationEnabled', options.enabledConfigVariableValue );

		hCaptchaOnLoadHandler();

		assert.deepEqual(
			ve.init.mw.HCaptchaOnLoadHandler.static.shouldRun(),
			options.expected,
			'::shouldRun returns expected value'
		);
	} );

	QUnit.test( 'renderHCaptcha is called when hCaptcha is not required for an edit', async function ( assert ) {
		mw.config.set( 'wgConfirmEditCaptchaNeededForGenericEdit', 'fancycaptcha' );

		hCaptchaOnLoadHandler();

		await ve.init.mw.HCaptchaOnLoadHandler.static.renderHCaptcha( this.window, ve.init.target );

		assert.deepEqual(
			this.loadHCaptcha.callCount,
			0,
			'loadHCaptcha is not called when hCaptcha is not required for an edit'
		);
	} );

	/**
	 * Common helper method used to set up the ve.init.target.saveDialog method.
	 *
	 * @param {this} self The `this` of the calling method
	 * @return {void}
	 */
	function setupSaveDialog( self ) {
		const $qunitFixture = $( '#qunit-fixture' );

		// Append a fake hCaptcha container to the DOM to test that is gets cleared out
		// These can exist if the hCaptcha widget has already been rendered through some
		// other method, like the API error handler or if the save dialog is closed and opened.
		const $fakeHCaptchaContainer = $( '<div>' );
		$fakeHCaptchaContainer.addClass( 'ext-confirmEdit-visualEditor-hCaptchaContainer' );
		$qunitFixture.append( $fakeHCaptchaContainer );

		const $saveDialogFooter = $( '<div>' );
		$saveDialogFooter.addClass( 've-ui-mwSaveDialog-foot' );
		$qunitFixture.append( $saveDialogFooter );

		// Mock the saveDialog to allow us to make it be the qunit test fixture element
		ve.init.target.saveDialog = {
			$element: $qunitFixture,
			updateSize: self.sandbox.stub()
		};
		ve.init.target.saveFields = {};
	}

	/**
	 * Performs assertions that are the same for any call to renderHCaptcha,
	 * regardless if the `loadHCaptcha` call returns rejected or fulfilled
	 * promise.
	 *
	 * @param {*} assert QUnit assert object
	 * @param {this} self The `this` of the calling method
	 * @param {boolean} invisibleMode Is hCaptcha in invisible mode
	 * @param {boolean} loadWasSuccessful Whether the `loadHCaptcha` call returned a fulfilled or rejected promise
	 * @return {void}
	 */
	function commonPostRenderHCaptchaAssertions( assert, self, invisibleMode, loadWasSuccessful ) {
		// Check loadHCaptcha was called
		assert.deepEqual(
			self.loadHCaptcha.callCount,
			1,
			'loadHCaptcha is called once'
		);
		assert.deepEqual(
			self.loadHCaptcha.firstCall.args,
			[ window, 'visualeditor', { render: 'explicit' } ],
			'loadHCaptcha is called with the correct arguments'
		);

		// Check saveDialog.updateSize() was called to make the dialog not have a vertical scroll
		assert.true(
			ve.init.target.saveDialog.updateSize.callCount > 0,
			've.init.target.saveDialog.updateSize should be called at least once'
		);

		// Check that the DOM is as expected
		const $actualHCaptchaContainer = $( '.ext-confirmEdit-visualEditor-hCaptchaContainer' );
		assert.deepEqual(
			$actualHCaptchaContainer.length,
			1,
			'Only one hCaptcha container should exist in the DOM'
		);
		const $actualHCaptchaWidgetContainer = $(
			'.ext-confirmEdit-visualEditor-hCaptchaWidgetContainer',
			$actualHCaptchaContainer
		);
		assert.deepEqual(
			$actualHCaptchaWidgetContainer.length,
			1,
			'Only one hCaptcha widget container should exist in the DOM'
		);
		assert.deepEqual(
			$actualHCaptchaWidgetContainer.attr( 'data-size' ),
			invisibleMode && loadWasSuccessful ? 'invisible' : undefined,
			'The hCaptcha widget should be marked as invisible if in invisible mode'
		);
		assert.deepEqual(
			$( '.ext-confirmEdit-hcaptcha-privacy-policy', $actualHCaptchaContainer ).length,
			invisibleMode ? 1 : 0,
			'hCaptcha privacy policy text should only be added in invisible mode'
		);
	}

	QUnit.test.each( 'renderHCaptcha is called for successful render', {
		'hCaptcha is in invisible mode': {
			invisibleMode: true,
			enterpriseMode: false,
			removeMwConfigSiteKey: false,
			forceShowCaptcha: false
		},
		'hCaptcha is in invisible and enterprise mode': {
			invisibleMode: true,
			enterpriseMode: true,
			removeMwConfigSiteKey: false,
			forceShowCaptcha: false
		},
		'hCaptcha is not in invisible mode': {
			invisibleMode: false,
			enterpriseMode: false,
			removeMwConfigSiteKey: false,
			forceShowCaptcha: false
		},
		'hCaptcha is in invisible and enterprise mode when force showing captcha': {
			invisibleMode: true,
			enterpriseMode: true,
			removeMwConfigSiteKey: false,
			forceShowCaptcha: true
		},
		'hCaptcha falls back to config.json if mw.config does not have site key': {
			invisibleMode: false,
			enterpriseMode: false,
			removeMwConfigSiteKey: true,
			forceShowCaptcha: false
		}
	}, async function ( assert, options ) {
		mw.config.set( 'wgConfirmEditCaptchaNeededForGenericEdit', 'hcaptcha' );
		mw.config.set( 'wgConfirmEditHCaptchaVisualEditorOnLoadIntegrationEnabled', true );
		mw.config.set( 'wgConfirmEditForceShowCaptcha', options.forceShowCaptcha );

		this.loadHCaptcha.returns( Promise.resolve() );
		this.window.hcaptcha.render.returns( 'widget-id' );

		hCaptchaConfig.HCaptchaInvisibleMode = options.invisibleMode;
		hCaptchaConfig.HCaptchaEnterprise = options.enterpriseMode;

		if ( options.removeMwConfigSiteKey ) {
			mw.config.set( 'wgConfirmEditHCaptchaSiteKey', null );
		}

		setupSaveDialog( this );

		hCaptchaOnLoadHandler();

		assert.deepEqual(
			ve.init.mw.HCaptchaOnLoadHandler.static.widgetId,
			null,
			'widgetId property should be null before renderHCaptcha call'
		);

		await ve.init.mw.HCaptchaOnLoadHandler.static.renderHCaptcha( this.window, ve.init.target );
		commonPostRenderHCaptchaAssertions( assert, this, options.invisibleMode, true );

		// Check that hcaptcha.render is called
		assert.deepEqual(
			this.window.hcaptcha.render.callCount,
			1,
			'window.hcaptcha.render is called once'
		);
		const actualRenderCallArgs = this.window.hcaptcha.render.firstCall.args;
		// eslint-disable-next-line no-jquery/no-class-state
		const isFirstRenderCallArgTheContainer = $( actualRenderCallArgs[ 0 ] ).hasClass( 'ext-confirmEdit-visualEditor-hCaptchaWidgetContainer' );
		assert.true(
			isFirstRenderCallArgTheContainer,
			'window.hcaptcha.render was provided with the expected container'
		);
		assert.deepEqual(
			actualRenderCallArgs[ 1 ].sitekey,
			!options.removeMwConfigSiteKey ? 'test-site-key' : 'test-default-site-key',
			'window.hcaptcha.render was provided with the expected sitekey'
		);
		assert.deepEqual(
			ve.init.mw.HCaptchaOnLoadHandler.static.widgetId,
			'widget-id',
			'widgetId property should be set with the return value of hcaptcha.render'
		);

		const $hCaptchaChallengeContainer = $( '.ext-confirmEdit-hCaptcha-challengeContainer' );
		if ( options.enterpriseMode ) {
			assert.deepEqual(
				$hCaptchaChallengeContainer.length,
				1,
				'hCaptcha challenge container exists when using enterprise mode'
			);
		} else {
			assert.deepEqual(
				$hCaptchaChallengeContainer.length,
				0,
				'hCaptcha challenge container does not exist in enterprise mode'
			);
		}

		if ( options.forceShowCaptcha ) {
			assert.deepEqual(
				ve.init.target.saveFields.wgConfirmEditForceShowCaptcha(),
				true,
				'wgConfirmEditForceShowCaptcha should be set in save fields if forcing captcha'
			);
		} else {
			assert.deepEqual(
				Object.hasOwn( ve.init.target.saveFields, 'wgConfirmEditForceShowCaptcha' ),
				false,
				'wgConfirmEditForceShowCaptcha should not be set in save fields if not forcing captcha'
			);
		}

		// Check there is no error message displayed
		const $actualHCaptchaContainer = $( '.ext-confirmEdit-visualEditor-hCaptchaContainer' );
		const $hcaptchaErrorWidget = $( '.cdx-message--error', $actualHCaptchaContainer );
		assert.deepEqual(
			$hcaptchaErrorWidget.length,
			1,
			'hCaptcha error message widget exists'
		);
		assert.deepEqual(
			$hcaptchaErrorWidget.css( 'display' ),
			'none',
			'hCaptcha error message widget should be hidden'
		);
	} );

	QUnit.test.each( 'renderHCaptcha is called and hCaptcha SDK fails to load', {
		'hCaptcha is in invisible mode': {
			invisibleMode: true
		},
		'hCaptcha is not in invisible mode': {
			invisibleMode: false
		}
	}, async function ( assert, options ) {
		mw.config.set( 'wgConfirmEditCaptchaNeededForGenericEdit', 'hcaptcha' );
		mw.config.set( 'wgConfirmEditHCaptchaVisualEditorOnLoadIntegrationEnabled', true );

		this.loadHCaptcha.returns( Promise.reject( 'generic-error' ) );

		hCaptchaConfig.HCaptchaInvisibleMode = options.invisibleMode;

		setupSaveDialog( this );

		hCaptchaOnLoadHandler();

		assert.deepEqual(
			ve.init.mw.HCaptchaOnLoadHandler.static.widgetId,
			null,
			'widgetId property should be null before renderHCaptcha call'
		);

		await assert.rejects(
			ve.init.mw.HCaptchaOnLoadHandler.static.renderHCaptcha( this.window, ve.init.target )
		);

		commonPostRenderHCaptchaAssertions( assert, this, options.invisibleMode, false );

		// Check that hcaptcha.render is not called, as the SDK loading failed
		assert.true(
			this.window.hcaptcha.render.notCalled,
			'window.hcaptcha.render is never called'
		);
		assert.deepEqual(
			ve.init.mw.HCaptchaOnLoadHandler.static.widgetId,
			null,
			'widgetId property should be null as window.hcaptcha.render was not called'
		);

		// Check there is an error message displayed
		const $actualHCaptchaContainer = $( '.ext-confirmEdit-visualEditor-hCaptchaContainer' );
		const $hcaptchaErrorWidget = $( '.cdx-message--error', $actualHCaptchaContainer );
		assert.deepEqual(
			1,
			$hcaptchaErrorWidget.length,
			'hCaptcha error message widget exists'
		);
		assert.notDeepEqual(
			$hcaptchaErrorWidget.css( 'display' ),
			'none',
			'hCaptcha error message widget should be visible'
		);
		assert.deepEqual(
			'(hcaptcha-generic-error)',
			$hcaptchaErrorWidget.text(),
			'hCaptcha error message widget has the error message'
		);
	} );

	QUnit.test.each( 'onSaveOptionsProcess is called', {
		'No execution if widgetId is null': {
			widgetId: null,
			executeHCaptchaPromiseFactory: () => Promise.reject( 'should-be-ignored' ),
			existingResponseToken: null,
			returnedPromiseShouldResolve: true,
			hCaptchaShouldBeExecuted: false,
			executionShouldSucceed: false
		},
		'Returns early without new execution if response token already held': {
			widgetId: 'mockWidgetId',
			executeHCaptchaPromiseFactory: () => Promise.reject( 'should-be-ignored' ),
			existingResponseToken: 'test-response',
			returnedPromiseShouldResolve: true,
			hCaptchaShouldBeExecuted: false,
			executionShouldSucceed: true
		},
		'Successful execution': {
			widgetId: 'mockWidgetId',
			executeHCaptchaPromiseFactory: () => Promise.resolve( 'test-response' ),
			existingResponseToken: null,
			returnedPromiseShouldResolve: true,
			hCaptchaShouldBeExecuted: true,
			executionShouldSucceed: true
		},
		'Failed execution': {
			widgetId: 'mockWidgetId',
			executeHCaptchaPromiseFactory: () => Promise.reject( 'test-error' ),
			existingResponseToken: null,
			returnedPromiseShouldResolve: false,
			hCaptchaShouldBeExecuted: true,
			executionShouldSucceed: false
		}
	}, async function ( assert, options ) {
		this.executeHCaptcha.callsFake( () => options.executeHCaptchaPromiseFactory() );

		const executionSuccessHook = mw.hook( 'confirmEdit.hCaptcha.executionSuccess' );
		const executionSuccessHookFireSpy = this.sandbox.spy( executionSuccessHook, 'fire' );

		hCaptchaOnLoadHandler();

		ve.init.mw.HCaptchaOnLoadHandler.static.widgetId = options.widgetId;

		if ( options.existingResponseToken ) {
			ve.init.mw.HCaptchaOnLoadHandler.static.hCaptchaResponseToken = options.existingResponseToken;
		}

		let showSaveErrorCalled = false;
		const mockTarget = {
			showSaveError: () => {
				showSaveErrorCalled = true;
			},
			saveFields: {}
		};

		const returnedPromise = ve.init.mw.HCaptchaOnLoadHandler.static.onSaveOptionsProcess(
			mockTarget
		);
		if ( options.returnedPromiseShouldResolve ) {
			await returnedPromise;
		} else {
			await assert.rejects( returnedPromise );
		}

		if ( options.hCaptchaShouldBeExecuted ) {
			assert.deepEqual(
				this.executeHCaptcha.callCount,
				1,
				'executeHCaptcha is called once when widgetId is set'
			);
			assert.deepEqual(
				this.executeHCaptcha.firstCall.args,
				[ window, 'mockWidgetId', 'visualeditor' ],
				'executeHCaptcha call uses the expected arguments'
			);
		} else {
			assert.deepEqual(
				this.executeHCaptcha.callCount,
				0,
				'executeHCaptcha is not called if hCaptcha not rendered'
			);
		}

		assert.deepEqual(
			executionSuccessHookFireSpy.callCount,
			options.hCaptchaShouldBeExecuted && options.executionShouldSucceed ? 1 : 0,
			'confirmEdit.hCaptcha.executionSuccess hook should only be fired on successful execution'
		);
		if ( options.widgetId ) {
			assert.deepEqual(
				showSaveErrorCalled,
				!options.executionShouldSucceed,
				'showSaveError should only be called if the execution failed'
			);
		}

		if ( options.executionShouldSucceed ) {
			assert.deepEqual(
				mockTarget.saveFields.wpCaptchaWord(),
				'test-response',
				'wpCaptchaWord field should be set as the hCaptcha response token'
			);
		}
	} );

	QUnit.test( 'destroyWidget resets internal state and removes hCaptcha widget', ( assert ) => {
		hCaptchaOnLoadHandler();

		ve.init.mw.HCaptchaOnLoadHandler.static.widgetId = 'test-widget-id';
		ve.init.mw.HCaptchaOnLoadHandler.static.hCaptchaResponseToken = 'test-response-token';

		const $fakeHCaptchaContainer = $( '<div>' );
		$fakeHCaptchaContainer.addClass( 'ext-confirmEdit-visualEditor-hCaptchaOnLoadContainer' );

		const $qunitFixture = $( '#qunit-fixture' );
		$qunitFixture.append( $fakeHCaptchaContainer );

		const mockTarget = {
			saveDialog: {
				$element: $qunitFixture
			}
		};
		ve.init.mw.HCaptchaOnLoadHandler.static.destroyWidget( mockTarget );

		assert.deepEqual(
			ve.init.mw.HCaptchaOnLoadHandler.static.widgetId,
			null,
			'widgetId should be cleared after call to destroyWidget'
		);
		assert.deepEqual(
			ve.init.mw.HCaptchaOnLoadHandler.static.hCaptchaResponseToken,
			null,
			'hCaptchaResponseToken should be cleared after call to destroyWidget'
		);
		assert.deepEqual(
			$qunitFixture.find( '.ext-confirmEdit-visualEditor-hCaptchaOnLoadContainer' ).length,
			0,
			'hCaptcha widget element should be cleared after call to destroyWidget'
		);
	} );
} );
