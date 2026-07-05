const initMobileFrontend = require( 'ext.confirmEdit.hCaptcha/ext.confirmEdit.hCaptcha/mobileFrontend/initMobileFrontend.js' );

QUnit.module(
	'ext.confirmEdit.hCaptcha.mobileFrontend.initMobileFrontend',
	QUnit.newMwEnvironment( {
		beforeEach() {
			this.waitOneTick = async () => await new Promise( ( resolve ) => {
				setTimeout( resolve );
			} );

			this.realMWHook = mw.hook;
			this.mfHookName =
				( method ) => `mobileFrontend.sourceEditor.${ method }`;
			this.ceHookName =
				( method ) => `confirmEdit.hCaptcha.${ method }`;

			this.logError = this.sandbox.stub( mw.errorLogger, 'logError' );
			this.logWarn = this.sandbox.stub( mw.log, 'warn' );

			// Provide a fake registry so that hooks set in one
			// test run are not kept when the next one runs
			const registry = {};

			mw.hook = function ( name ) {
				registry[ name ] = registry[ name ] || {
					handlers: [],
					add( fn ) {
						this.handlers.push( fn );
						return this;
					},
					fire( ...args ) {
						this.handlers.forEach(
							( fn ) => fn( ...args )
						);
						return this;
					}
				};

				return registry[ name ];
			};

			// We do not want to add real script elements to the page or
			// interact with the real hCaptcha, so stub the code that does this
			// for tests.
			this.window = {
				hcaptcha: {
					render: this.sandbox.stub(),
					execute: this.sandbox.stub()
				},
				document: {
					head: {
						appendChild: this.sandbox.stub()
					},
					createElement: this.sandbox.stub(),
					querySelectorAll: this.sandbox.stub()
				}
			};

			this.window.document.querySelectorAll.returns( [] );

			// loadHCaptcha() is a function imported by initMobileFrontend.js
			// from utils.js, and we can't add a stub for it here easily so,
			// instead of that, tests verify whether the side effects of calling
			// it had happened (specifically, that createElement was called to
			// create the script tag to add to the document).
			this.window.document.createElement.returns(
				document.createElement( 'script' )
			);

			this.trimHTML = ( html ) => html.split( '\n' )
				.map( ( line ) => line.trim() )
				.join( '\n' );

			this.hCaptchaExpectedFormHTML = this.trimHTML(
				`<div id="h-captcha-container">
					<div id="h-captcha" class="h-captcha" data-size="invisible"
						data-sitekey="{{hCaptchaSiteKey}}"></div>
					<div class="ext-confirmEdit-captcha-privacy-policy license">
						{{{hCaptchaLicenseText}}}
					</div>
				</div>`
			);
		},
		afterEach: function () {
			mw.hook = this.realMWHook;
		}
	} )
);

// Tests for mobileFrontend.sourceEditor.getDefaultOptions

QUnit.test(
	'getDefaultOptions when hCaptcha is enabled for MobileFrontend',
	function ( assert ) {
		initMobileFrontend(
			'mobilefrontend-editor',
			{
				HCaptchaEnabledInMobileFrontend: true,
				MobileHCaptchaAbuseFilterEnabled: true,
				HCaptchaSiteKey: 'hCaptcha-site-key'
			},
			this.window
		);

		const e = this.sandbox.stub();
		e.setDefaults = this.sandbox.stub();
		e.defaults = {
			foo: 'bar'
		};

		mw.hook( this.mfHookName( 'getDefaultOptions' ) ).fire( e );

		assert.true(
			e.setDefaults.calledOnce,
			"The hook handler should've used the event object to update defaults"
		);
		assert.true(
			e.setDefaults.calledWith( {
				foo: 'bar',
				hCaptchaLicenseText: '(hcaptcha-privacy-policy)',
				hCaptchaSiteKey: 'hCaptcha-site-key'
			} ),
			"The hook handler should've set new default values on the event object"
		);
	}
);

QUnit.test(
	'getDefaultOptions when hCaptcha is not enabled for MobileFrontend',
	function ( assert ) {
		initMobileFrontend(
			'mobilefrontend-editor',
			{
				HCaptchaEnabledInMobileFrontend: false,
				MobileHCaptchaAbuseFilterEnabled: false,
				HCaptchaSiteKey: 'hCaptcha-site-key'
			}
		);

		const e = this.sandbox.stub();
		e.setDefaults = this.sandbox.stub();
		e.defaults = {
			foo: 'bar'
		};

		mw.hook( this.mfHookName( 'getDefaultOptions' ) ).fire( e );

		assert.false(
			e.setDefaults.called,
			'The hook handler should not have been called'
		);
	}
);

