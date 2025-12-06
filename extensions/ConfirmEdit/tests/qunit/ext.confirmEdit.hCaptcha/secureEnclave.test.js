const useSecureEnclave = require( 'ext.confirmEdit.hCaptcha/ext.confirmEdit.hCaptcha/secureEnclave.js' );
const config = require( 'ext.confirmEdit.hCaptcha/ext.confirmEdit.hCaptcha/config.json' );

QUnit.module( 'ext.confirmEdit.hCaptcha.secureEnclave', QUnit.newMwEnvironment( {
	beforeEach() {
		mw.config.set( 'wgDBname', 'testwiki' );

		this.track = this.sandbox.stub( mw, 'track' );
		this.logError = this.sandbox.stub( mw.errorLogger, 'logError' );

		// Sinon fake timers as of v21 only return a static fake value from performance.measure(),
		// so use a regular stub instead.
		this.measure = this.sandbox.stub( performance, 'measure' );
		this.measure.returns( { duration: 0 } );

		// We do not want to add real script elements to the page or interact with the real
		// hcaptcha, so stub the code that does this for this test
		this.window = {
			hcaptcha: {
				render: this.sandbox.stub(),
				execute: this.sandbox.stub()
			},
			document: {
				head: {
					appendChild: this.sandbox.stub()
				}
			}
		};

		const form = document.createElement( 'form' );
		this.submit = this.sandbox.stub( form, 'submit' );

		this.$form = $( form )
			.append( '<input type="text" name="some-input" />' )
			.append( '<textarea name="some-textarea"></textarea>' )
			.append( '<input type="hidden" id="h-captcha">' );

		this.$form.appendTo( $( '#qunit-fixture' ) );

		this.isLoadingIndicatorVisible = () => this.$form
			.find( '.ext-confirmEdit-hCaptchaLoadingIndicator' )
			.css( 'display' ) !== 'none';

		this.origUrl = config.HCaptchaApiUrl;
		this.origIntegrityHash = config.HCaptchaApiUrlIntegrityHash;
		config.HCaptchaApiUrl = 'https://example.com/hcaptcha.js';
		config.HCaptchaApiUrlIntegrityHash = '1234abcef';
	},

	afterEach() {
		this.track.restore();
		this.measure.restore();
		this.logError.restore();

		config.HCaptchaApiUrl = this.origUrl;
		config.HCaptchaApiUrlIntegrityHash = this.origIntegrityHash;
	}
} ) );

QUnit.test( 'should not load hCaptcha before the form has been interacted with', async function ( assert ) {
	useSecureEnclave( this.window );

	assert.true( this.window.document.head.appendChild.notCalled, 'should not load hCaptcha SDK' );
	assert.true( this.window.hcaptcha.render.notCalled, 'should not render hCaptcha' );
	assert.true( this.window.hcaptcha.execute.notCalled, 'should not execute hCaptcha' );
	assert.true( this.track.notCalled, 'should not emit hCaptcha performance events' );
} );

QUnit.test.each( 'should load hCaptcha exactly once when the form is interacted with', {
	'interaction with input element': {
		fieldName: 'some-textarea'
	},
	'interaction with textarea element': {
		fieldName: 'some-textarea'
	}
}, async function ( assert, data ) {
	this.window.document.head.appendChild.callsFake( async () => {
		this.window.onHCaptchaSDKLoaded();
	} );

	useSecureEnclave( this.window );

	const $field = this.$form.find( '[name=' + data.fieldName + ']' );

	$field.trigger( 'focus' );
	$field.trigger( 'input' );
	$field.trigger( 'input' );

	// Wait one tick for event handlers to run.
	await new Promise( ( resolve ) => {
		setTimeout( resolve );
	} );

	assert.true( this.window.document.head.appendChild.calledOnce, 'should load hCaptcha SDK once' );
	assert.true( this.window.hcaptcha.render.calledOnce, 'should render hCaptcha widget once' );
	assert.deepEqual(
		this.window.hcaptcha.render.firstCall.args[ 0 ],
		'h-captcha',
		'should render hCaptcha widget in correct element'
	);
	assert.true( this.window.hcaptcha.execute.notCalled, 'should not execute hCaptcha before the form is submitted' );
} );

