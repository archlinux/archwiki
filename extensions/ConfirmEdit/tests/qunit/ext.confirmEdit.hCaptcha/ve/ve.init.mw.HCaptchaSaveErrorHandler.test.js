const utils = require( 'ext.confirmEdit.hCaptcha/ext.confirmEdit.hCaptcha/utils.js' );
const hCaptchaSaveErrorHandler = require( 'ext.confirmEdit.hCaptcha/ext.confirmEdit.hCaptcha/ve/ve.init.mw.HCaptchaSaveErrorHandler.js' );

QUnit.module.if( 'ext.confirmEdit.hCaptcha.ve.HCaptchaSaveErrorHandler', mw.loader.getState( 'ext.visualEditor.targetLoader' ), QUnit.newMwEnvironment(), ( hooks ) => {

	const hCaptchaConfig = require( 'ext.confirmEdit.hCaptcha/ext.confirmEdit.hCaptcha/config.json' );
	const hCaptchaOnLoadHandler = require( 'ext.confirmEdit.hCaptcha/ext.confirmEdit.hCaptcha/ve/ve.init.mw.HCaptchaOnLoadHandler.js' );

	hooks.beforeEach( function () {
		this.loadHCaptcha = this.sandbox.stub( utils, 'loadHCaptcha' );

		// In a real environment, initPlugins.js does this for us. However, to avoid
		// side effects, we don't use that method of loading the code we are testing.
		// Therefore, run this ourselves.
		require( 'ext.confirmEdit.hCaptcha/ext.confirmEdit.hCaptcha/ve/ve.init.mw.HCaptcha.js' )();
	} );

	const getMockTarget = ( self, $saveDialogElement ) => ( {
		saveFields: {},
		saveDialog: {
			$element: $saveDialogElement,
			updateSize: self.sandbox.stub(),
			popPending: self.sandbox.stub(),
			clearMessage: self.sandbox.stub(),
			executeAction: self.sandbox.stub(),
			showMessage: ( name, $element ) => {
				$saveDialogElement.append( $element );
			}
		},
		emit: self.sandbox.stub()
	} );

	const getMockWindow = ( self, $qunitFixture ) => {
		const mockWindow = {
			hcaptcha: {
				render: self.sandbox.stub()
			},
			document: {
				body: $qunitFixture[ 0 ]
			}
		};
		mockWindow.hcaptcha.render.returns( 'widget-id' );
		return mockWindow;
	};

	QUnit.test.each( 'process uses loadHCaptcha', {
		'hCaptcha is in invisible mode': {
			invisibleMode: true,
			captchaData: { type: 'hcaptcha' },
			expectedSiteKey: 'generic-site-key'
		},
		'hCaptcha is not in invisible mode': {
			invisibleMode: false,
			captchaData: { type: 'hcaptcha', key: 'generic-site-key-2' },
			expectedSiteKey: 'generic-site-key-2'
		},
		'AbuseFilter hCaptcha error from visualeditoredit API': {
			invisibleMode: false,
			captchaData: { type: 'hcaptcha', key: 'abuse-filter-key', error: 'forceshowcaptcha' },
			expectedSiteKey: 'abuse-filter-key'
		}
	}, async function ( assert, options ) {
		this.loadHCaptcha.returns( Promise.resolve() );

		hCaptchaConfig.HCaptchaInvisibleMode = options.invisibleMode;
		hCaptchaConfig.HCaptchaSiteKey = 'generic-site-key';

		hCaptchaSaveErrorHandler();

		const $qunitFixture = $( '#qunit-fixture' );
		const target = getMockTarget( this, $qunitFixture );
		const mockWindow = getMockWindow( this, $qunitFixture );

		ve.init.mw.HCaptchaSaveErrorHandler.static.window = mockWindow;

		ve.init.mw.HCaptchaSaveErrorHandler.static.process(
			{ visualeditoredit: { edit: { captcha: options.captchaData } } },
			target
		);

		setTimeout( () => {
			assert.deepEqual(
				mockWindow.hcaptcha.render.callCount,
				1,
				'window.hcaptcha.render is called once'
			);
			assert.deepEqual(
				mockWindow.hcaptcha.render.firstCall.args[ 1 ].sitekey,
				options.expectedSiteKey,
				'window.hcaptcha.render is passed the expected sitekey'
			);
			assert.deepEqual(
				ve.init.mw.HCaptchaSaveErrorHandler.static.widgetId,
				'widget-id',
				'widgetId property should be set with the return value of hcaptcha.render'
			);

			// Check that the DOM is as expected
			const $actualHCaptchaContainer = $( '.ext-confirmEdit-visualEditor-hCaptchaContainer', $qunitFixture );
			assert.deepEqual(
				$actualHCaptchaContainer.length,
				1,
				'A hCaptcha container should exist in the DOM'
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
				options.invisibleMode ? 'invisible' : undefined,
				'The hCaptcha widget should be marked as invisible if in invisible mode'
			);
			assert.deepEqual(
				$( '.ext-confirmEdit-hcaptcha-privacy-policy', $actualHCaptchaContainer ).length,
				options.invisibleMode ? 1 : 0,
				'hCaptcha privacy policy text should only be added in invisible mode'
			);
			assert.deepEqual(
				$( '.ext-confirmEdit-hcaptcha-visual-editor-error-handler-warning', $actualHCaptchaContainer ).length,
				options.invisibleMode ? 1 : 0,
				'hCaptcha edit notice should only be added in invisible mode'
			);

			assert.true(
				this.loadHCaptcha.calledOnce,
				'loadHCaptcha is called when by process'
			);
			assert.deepEqual(
				this.loadHCaptcha.firstCall.args,
				[ window, 'visualeditor', { render: 'explicit' } ],
				'loadHCaptcha arguments are as expected'
			);
		} );
	} );

	QUnit.test.each( 'process re-executes hCaptcha if onload handler was already run', {
		'hCaptcha on load handler was not run': {
			onLoadHandlerRun: false
		},
		'hCaptcha on load handler was already run': {
			onLoadHandlerRun: true
		}
	}, async function ( assert, options ) {
		hCaptchaConfig.HCaptchaSiteKey = 'generic-site-key';

		hCaptchaOnLoadHandler();
		hCaptchaSaveErrorHandler();

		ve.init.mw.HCaptchaOnLoadHandler.static.destroyWidget = this.sandbox.stub();
		ve.init.mw.HCaptchaOnLoadHandler.static.shouldRun = () => options.onLoadHandlerRun;

		const $qunitFixture = $( '#qunit-fixture' );
		const target = getMockTarget( this, $qunitFixture );
		const mockWindow = getMockWindow( this, $qunitFixture );

		ve.init.mw.HCaptchaSaveErrorHandler.static.window = mockWindow;

		this.loadHCaptcha.returns( Promise.resolve() );

		ve.init.mw.HCaptchaSaveErrorHandler.static.process(
			{ visualeditoredit: { edit: { captcha: { type: 'hcaptcha' } } } },
			target
		);

		setTimeout( () => {
			assert.deepEqual(
				mockWindow.hcaptcha.render.callCount,
				1,
				'window.hcaptcha.render is called once'
			);

			assert.deepEqual(
				ve.init.mw.HCaptchaOnLoadHandler.static.destroyWidget.callCount,
				1,
				've.init.mw.HCaptchaOnLoadHandler.static.destroyWidget should be called'
			);
			if ( options.onLoadHandlerRun ) {
				assert.deepEqual(
					target.saveDialog.executeAction.callCount,
					1,
					'target.saveDialog.executeAction should be called to automatically restart saving'
				);
				assert.deepEqual(
					target.saveDialog.executeAction.firstCall.args[ 0 ],
					'save',
					'target.saveDialog.executeAction should be called for the save action'
				);
			} else {
				assert.deepEqual(
					target.saveDialog.executeAction.callCount,
					0,
					'target.saveDialog.executeAction should not execute unless onload handler was executed'
				);
			}
		} );
	} );

	QUnit.test.each( 'matchFunction correctly matches', {
		'Captcha is not present': {
			data: { visualeditoredit: { edit: {} } },
			expected: false,
			assertMessage: 'Should not match if captcha is not present in data'
		},
		'Captcha is present, but shown captcha is FancyCaptcha': {
			data: { visualeditoredit: { edit: { captcha: { type: 'fancycaptcha' } } } },
			expected: false,
			assertMessage: 'Should not match if the captcha is FancyCaptcha'
		},
		'hCaptcha captcha is present': {
			data: { visualeditoredit: { edit: { captcha: { type: 'hcaptcha' } } } },
			expected: true,
			assertMessage: 'Should match if the captcha is hCaptcha'
		}
	}, ( assert, options ) => {
		hCaptchaSaveErrorHandler();

		assert.deepEqual(
			ve.init.mw.HCaptchaSaveErrorHandler.static.matchFunction( options.data ),
			options.expected,
			options.assertMessage
		);
	} );
} );