// Tests for mobileFrontend.sourceEditor.getSavePanelTemplateSource

QUnit.test(
	'getSavePanelTemplateSource when hCaptcha is enabled for MobileFrontend',
	function ( assert ) {
		initMobileFrontend(
			'mobilefrontend-editor',
			{
				HCaptchaEnabledInMobileFrontend: true,
				MobileHCaptchaAbuseFilterEnabled: true,
				HCaptchaSiteKey: 'hCaptcha-site-key'
			},
			this.window
		);

		const e = this.sandbox.stub();
		e.setTemplate = this.sandbox.stub();

		mw.hook( this.mfHookName( 'getSavePanelTemplateSource' ) ).fire( e );

		assert.true(
			e.setTemplate.calledOnce,
			"The hook handler should've used the event object to change the template"
		);
		assert.true(
			this.trimHTML( e.setTemplate.firstCall.args[ 0 ] ) ===
			this.hCaptchaExpectedFormHTML,
			"The hook handler should've set a new template for the save panel"
		);
	}
);

QUnit.test(
	'getSavePanelTemplateSource when hCaptcha is not enabled for MobileFrontend',
	function ( assert ) {
		initMobileFrontend(
			'mobilefrontend-editor',
			{
				HCaptchaEnabledInMobileFrontend: false,
				MobileHCaptchaAbuseFilterEnabled: true,
				HCaptchaSiteKey: 'hCaptcha-site-key'
			},
			this.window
		);

		const e = this.sandbox.stub();
		e.setTemplate = this.sandbox.stub();

		mw.hook( this.mfHookName( 'getSavePanelTemplateSource' ) ).fire( e );

		assert.false(
			e.setTemplate.called,
			'The hook handler should not have been called'
		);
	}
);

// Tests for mobileFrontend.sourceEditor.getCaptchaPanelTemplateSource

QUnit.test(
	'getCaptchaPanelTemplateSource when hCaptcha is enabled for MobileFrontend',
	function ( assert ) {
		initMobileFrontend(
			'mobilefrontend-editor',
			{
				HCaptchaEnabledInMobileFrontend: true,
				MobileHCaptchaAbuseFilterEnabled: true,
				HCaptchaSiteKey: 'hCaptcha-site-key'
			},
			this.window
		);

		const e = this.sandbox.stub();
		e.setTemplate = this.sandbox.stub();

		mw.hook( this.mfHookName( 'getCaptchaPanelTemplateSource' ) ).fire( e );

		assert.true(
			e.setTemplate.calledOnce,
			"The hook handler should've used the event object to change the template"
		);
		assert.true(
			e.setTemplate.calledWith( '', false ),
			"The hook handler should've set up an empty template for the captcha panel"
		);
	}
);

QUnit.test(
	'getCaptchaPanelTemplateSource when hCaptcha is not enabled for MobileFrontend',
	function ( assert ) {
		initMobileFrontend(
			'mobilefrontend-editor',
			{
				HCaptchaEnabledInMobileFrontend: false,
				MobileHCaptchaAbuseFilterEnabled: true,
				HCaptchaSiteKey: 'hCaptcha-site-key'
			}
		);

		const e = this.sandbox.stub();
		e.setTemplate = this.sandbox.stub();

		mw.hook( this.mfHookName( 'getCaptchaPanelTemplateSource' ) ).fire( e );

		assert.false(
			e.setTemplate.called,
			'The hook handler should not have been called'
		);
	}
);

// Tests for mobileFrontend.sourceEditor.preRenderFinished

QUnit.test(
	'preRenderFinished when hCaptcha is not enabled for MobileFrontend',
	async function ( assert ) {
		initMobileFrontend(
			'mobilefrontend-editor',
			{
				HCaptchaEnabledInMobileFrontend: false,
				MobileHCaptchaAbuseFilterEnabled: true,
				HCaptchaSiteKey: 'hCaptcha-site-key'
			},
			this.window
		);

		mw.hook( this.mfHookName( 'preRenderFinished' ) ).fire();
		await this.waitOneTick();

		assert.false(
			this.window.document.createElement.called,
			'The hook handler should not have been called'
		);
	}
);

QUnit.test(
	'preRenderFinished when hCaptcha is enabled for MobileFrontend',
	async function ( assert ) {
		initMobileFrontend(
			'mobilefrontend-editor',
			{
				HCaptchaEnabledInMobileFrontend: true,
				MobileHCaptchaAbuseFilterEnabled: true,
				HCaptchaSiteKey: 'hCaptcha-site-key'
			},
			this.window
		);

		mw.hook( this.mfHookName( 'preRenderFinished' ) ).fire();
		await this.waitOneTick();

		assert.true(
			this.window.document.createElement.calledOnce,
			'The handler should have created a script tag for the hCaptcha SDK'
		);
	}
);