QUnit.test( 'should load hCaptcha on form submissions triggered before hCaptcha was setup', async function ( assert ) {
	this.window.document.head.appendChild.callsFake( async () => {
		this.window.onHCaptchaSDKLoaded();
	} );

	useSecureEnclave( this.window );

	this.$form.trigger( 'submit' );

	// Wait one tick for event handlers to run.
	await new Promise( ( resolve ) => {
		setTimeout( resolve );
	} );

	assert.true( this.window.document.head.appendChild.calledOnce, 'should load hCaptcha SDK once' );
	assert.true( this.submit.notCalled, 'form submission should have been prevented' );
	assert.true( this.window.hcaptcha.render.calledOnce, 'should render hCaptcha widget once' );
	assert.deepEqual(
		this.window.hcaptcha.render.firstCall.args[ 0 ],
		'h-captcha',
		'should render hCaptcha widget in correct element'
	);
	assert.true( this.window.hcaptcha.execute.notCalled, 'should not execute hCaptcha before the form is submitted' );
} );

QUnit.test( 'should intercept form submissions', function ( assert ) {
	this.window.document.head.appendChild.callsFake( async () => {
		assert.false( this.isLoadingIndicatorVisible(), 'should not show loading indicator prior to execute' );
		this.window.onHCaptchaSDKLoaded();
	} );
	this.window.hcaptcha.render.returns( 'some-captcha-id' );
	this.window.hcaptcha.execute.callsFake( async () => {
		assert.true( this.isLoadingIndicatorVisible(), 'loading indicator should be visible during execute' );
		return { response: 'some-token' };
	} );

	const result = useSecureEnclave( this.window )
		.then( () => {
			assert.true( this.window.document.head.appendChild.calledOnce, 'should load hCaptcha SDK once' );
			const actualScriptElement = this.window.document.head.appendChild.firstCall.args[ 0 ];
			assert.deepEqual(
				actualScriptElement.src,
				'https://example.com/hcaptcha.js?onload=onHCaptchaSDKLoaded',
				'should load hCaptcha SDK from given URL'
			);
			assert.deepEqual(
				actualScriptElement.integrity,
				'1234abcef',
				'should load hCaptcha SDK from given URL'
			);

			assert.false( this.isLoadingIndicatorVisible(), 'should hide loading indicator' );

			assert.true( this.window.hcaptcha.render.calledOnce, 'should render hCaptcha widget once' );
			assert.deepEqual(
				this.window.hcaptcha.render.firstCall.args[ 0 ],
				'h-captcha',
				'should render hCaptcha widget in correct element'
			);

			assert.true( this.window.hcaptcha.execute.calledOnce, 'should run hCaptcha once' );
			assert.deepEqual(
				this.window.hcaptcha.execute.firstCall.args,
				[ 'some-captcha-id', { async: true } ],
				'should invoke hCaptcha with correct ID'
			);

			assert.true( this.submit.calledOnce, 'should submit form once hCaptcha token is available' );
			assert.strictEqual(
				this.$form.find( '#h-captcha-response' ).val(),
				'some-token',
				'should add hCaptcha response token to form'
			);

			assert.strictEqual(
				this.$form.find( '.cdx-message' ).css( 'display' ),
				'none',
				'no error message should be shown'
			);
			assert.strictEqual(
				this.$form.find( '.cdx-message' ).text(),
				'',
				'no error message should be set'
			);
		} );

	this.$form.find( '[name=some-input]' ).trigger( 'input' );
	this.$form.trigger( 'submit' );

	return result;
} );

