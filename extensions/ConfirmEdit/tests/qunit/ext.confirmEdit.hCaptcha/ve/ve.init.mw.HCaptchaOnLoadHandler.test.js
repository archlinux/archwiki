const hCaptchaUtils = require( 'ext.confirmEdit.hCaptcha/ext.confirmEdit.hCaptcha/utils.js' );
const hCaptchaOnLoadHandler = require( 'ext.confirmEdit.hCaptcha/ext.confirmEdit.hCaptcha/ve/ve.init.mw.HCaptchaOnLoadHandler.js' );
const hCaptchaConfig = require( 'ext.confirmEdit.hCaptcha/ext.confirmEdit.hCaptcha/config.json' );

QUnit.module.if( 'VisualEditor', mw.loader.getModuleNames().includes( 'ext.visualEditor.targetLoader' ), () => {
	QUnit.module( 'ext.confirmEdit.hCaptcha.ve.HCaptchaOnLoadHandler', QUnit.newMwEnvironment( {
		beforeEach() {
			this.loadHCaptcha = this.sandbox.stub( hCaptchaUtils, 'loadHCaptcha' );
			mw.config.set( 'wgConfirmEditHCaptchaSiteKey', 'test-site-key' );

			this.origVisualEditorSurface = ve.init.target.surface;
			ve.init.target.surface = {};

			this.window = {
				hcaptcha: {
					render: this.sandbox.stub()
				}
			};

			this.origSiteKey = hCaptchaConfig.HCaptchaSiteKey;
			this.origInvisibleMode = hCaptchaConfig.HCaptchaInvisibleMode;

			hCaptchaConfig.HCaptchaSiteKey = 'test-default-site-key';
			hCaptchaConfig.HCaptchaInvisibleMode = false;

			// In a real environment, initPlugins.js does this for us. However, to avoid
			// side effects, we don't use that method of loading the code we are testing.
			// Therefore, run this ourselves.
			require( 'ext.confirmEdit.hCaptcha/ext.confirmEdit.hCaptcha/ve/ve.init.mw.HCaptcha.js' )();
		},
		afterEach() {
			this.loadHCaptcha.restore();
			mw.config.set( 'wgConfirmEditHCaptchaSiteKey', '' );
			ve.init.mw.HCaptchaOnLoadHandler.static.readyPromise = null;

			ve.init.target.surface = this.origVisualEditorSurface;

			hCaptchaConfig.HCaptchaSiteKey = this.origSiteKey;
			hCaptchaConfig.HCaptchaInvisibleMode = this.origInvisibleMode;
		}
	} ) );

	QUnit.test( 'transact event in VisualEditor surface causes hCaptcha load once', function ( assert ) {
		mw.config.set( 'wgConfirmEditCaptchaNeededForGenericEdit', 'hcaptcha' );
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

		ve.init.mw.HCaptchaOnLoadHandler.static.onActivationComplete();

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
			configVariableValue: undefined,
			expected: false
		},
		'wgConfirmEditCaptchaNeededForGenericEdit is false': {
			configVariableValue: false,
			expected: false
		},
		'wgConfirmEditCaptchaNeededForGenericEdit is fancycaptcha': {
			configVariableValue: 'fancycaptcha',
			expected: false
		},
		'wgConfirmEditCaptchaNeededForGenericEdit is hcaptcha': {
			configVariableValue: 'hcaptcha',
			expected: true
		}
	}, ( assert, options ) => {
		mw.config.set( 'wgConfirmEditCaptchaNeededForGenericEdit', options.configVariableValue );

		hCaptchaOnLoadHandler();

		assert.deepEqual(
			ve.init.mw.HCaptchaOnLoadHandler.static.shouldRun(),
			options.expected,
			'::shouldRun returns expected value'
		);
	} );

	QUnit.test( 'renderHCaptcha is called when hCaptcha is not required for an edit', function ( assert ) {
		mw.config.set( 'wgConfirmEditCaptchaNeededForGenericEdit', 'fancycaptcha' );

		hCaptchaOnLoadHandler();

		return ve.init.mw.HCaptchaOnLoadHandler.static.renderHCaptcha( this.window ).then(
			() => {
				assert.deepEqual(
					this.loadHCaptcha.callCount,
					0,
					'loadHCaptcha is not called when hCaptcha is not required for an edit'
				);
			},
			() => assert.false( true, 'renderHCaptcha should not return a rejected promise' )
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
	}

	/**
	 * Performs assertions that are the same for any call to renderHCaptcha,
	 * regardless if the `loadHCaptcha` call returns rejected or fulfilled
	 * promise.
	 *
	 * @param {*} assert QUnit assert object
	 * @param {this} self The `this` of the calling method
	 * @param {boolean} invisibleMode Is hCaptcha in invisible mode
	 * @return {void}
	 */
	function commonPostRenderHCaptchaAssertions( assert, self, invisibleMode ) {
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
		assert.deepEqual(
			$( '.ext-confirmEdit-visualEditor-hCaptchaWidgetContainer', $actualHCaptchaContainer ).length,
			1,
			'Only one hCaptcha widget container should exist in the DOM'
		);
		assert.deepEqual(
			$( '.ext-confirmEdit-hcaptcha-privacy-policy', $actualHCaptchaContainer ).length,
			invisibleMode ? 1 : 0,
			'hCaptcha privacy policy text should only be added in invisible mode'
		);
	}

	QUnit.test.each( 'renderHCaptcha is called for successful render', {
		'hCaptcha is in invisible mode': {
			invisibleMode: true
		},
		'hCaptcha is not in invisible mode': {
			invisibleMode: false
		}
	}, function ( assert, options ) {
		mw.config.set( 'wgConfirmEditCaptchaNeededForGenericEdit', 'hcaptcha' );

		this.loadHCaptcha.returns( Promise.resolve() );
		this.window.hcaptcha.render.returns( 'widget-id' );

		hCaptchaConfig.HCaptchaInvisibleMode = options.invisibleMode;

		setupSaveDialog( this );

		hCaptchaOnLoadHandler();

		assert.deepEqual(
			ve.init.mw.HCaptchaOnLoadHandler.static.widgetId,
			null,
			'widgetId property should be null before renderHCaptcha call'
		);

		return ve.init.mw.HCaptchaOnLoadHandler.static.renderHCaptcha( this.window ).then(
			() => {
				commonPostRenderHCaptchaAssertions( assert, this, options.invisibleMode );

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
					actualRenderCallArgs[ 1 ],
					{ sitekey: 'test-site-key' },
					'window.hcaptcha.render was provided with the expected configuration values'
				);
				assert.deepEqual(
					ve.init.mw.HCaptchaOnLoadHandler.static.widgetId,
					'widget-id',
					'widgetId property should be set with the return value of hcaptcha.render'
				);

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
			},
			() => assert.true( false, 'renderHCaptcha should not return a rejected promise' )
		);
	} );

	QUnit.test.each( 'renderHCaptcha is called and hCaptcha SDK fails to load', {
		'hCaptcha is in invisible mode': {
			invisibleMode: true
		},
		'hCaptcha is not in invisible mode': {
			invisibleMode: false
		}
	}, function ( assert, options ) {
		mw.config.set( 'wgConfirmEditCaptchaNeededForGenericEdit', 'hcaptcha' );

		this.loadHCaptcha.returns( Promise.reject( 'generic-error' ) );

		hCaptchaConfig.HCaptchaInvisibleMode = options.invisibleMode;

		setupSaveDialog( this );

		hCaptchaOnLoadHandler();

		assert.deepEqual(
			ve.init.mw.HCaptchaOnLoadHandler.static.widgetId,
			null,
			'widgetId property should be null before renderHCaptcha call'
		);

		return ve.init.mw.HCaptchaOnLoadHandler.static.renderHCaptcha( this.window ).then(
			() => assert.true( false, 'renderHCaptcha should not return a fulfilled promise' ),
			() => {
				commonPostRenderHCaptchaAssertions( assert, this, options.invisibleMode );

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
			}
		);
	} );
} );