// Tests for mobileFrontend.sourceEditor.saveBegin

QUnit.test.each(
	'saveBegin when hCaptcha is not enabled for MobileFrontend',
	[
		{
			config: { HCaptchaEnabledInMobileFrontend: false, MobileHCaptchaAbuseFilterEnabled: false },
			captchaNeededForGenericEdit: undefined
		},
		{
			config: { HCaptchaEnabledInMobileFrontend: false, MobileHCaptchaAbuseFilterEnabled: true },
			captchaNeededForGenericEdit: undefined
		},
		{
			config: { HCaptchaEnabledInMobileFrontend: true, MobileHCaptchaAbuseFilterEnabled: true },
			captchaNeededForGenericEdit: false
		}
	],
	async function ( assert, { config, captchaNeededForGenericEdit } ) {
		initMobileFrontend( 'mobilefrontend-editor', config, this.window );

		mw.config.set( 'wgConfirmEditCaptchaNeededForGenericEdit', captchaNeededForGenericEdit );

		const e = this.sandbox.stub();
		e.stop = this.sandbox.stub();

		mw.hook( this.mfHookName( 'saveBegin' ) ).fire( e );
		await this.waitOneTick();

		// When HCaptcha for the MobileFrontend is enabled, the hook handler for
		// saveBegin first calls stop() on the event it receives in order to
		// instruct the MobileFrontend to not continue saving the edit: Check
		// that stop() was not called to verify that the handler was not called.
		assert.false(
			e.stop.called,
			'The hook handler should not have been called'
		);
	}
);

QUnit.test(
	'saveBegin when hCaptcha is enabled for MobileFrontend',
	async function ( assert ) {
		initMobileFrontend(
			'mobilefrontend-editor',
			{
				HCaptchaEnabledInMobileFrontend: true,
				MobileHCaptchaAbuseFilterEnabled: true,
				HCaptchaSiteKey: 'hCaptcha-site-key'
			},
			this.window
		);

		mw.config.set( 'wgConfirmEditCaptchaNeededForGenericEdit', 'hcaptcha' );

		const e = this.sandbox.stub();
		e.stop = this.sandbox.stub();

		mw.hook( this.mfHookName( 'saveBegin' ) ).fire( e );
		await this.waitOneTick();

		// When HCaptcha for the MobileFrontend is enabled, the hook handler for
		// saveBegin first calls stop() on the event it receives in order to
		// instruct the MobileFrontend to not continue saving the edit: Check
		// that stop() was called to verify that the handler was indeed called.
		assert.true(
			e.stop.calledOnce,
			'The handler should have requested to stop saving the page'
		);
	}
);

// Tests for confirmEdit.hCaptcha.executionSuccess

QUnit.test(
	'executionSuccess when hCaptcha is not enabled for MobileFrontend',
	async function ( assert ) {
		initMobileFrontend(
			'mobilefrontend-editor',
			{
				HCaptchaEnabledInMobileFrontend: false,
				HCaptchaSiteKey: 'hCaptcha-site-key'
			},
			this.window
		);

		const e = this.sandbox.stub();
		e.stop = this.sandbox.stub();
		e.resume = this.sandbox.stub();

		// Under normal circumstances, saveBegin is triggered first in order to
		// have the object passed as its argument bound to the hooks in
		// initMobileFrontend, and then the handler for executionSuccess uses
		// that object to resume the normal flow for saving the edit.
		//
		// As this test checks the behavior when the integration is not enabled,
		// we trigger the regular events that the MobileFrontend would fire, but
		// don't expect it to stop the save flow nor to then resume it.
		mw.hook( this.mfHookName( 'saveBegin' ) ).fire( e );
		await this.waitOneTick();

		mw.hook( this.ceHookName( 'executionSuccess' ) ).fire( 'token' );
		await this.waitOneTick();

		assert.false(
			e.stop.called,
			'stop() in the hook handler payload should not have been called'
		);
		assert.false(
			e.resume.called,
			'resume() in the hook handler payload should not have been called'
		);
	}
);