QUnit.test( 'should measure hCaptcha load and execute timing for successful submission', function ( assert ) {
	mw.config.set( 'wgCanonicalSpecialPageName', 'CreateAccount' );

	this.measure
		.onFirstCall().returns( { duration: 1718 } )
		.onSecondCall().returns( { duration: 2314 } );

	this.window.document.head.appendChild.callsFake( async () => {
		this.window.onHCaptchaSDKLoaded();
	} );
	this.window.hcaptcha.render.returns( 'some-captcha-id' );
	this.window.hcaptcha.execute.callsFake( async () => ( { response: 'some-token' } ) );

	const result = useSecureEnclave( this.window )
		.then( () => {
			assert.strictEqual( this.track.callCount, 8, 'should invoke mw.track() eight times' );
			assert.deepEqual(
				this.track.getCall( 0 ).args,
				[ 'specialCreateAccount.performanceTiming', 'hcaptcha-load', 1.718 ],
				'should emit event for load time'
			);
			assert.deepEqual(
				this.track.getCall( 1 ).args,
				[ 'stats.mediawiki_special_createaccount_hcaptcha_load_duration_seconds', 1718, { wiki: 'testwiki' } ],
				'should record account creation specific metric for load time'
			);
			assert.deepEqual(
				this.track.getCall( 2 ).args,
				[ 'stats.mediawiki_confirmedit_hcaptcha_load_duration_seconds', 1718, { wiki: 'testwiki', interfaceName: 'createaccount' } ],
				'should record metric for load time'
			);
			assert.deepEqual(
				this.track.getCall( 3 ).args,
				[ 'stats.mediawiki_confirmedit_hcaptcha_execute_total', 1, { wiki: 'testwiki', interfaceName: 'createaccount' } ],
				'should record event for execute'
			);
			assert.deepEqual(
				this.track.getCall( 4 ).args,
				[ 'stats.mediawiki_confirmedit_hcaptcha_form_submit_total', 1, { wiki: 'testwiki', interfaceName: 'createaccount' } ],
				'should record event for form submission'
			);
			assert.deepEqual(
				this.track.getCall( 5 ).args,
				[ 'specialCreateAccount.performanceTiming', 'hcaptcha-execute', 2.314 ],
				'should emit event for execution time'
			);
			assert.deepEqual(
				this.track.getCall( 6 ).args,
				[ 'stats.mediawiki_special_createaccount_hcaptcha_execute_duration_seconds', 2314, { wiki: 'testwiki' } ],
				'should record account creation specific metric for execution time'
			);
			assert.deepEqual(
				this.track.getCall( 7 ).args,
				[ 'stats.mediawiki_confirmedit_hcaptcha_execute_duration_seconds', 2314, { wiki: 'testwiki', interfaceName: 'createaccount' } ],
				'should record metric for execution time'
			);
		} );

	this.$form.find( '[name=some-input]' ).trigger( 'input' );
	this.$form.trigger( 'submit' );

	return result;
} );

QUnit.test( 'should surface load errors as soon as possible', async function ( assert ) {
	mw.config.set( 'wgAction', 'edit' );

	this.window.document.head.appendChild.callsFake( ( script ) => {
		assert.false( this.isLoadingIndicatorVisible(), 'should not show loading indicator prior to execute' );
		script.onerror();
	} );

	this.measure.onFirstCall().returns( { duration: 1718 } );

	const hCaptchaResult = useSecureEnclave( this.window );

	this.$form.find( '[name=some-input]' ).trigger( 'input' );

	await hCaptchaResult;

	assert.notStrictEqual(
		this.$form.find( '.cdx-message' ).css( 'display' ),
		'none',
		'error message container should be visible'
	);
	assert.strictEqual(
		this.$form.find( '.cdx-message' ).text(),
		'(hcaptcha-generic-error)',
		'load error message should be set'
	);

	assert.strictEqual( this.track.callCount, 2, 'should invoke mw.track() two times' );
	assert.deepEqual(
		this.track.getCall( 0 ).args,
		[ 'stats.mediawiki_confirmedit_hcaptcha_load_duration_seconds', 1718, { wiki: 'testwiki', interfaceName: 'edit' } ],
		'should record metric for load time'
	);
	assert.deepEqual(
		this.track.getCall( 1 ).args,
		[ 'stats.mediawiki_confirmedit_hcaptcha_script_error_total', 1, { wiki: 'testwiki', interfaceName: 'edit' } ],
		'should emit event for load failure'
	);

	assert.strictEqual( this.logError.callCount, 1, 'should invoke mw.errorLogger.logError() once' );
	const logErrorArguments = this.logError.getCall( 0 ).args;
	assert.deepEqual(
		logErrorArguments[ 0 ].message,
		'Unable to load hCaptcha script',
		'should use correct channel for errors'
	);
	assert.deepEqual(
		logErrorArguments[ 1 ],
		'error.confirmedit',
		'should use correct channel for errors'
	);
} );

