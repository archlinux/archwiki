const utils = require( 'ext.confirmEdit.hCaptcha/ext.confirmEdit.hCaptcha/utils.js' );
const hCaptchaSaveErrorHandler = require( 'ext.confirmEdit.hCaptcha/ext.confirmEdit.hCaptcha/ve/ve.init.mw.HCaptchaSaveErrorHandler.js' );

QUnit.module.if( 'VisualEditor', mw.loader.getModuleNames().includes( 'ext.visualEditor.targetLoader' ), () => {
	QUnit.module( 'ext.confirmEdit.hCaptcha.ve.HCaptchaSaveErrorHandler', QUnit.newMwEnvironment( {
		beforeEach() {
			this.loadHCaptcha = this.sandbox.stub( utils, 'loadHCaptcha' );

			// In a real environment, initPlugins.js does this for us. However, to avoid
			// side effects, we don't use that method of loading the code we are testing.
			// Therefore, run this ourselves.
			require( 'ext.confirmEdit.hCaptcha/ext.confirmEdit.hCaptcha/ve/ve.init.mw.HCaptcha.js' )();
		},
		afterEach() {
			this.loadHCaptcha.restore();
		}
	} ) );

	QUnit.test( 'getReadyPromise uses loadHCaptcha', function ( assert ) {
		this.loadHCaptcha.returns( Promise.resolve() );
		hCaptchaSaveErrorHandler();

		ve.init.mw.HCaptchaSaveErrorHandler.static.getReadyPromise();

		assert.true(
			this.loadHCaptcha.calledOnce,
			'loadHCaptcha is called when getReadyPromise is called'
		);
		assert.deepEqual(
			this.loadHCaptcha.firstCall.args,
			[ window, 'visualeditor', { render: 'explicit' } ],
			'loadHCaptcha arguments are as expected'
		);
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