QUnit.test(
	'executionSuccess when hCaptcha is enabled for MobileFrontend',
	async function ( assert ) {
		initMobileFrontend(
			'mobilefrontend-editor',
			{
				HCaptchaEnabledInMobileFrontend: true,
				HCaptchaSiteKey: 'hCaptcha-site-key'
			},
			this.window
		);

		mw.config.set( 'wgConfirmEditCaptchaNeededForGenericEdit', 'hcaptcha' );

		const e = this.sandbox.stub();
		e.stop = this.sandbox.stub();
		e.resume = this.sandbox.stub();
		e.options = {
			foo: 'bar'
		};

		// saveBegin should be triggered first in order to have the object
		// passed as its argument bound to the hooks in initMobileFrontend.
		//
		// Then, the handler fo executionSuccess should use that object to
		// resume the normal flow for saving the edit, while retaining any
		// option provided in the original event.
		mw.hook( this.mfHookName( 'saveBegin' ) ).fire( e );
		await this.waitOneTick();

		assert.true(
			e.stop.calledOnce,
			'The handler should have requested to stop saving the page'
		);

		mw.hook( this.ceHookName( 'executionSuccess' ) ).fire( 'token' );
		await this.waitOneTick();

		assert.true(
			e.resume.calledWith( {
				captchaWord: 'token',
				foo: 'bar'
			} ),
			'resume() should have been called passing the token in its param'
		);
	}
);

QUnit.test(
	'saveBegin does not stop save when wgConfirmEditForceShowCaptcha is set (page reload after AbuseFilter)',
	async function ( assert ) {
		initMobileFrontend(
			'mobilefrontend-editor',
			{
				HCaptchaEnabledInMobileFrontend: true,
				HCaptchaSiteKey: 'hCaptcha-site-key'
			},
			this.window
		);

		mw.config.set( 'wgConfirmEditCaptchaNeededForGenericEdit', 'hcaptcha' );
		mw.config.set( 'wgConfirmEditForceShowCaptcha', true );

		const e = this.sandbox.stub();
		e.stop = this.sandbox.stub();
		e.resume = this.sandbox.stub();
		e.options = {};

		mw.hook( this.mfHookName( 'saveBegin' ) ).fire( e );
		await this.waitOneTick();

		assert.false(
			e.stop.called,
			'stop() should not be called when wgConfirmEditForceShowCaptcha is set — handleCaptcha will handle it'
		);
	}
);

QUnit.test.each(
	'handleCaptcha renders the captcha panel for abuse-filter responses',
	{
		'missing-token': { error: 'missing-token' },
		'sitekey-mismatch': { error: 'sitekey-mismatch' }
	},
	async function ( assert, { error } ) {
		const mobileConfig = {
			HCaptchaEnabledInMobileFrontend: false,
			MobileHCaptchaAbuseFilterEnabled: true,
			HCaptchaSiteKey: 'hCaptcha-site-key'
		};

		initMobileFrontend( 'mobilefrontend-editor', mobileConfig, this.window );

		const editorEvent = this.sandbox.stub();
		editorEvent.stop = this.sandbox.stub();
		editorEvent.setTemplate = this.sandbox.stub();

		const captchaDetails = {
			type: 'hcaptcha',
			error: error,
			key: 'details-site-key'
		};

		const $captchaForm = this.sandbox.stub();
		$captchaForm.show = this.sandbox.stub();

		const $captchaContainer = this.sandbox.stub();
		$captchaContainer.find = this.sandbox.stub().returns( $captchaForm );

		mw.hook( this.mfHookName( 'handleCaptcha' ) ).fire(
			editorEvent,
			captchaDetails,
			$captchaContainer
		);
		await this.waitOneTick();

		assert.true(
			editorEvent.stop.calledOnce,
			'Stops the current save attempt'
		);
		assert.true(
			editorEvent.setTemplate.calledOnce,
			'Injects captcha panel template'
		);

		const setTemplateArgs = editorEvent.setTemplate.firstCall.args;
		assert.strictEqual(
			setTemplateArgs[ 0 ],
			'captcha-panel',
			'Targets captcha panel template slot'
		);
		assert.strictEqual(
			this.trimHTML( setTemplateArgs[ 1 ] ),
			this.hCaptchaExpectedFormHTML,
			'Uses the expected hCaptcha form template'
		);
		assert.deepEqual(
			setTemplateArgs[ 2 ],
			{
				hCaptchaLicenseText: '(hcaptcha-privacy-policy)',
				hCaptchaSiteKey: 'details-site-key'
			},
			'Passes template variables required by the panel'
		);

		setTemplateArgs[ 3 ]();
		assert.true(
			this.window.document.createElement.calledOnce,
			'Loads hCaptcha SDK on panel callback'
		);
	}
);