QUnit.test( 'should surface irrecoverable workflow execution errors as soon as possible', async function ( assert ) {
	// Explicitly set an unknown value here to test the unknown interface handling
	mw.config.set( 'wgAction', 'unknown' );

	this.window.document.head.appendChild.callsFake( async () => {
		assert.false( this.isLoadingIndicatorVisible(), 'should not show loading indicator prior to execute' );
		this.window.onHCaptchaSDKLoaded();
	} );
	this.window.hcaptcha.render.returns( 'some-captcha-id' );
	this.window.hcaptcha.execute.callsFake( () => {
		assert.true( this.isLoadingIndicatorVisible(), 'loading indicator should be visible until hCaptcha finishes' );
		return Promise.reject( 'generic-error' );
	} );

	this.measure
		.onFirstCall().returns( { duration: 1718 } )
		.onSecondCall().returns( { duration: 2314 } );

	const hCaptchaResult = useSecureEnclave( this.window );

	this.$form.find( '[name=some-input]' ).trigger( 'input' );
	this.$form.trigger( 'submit' );

	await hCaptchaResult;

	assert.false( this.isLoadingIndicatorVisible(), 'should hide loading indicator' );

	assert.notStrictEqual(
		this.$form.find( '.cdx-message' ).css( 'display' ),
		'none',
		'error message container should be visible'
	);
	assert.strictEqual(
		this.$form.find( '.cdx-message' ).text(),
		'(hcaptcha-generic-error)',
		'error message should be set'
	);

	assert.strictEqual( this.track.callCount, 4, 'should invoke mw.track() three times' );
	assert.deepEqual(
		this.track.getCall( 0 ).args,
		[ 'stats.mediawiki_confirmedit_hcaptcha_load_duration_seconds', 1718, { wiki: 'testwiki', interfaceName: 'unknown' } ],
		'should record metric for load time'
	);
	assert.deepEqual(
		this.track.getCall( 1 ).args,
		[ 'stats.mediawiki_confirmedit_hcaptcha_execute_total', 1, { wiki: 'testwiki', interfaceName: 'unknown' } ],
		'should emit event for execution'
	);
	assert.deepEqual(
		this.track.getCall( 2 ).args,
		[ 'stats.mediawiki_confirmedit_hcaptcha_execute_duration_seconds', 2314, { wiki: 'testwiki', interfaceName: 'unknown' } ],
		'should record metric for load time'
	);
	assert.deepEqual(
		this.track.getCall( 3 ).args,
		[ 'stats.mediawiki_confirmedit_hcaptcha_execute_workflow_error_total', 1, { wiki: 'testwiki', interfaceName: 'unknown', code: 'generic_error' } ],
		'should emit event for execution failure'
	);
} );

QUnit.test( 'should surface recoverable workflow execution errors on submit', function ( assert ) {
	this.window.document.head.appendChild.callsFake( async () => {
		assert.false( this.isLoadingIndicatorVisible(), 'should not show loading indicator prior to execute' );
		this.window.onHCaptchaSDKLoaded();
	} );

	this.window.hcaptcha.render.returns( 'some-captcha-id' );
	this.window.hcaptcha.execute.callsFake( () => {
		assert.true( this.isLoadingIndicatorVisible(), 'loading indicator should be visible during execute' );
		return Promise.reject( 'challenge-closed' );
	} );

	useSecureEnclave( this.window );

	const formSubmitted = new Promise( ( resolve ) => {
		this.$form.one( 'submit', () => setTimeout( resolve ) );
	} );

	this.$form.find( '[name=some-input]' ).trigger( 'input' );
	this.$form.trigger( 'submit' );

	return formSubmitted.then( () => {
		assert.false( this.isLoadingIndicatorVisible(), 'should hide loading indicator' );

		assert.true( this.submit.notCalled, 'submit should have been prevented' );

		assert.notStrictEqual(
			this.$form.find( '.cdx-message' ).css( 'display' ),
			'none',
			'error message container should be visible'
		);
		assert.strictEqual(
			this.$form.find( '.cdx-message' ).text(),
			'(hcaptcha-challenge-closed)',
			'error message should be set'
		);
	} );
} );

