const mobileFrontendSecureEnclave = require( 'ext.confirmEdit.hCaptcha/ext.confirmEdit.hCaptcha/mobileFrontend/mobileFrontendSecureEnclave.js' );

QUnit.module(
	'ext.confirmEdit.hCaptcha.mobileFrontend.mobileFrontendSecureEnclave',
	QUnit.newMwEnvironment( {
		beforeEach() {
			mw.config.set( 'wgDBname', 'testwiki' );

			this.ceHookName = ( method ) => `confirmEdit.hCaptcha.${ method }`;

			this.track = this.sandbox.stub( mw, 'track' );
			this.logError = this.sandbox.stub( mw.errorLogger, 'logError' );

			// Sinon fake timers as of v21 only return a static fake value from
			// performance.measure(), so use a regular stub instead.
			this.measure = this.sandbox.stub( performance, 'measure' );
			this.measure.returns( { duration: 0 } );

			this.getEntriesByName = this.sandbox.stub( performance, 'getEntriesByName' );

			// We don't want to add real script elements to the page or interact with
			// the real hCaptcha, so stub the code that does this for this test
			this.window = {
				hcaptcha: {
					render: this.sandbox.stub(),
					execute: this.sandbox.stub()
				},
				document: {
					createElement: this.sandbox.stub().returns( {
						classList: {
							contains: this.sandbox.stub().returns( false )
						}
					} ),
					head: {
						appendChild: this.sandbox.stub(),
						removeChild: this.sandbox.stub()
					},
					querySelectorAll: this.sandbox.stub().returns( [] )
				},
				performance: {
					measure: this.measure,
					getEntriesByName: this.getEntriesByName
				}
			};

			const form = document.createElement( 'form' );
			this.submit = this.sandbox.stub( form, 'submit' );

			this.$form = $( form )
				.append( '<input type="text" name="some-input" />' )
				.append( '<textarea name="some-textarea"></textarea>' )
				.append( '<input type="hidden" id="h-captcha">' )
				.append( '<input type="submit" value="Save Changes" id="wpSave">' )
				.append( '<div id="wpSaveWidget"></div>' );

			this.sandbox.stub( window.OO.ui, 'infuse' ).returns( {
				setDisabled: ( disabled ) => {
					this.$form.find( 'input[type="submit"], button[type="submit"]' ).prop( 'disabled', disabled );
				}
			} );

			this.$form.appendTo( $( '#qunit-fixture' ) );

			// The display property may be "none" if the element is hidden, but
			// it can also not be set at all (i.e. undefined, which (void 0)
			// equals to) if the loading indicator was never added to the DOM in
			// the first place. When the element is being shown, its display
			// property may be either "block" or "flex".
			this.isLoadingIndicatorVisible = () => !(
				[ undefined, 'none' ].includes(
					this.$form
						.find( '.ext-confirmEdit-hCaptchaLoadingIndicator' )
						.css( 'display' )
				)
			);

			this.areSubmitButtonsDisabled = () => {
				const $submitButtons = this.$form.find( 'input[type="submit"], button[type="submit"]' );
				return $submitButtons.length > 0 && $submitButtons.toArray().every( ( btn ) => btn.disabled );
			};
		},

		afterEach() {
			this.track.restore();
			this.measure.restore();
			this.logError.restore();
			this.getEntriesByName.restore();
		}
	} )
);

QUnit.test(
	'should not wait for a form submission when run for the MobileFrontend Editor',
	async function ( assert ) {
		this.window.document.head.appendChild.callsFake( async () => {
			assert.false(
				this.isLoadingIndicatorVisible(),
				'should not show loading indicator prior to execute'
			);
			this.window.onHCaptchaSDKLoaded();
		} );
		this.window.hcaptcha.render.returns( 'some-captcha-id' );
		this.window.hcaptcha.execute.callsFake(
			() => Promise.resolve( {
				response: 'some-token'
			} )
		);

		const hook = mw.hook( this.ceHookName( 'executionSuccess' ) );
		const spy = this.sandbox.spy( hook, 'fire' );

		// The promise from mobileFrontendSecureEnclave should resolve without
		// waiting for a form submission - this will time out if it doesn't.
		await mobileFrontendSecureEnclave( this.window, 'mobilefrontend-editor' );

		assert.true(
			this.window.document.head.appendChild.calledOnce,
			'should load hCaptcha SDK once'
		);
		assert.true(
			this.window.hcaptcha.render.calledOnce,
			'should render hCaptcha widget once'
		);
		assert.deepEqual(
			this.window.hcaptcha.render.firstCall.args[ 0 ],
			'h-captcha',
			'should render hCaptcha widget in correct element'
		);
		assert.true(
			this.window.hcaptcha.execute.calledOnce,
			'should execute hCaptcha without waiting for a form submission'
		);

		assert.true( spy.calledOnce, 'Hook was fired once' );
		assert.deepEqual(
			spy.firstCall.args[ 0 ],
			'some-token',
			'Hook was fired with expected arguments'
		);

		// Clean up spy to avoid affecting later tests
		spy.restore();
	}
);

QUnit.test(
	'should not retry after a challenge-closed error',
	async function ( assert ) {
		this.window.document.head.appendChild.callsFake( () => {
			this.window.onHCaptchaSDKLoaded();
		} );
		this.window.hcaptcha.render.returns( 'some-captcha-id' );
		this.window.hcaptcha.execute.callsFake(
			() => Promise.reject( 'challenge-closed' )
		);

		const hook = mw.hook( this.ceHookName( 'executionSuccess' ) );
		const spy = this.sandbox.spy( hook, 'fire' );

		// The promise from mobileFrontendSecureEnclave should resolve without
		// waiting for a form submission - this will time out if it doesn't.
		await mobileFrontendSecureEnclave( this.window, 'mobilefrontend-editor' );

		assert.true(
			this.window.hcaptcha.execute.calledOnce,
			'should not have attempted a retry after challenge-closed'
		);
		assert.false(
			spy.called,
			'executionSuccess hook should not have been fired'
		);

		// Clean up spy to avoid affecting later tests
		spy.restore();
	}
);

QUnit.test.each(
	'should initiate a new workflow after a recoverable error',
	{
		'challenge-expired': 'challenge-expired',
		'internal-error': 'internal-error',
		'network-error': 'network-error',
		'rate-limited': 'rate-limited'
	},
	async function ( assert, error ) {
		this.window.document.head.appendChild.callsFake( () => {
			this.window.onHCaptchaSDKLoaded();
		} );
		this.window.hcaptcha.render.returns( 'some-captcha-id' );
		this.window.hcaptcha.execute.onFirstCall().callsFake(
			() => Promise.reject( error )
		);
		this.window.hcaptcha.execute.onSecondCall().callsFake(
			() => Promise.resolve( { response: 'some-token' } )
		);

		const hook = mw.hook( this.ceHookName( 'executionSuccess' ) );
		const spy = this.sandbox.spy( hook, 'fire' );

		// The promise from mobileFrontendSecureEnclave should resolve without
		// waiting for a form submission - this will time out if it doesn't.
		await mobileFrontendSecureEnclave( this.window, 'mobilefrontend-editor' );

		assert.strictEqual(
			this.window.hcaptcha.execute.callCount,
			2,
			`should retry once after ${ error }`
		);
		assert.true(
			spy.calledOnce,
			'executionSuccess should have been fired after the retry succeeds'
		);
		assert.deepEqual(
			spy.firstCall.args[ 0 ],
			'some-token',
			'Hook was fired with expected arguments'
		);

		// Clean up spy to avoid affecting later tests
		spy.restore();
	}
);