QUnit.test(
	'executionSuccess resumes a pending save started by handleCaptcha',
	async function ( assert ) {
		const mobileConfig = {
			HCaptchaEnabledInMobileFrontend: false,
			MobileHCaptchaAbuseFilterEnabled: true,
			HCaptchaSiteKey: 'hCaptcha-site-key'
		};

		initMobileFrontend( 'mobilefrontend-editor', mobileConfig, this.window );

		const editorEvent = this.sandbox.stub();
		editorEvent.stop = this.sandbox.stub();
		editorEvent.resume = this.sandbox.stub();
		editorEvent.setTemplate = this.sandbox.stub();
		editorEvent.options = { source: 'mobile-abuse-filter' };

		const captchaDetails = {
			type: 'hcaptcha',
			error: 'missing-token',
			key: 'details-site-key'
		};

		const $captchaContainer = this.sandbox.stub();
		$captchaContainer.find = this.sandbox.stub().returns( {
			show: this.sandbox.stub()
		} );

		mw.hook( this.mfHookName( 'handleCaptcha' ) ).fire(
			editorEvent,
			captchaDetails,
			$captchaContainer
		);
		mw.hook( this.ceHookName( 'executionSuccess' ) ).fire( 'hcaptcha-token' );
		await this.waitOneTick();

		assert.true(
			editorEvent.stop.calledOnce,
			'Stops edit flow until a token is available'
		);
		assert.true(
			editorEvent.resume.calledWith( {
				source: 'mobile-abuse-filter',
				captchaWord: 'hcaptcha-token'
			} ),
			'Resumes edit flow with the captcha token'
		);
	}
);

QUnit.test.each(
	'handleCaptcha renders the captcha panel for known errors',
	{
		forceshowcaptcha: { error: 'forceshowcaptcha' }
	},
	async function ( assert, { error } ) {
		const mobileConfig = {
			HCaptchaEnabledInMobileFrontend: false,
			MobileHCaptchaAbuseFilterEnabled: true,
			HCaptchaSiteKey: 'hCaptcha-site-key'
		};

		initMobileFrontend( 'mobilefrontend-editor', mobileConfig, this.window );

		const editorEvent = this.sandbox.stub();
		editorEvent.stop = this.sandbox.stub();
		editorEvent.setTemplate = this.sandbox.stub();

		const captchaDetails = {
			type: 'hcaptcha',
			error: error,
			key: 'details-site-key'
		};

		const $captchaForm = this.sandbox.stub();
		$captchaForm.show = this.sandbox.stub();

		const $captchaContainer = this.sandbox.stub();
		$captchaContainer.find = this.sandbox.stub().returns( $captchaForm );

		mw.hook( this.mfHookName( 'handleCaptcha' ) ).fire(
			editorEvent,
			captchaDetails,
			$captchaContainer
		);
		await this.waitOneTick();

		assert.true(
			editorEvent.stop.calledOnce,
			'Stops the current save attempt'
		);
		assert.true(
			editorEvent.setTemplate.calledOnce,
			'Injects captcha panel template'
		);
		assert.deepEqual(
			editorEvent.setTemplate.firstCall.args[ 2 ],
			{
				hCaptchaLicenseText: '(hcaptcha-privacy-policy)',
				hCaptchaSiteKey: 'details-site-key'
			},
			'Passes the site key from details to the panel'
		);
	}
);

QUnit.test(
	'handleCaptcha shows a generic error notification for unhandled errors',
	async function ( assert ) {
		const mobileConfig = {
			HCaptchaEnabledInMobileFrontend: false,
			MobileHCaptchaAbuseFilterEnabled: true,
			HCaptchaSiteKey: 'hCaptcha-site-key'
		};

		initMobileFrontend( 'mobilefrontend-editor', mobileConfig, this.window );

		const editorEvent = this.sandbox.stub();
		editorEvent.stop = this.sandbox.stub();
		editorEvent.setTemplate = this.sandbox.stub();

		const captchaDetails = {
			type: 'hcaptcha',
			error: 'some-unknown-error',
			key: 'details-site-key'
		};

		const $captchaContainer = this.sandbox.stub();
		$captchaContainer.find = this.sandbox.stub();

		mw.hook( this.mfHookName( 'handleCaptcha' ) ).fire(
			editorEvent,
			captchaDetails,
			$captchaContainer
		);
		await this.waitOneTick();

		assert.true(
			editorEvent.stop.calledOnce,
			'Stops the current save attempt'
		);
		assert.true(
			editorEvent.setTemplate.calledOnce,
			'Injects captcha panel template'
		);
		assert.strictEqual(
			editorEvent.setTemplate.firstCall.args[ 0 ],
			'captcha-panel',
			'Targets captcha panel template slot'
		);
	}
);