QUnit.test( 'should allow recovering from a recoverable error by starting a new workflow', function ( assert ) {
	this.window.document.head.appendChild.callsFake( async () => {
		assert.false( this.isLoadingIndicatorVisible(), 'should not show loading indicator prior to execute' );
		this.window.onHCaptchaSDKLoaded();
	} );

	this.window.hcaptcha.render.returns( 'some-captcha-id' );
	this.window.hcaptcha.execute
		.onFirstCall().returns( Promise.reject( 'challenge-closed' ) )
		.onSecondCall().resolves( { response: 'some-token' } );

	const result = useSecureEnclave( this.window )
		.then( () => {
			assert.false( this.isLoadingIndicatorVisible(), 'should hide loading indicator' );

			assert.true( this.window.hcaptcha.render.calledOnce, 'should render hCaptcha widget once' );
			assert.deepEqual(
				this.window.hcaptcha.render.firstCall.args[ 0 ],
				'h-captcha',
				'should render hCaptcha widget in correct element'
			);

			assert.true( this.window.hcaptcha.execute.calledTwice, 'should run hCaptcha twice' );
			assert.deepEqual(
				this.window.hcaptcha.execute.firstCall.args,
				[ 'some-captcha-id', { async: true } ],
				'should invoke hCaptcha with correct ID'
			);
			assert.deepEqual(
				this.window.hcaptcha.execute.secondCall.args,
				[ 'some-captcha-id', { async: true } ],
				'should invoke hCaptcha with correct ID'
			);

			assert.true( this.submit.calledOnce, 'submit should have eventually succeeded' );
			assert.strictEqual(
				this.$form.find( '#h-captcha-response' ).val(),
				'some-token',
				'should add hCaptcha response token to form'
			);

			assert.strictEqual(
				this.$form.find( '.cdx-message' ).css( 'display' ),
				'none',
				'no error message should be shown'
			);
		} );

	this.$form.find( '[name=some-input]' ).trigger( 'input' );

	this.$form.one( 'submit', () => setTimeout( () => this.$form.trigger( 'submit' ) ) );

	this.$form.trigger( 'submit' );

	return result;
} );

QUnit.test( 'should fire the confirmEdit.hCaptcha.executed hook when executeHCaptcha succeeds', async function ( assert ) {
	this.window.document.head.appendChild.callsFake( async () => {
		assert.false( this.isLoadingIndicatorVisible(), 'should not show loading indicator prior to execute' );
		this.window.onHCaptchaSDKLoaded();
	} );
	this.window.hcaptcha.render.returns( 'some-captcha-id' );
	this.window.hcaptcha.execute.callsFake( async () => ( { response: 'some-token' } ) );

	const hook = mw.hook( 'confirmEdit.hCaptcha.executionSuccess' );
	const spy = this.sandbox.spy( hook, 'fire' );

	// The promise returned by useSecureEnclave() won't resolve
	// until the form is submitted.
	const result = useSecureEnclave( this.window );

	this.$form.find( '[name=some-input]' ).trigger( 'input' );
	this.$form.trigger( 'submit' );

	await result;

	assert.true( spy.calledOnce, 'Hook was fired once' );
	assert.deepEqual(
		spy.firstCall.args[ 0 ],
		'some-token',
		'Hook was fired with expected arguments'
	);

	// Clean up spy to avoid affecting later tests
	spy.restore();
} );
