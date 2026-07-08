const useSecureEnclave = require( 'ext.confirmEdit.hCaptcha/ext.confirmEdit.hCaptcha/secureEnclave.js' );
const config = require( 'ext.confirmEdit.hCaptcha/ext.confirmEdit.hCaptcha/config.json' );

/**
 * Introduces a delay which allows pending actions to be processed.
 *
 * By default, the delay takes one event cycle of the Javascript engine.
 *
 * This is used in order to have a chance for event handlers to run before
 * asserting their side effects.
 *
 * @param {number} timeout Number of milliseconds to wait.
 * @return {Promise<void>}
 */
const delay = ( timeout = 0 ) => new Promise( ( resolve ) => {
	setTimeout( resolve, timeout );
} );

QUnit.module( 'ext.confirmEdit.hCaptcha.secureEnclave', QUnit.newMwEnvironment( {
	beforeEach() {
		mw.config.set( 'wgDBname', 'testwiki' );
		mw.config.set( 'wgHCaptchaTriggerFormSubmission', null );

		// Prevent tests from keeping trying to load the hCaptcha script when
		// testing the "onerror" logic.
		mw.config.set( 'wgHCaptchaMaxLoadAttempts', 2 );
		mw.config.set( 'wgHCaptchaBaseRetryDelay', 1 );

		this.track = this.sandbox.stub( mw, 'track' );
		this.logError = this.sandbox.stub( mw.errorLogger, 'logError' );

		// Sinon fake timers as of v21 only return a static fake value from performance.measure(),
		// so use a regular stub instead.
		this.measure = this.sandbox.stub( performance, 'measure' );
		this.measure.returns( { duration: 0 } );

		this.getEntriesByName = this.sandbox.stub( performance, 'getEntriesByName' );

		// We do not want to add real script elements to the page or interact with the real
		// hcaptcha, so stub the code that does this for this test
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

		// The display property may be "none" if the element is hidden, but it
		// can also not be set at all (i.e. undefined) if the loading indicator
		// was never added to the DOM in the first place. When the element is
		// being shown, its display property may be either "block" or "flex".
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

		this.origUrl = config.HCaptchaApiUrl;
		this.origIntegrityHash = config.HCaptchaApiUrlIntegrityHash;
		config.HCaptchaApiUrl = 'https://example.com/hcaptcha.js';
		config.HCaptchaApiUrlIntegrityHash = '1234abcef';

		// Needed so we are able to reset the internal state between tests
		// (i.e state of widgets that may've been set by previous tests)
		this.utils = require( 'ext.confirmEdit.hCaptcha/ext.confirmEdit.hCaptcha/utils.js' );
	},

	afterEach() {
		this.track.restore();
		this.measure.restore();
		this.logError.restore();
		this.getEntriesByName.restore();

		this.utils.reset();

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
	this.window.document.head.appendChild.callsFake( () => {
		this.window.onHCaptchaSDKLoaded();
	} );

	useSecureEnclave( this.window );

	const $field = this.$form.find( '[name=' + data.fieldName + ']' );

	$field.trigger( 'focus' );
	$field.trigger( 'input' );
	$field.trigger( 'input' );

	await delay();

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
	this.window.document.head.appendChild.callsFake( () => {
		this.window.onHCaptchaSDKLoaded();
	} );

	useSecureEnclave( this.window );

	this.$form.trigger( 'submit' );

	await delay();

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

function assertHCaptchaWasExecuted( assert, testcase ) {
	const win = testcase.window;

	assert.true( win.document.head.appendChild.calledOnce, 'should load hCaptcha SDK once' );
	const actualScriptElement = win.document.head.appendChild.firstCall.args[ 0 ];
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

	assert.false(
		testcase.isLoadingIndicatorVisible(),
		'should hide loading indicator'
	);
	assert.false(
		testcase.areSubmitButtonsDisabled(),
		'submit buttons should be re-enabled after success'
	);

	assert.true( win.hcaptcha.render.calledOnce, 'should render hCaptcha widget once' );
	assert.deepEqual(
		win.hcaptcha.render.firstCall.args[ 0 ],
		'h-captcha',
		'should render hCaptcha widget in correct element'
	);

	assert.true( win.hcaptcha.execute.calledOnce, 'should run hCaptcha once' );
	assert.deepEqual(
		win.hcaptcha.execute.firstCall.args,
		[ 'some-captcha-id', { async: true } ],
		'should invoke hCaptcha with correct ID'
	);
}

function assertSubmissionDone( assert, testcase, token = 'some-token', callCount = 1 ) {
	assert.strictEqual(
		testcase.submit.callCount, callCount,
		'should submit form once hCaptcha token is available'
	);
	assert.strictEqual(
		testcase.$form.find( '#h-captcha-response' ).val(),
		token,
		'should add hCaptcha response token to form'
	);
	assert.strictEqual(
		testcase.$form.find( '.cdx-message' ).css( 'display' ),
		'none',
		'no error message should be shown'
	);
	assert.strictEqual(
		testcase.$form.find( '.cdx-message' ).text(),
		'',
		'no error message should be set'
	);
}

QUnit.test( 'should intercept form submissions by default', async function ( assert ) {
	this.window.document.head.appendChild.callsFake( () => {
		assert.false( this.isLoadingIndicatorVisible(), 'should not show loading indicator prior to execute' );
		this.window.onHCaptchaSDKLoaded();
	} );
	this.window.hcaptcha.render.returns( 'some-captcha-id' );
	this.window.hcaptcha.execute.callsFake( async () => {
		assert.true( this.isLoadingIndicatorVisible(), 'loading indicator should be visible during execute' );
		assert.true( this.areSubmitButtonsDisabled(), 'submit buttons should be disabled during execute' );
		return { response: 'some-token' };
	} );

	const result = useSecureEnclave( this.window ).then(
		() => assertHCaptchaWasExecuted( assert, this )
	);

	// The submission is always intercepted because it does not come from the edit form.
	this.$form.find( '[name=some-input]' ).trigger( 'input' );
	this.$form.trigger( 'submit' );

	return Promise.all( [ delay(), result ] ).then(
		() => assertSubmissionDone( assert, this )
	);
} );

QUnit.test( 'should intercept edit form submissions if they come from wpSave', function ( assert ) {
	this.window.document.head.appendChild.callsFake( () => {
		assert.false( this.isLoadingIndicatorVisible(), 'should not show loading indicator prior to execute' );
		this.window.onHCaptchaSDKLoaded();
	} );
	this.window.hcaptcha.render.returns( 'some-captcha-id' );
	this.window.hcaptcha.execute.callsFake( async () => {
		assert.true( this.isLoadingIndicatorVisible(), 'loading indicator should be visible during execute' );
		return { response: 'some-token' };
	} );

	this.$form.attr( 'id', 'editform' );

	const result = useSecureEnclave( this.window ).then(
		() => assertHCaptchaWasExecuted( assert, this )
	);

	this.$form.find( '[name=some-input]' ).trigger( 'input' );
	this.$form.find( '#wpSave' ).trigger( 'click' );

	return result.then(
		() => assertSubmissionDone( assert, this )
	);
} );

QUnit.test( 'should not intercept edit form submissions not coming from wpSave', async function ( assert ) {
	this.window.document.head.appendChild.callsFake( () => {
		assert.false( this.isLoadingIndicatorVisible(), 'should not show loading indicator prior to execute' );
		this.window.onHCaptchaSDKLoaded();
	} );
	this.window.hcaptcha.render.returns( 'some-captcha-id' );
	this.window.hcaptcha.execute.callsFake( async () => {
		assert.true( this.isLoadingIndicatorVisible(), 'loading indicator should be visible during execute' );
		return { response: 'some-token' };
	} );

	this.$form.attr( 'id', 'editform' );

	let resolved = false;

	const result = useSecureEnclave( this.window ).then( () => {
		resolved = true;
	} );

	this.$form.find( '[name=some-input]' ).trigger( 'input' );
	this.$form.find( '#wpPreview' ).trigger( 'submit' );

	await delay();

	assert.false(
		resolved,
		'A captcha should not be shown when clicking on the review button'
	);
	assert.false(
		this.window.hcaptcha.execute.calledOnce,
		'should have not run hCaptcha when clicking the Review button'
	);

	// Ensure clicking Save after requesting a preview still triggers the captcha.
	//
	// Tests a scenario where the user first clicks a submit button not triggering a
	// captcha (i.e. "Show Preview" in the edit form), then immediately cancels loading
	// the new page (pressing Esc or using the browser's Stop button) and clicks on the
	// Save button instead (which should trigger a captcha).
	//
	// This can happen over slow connections without explicitly canceling the page load
	// if the Preview request goes slow enough to allow the user to click the Save button
	// before the response for the Preview request has been received.
	this.$form.find( '#wpSave' ).trigger( 'click' );

	await delay();

	assert.true(
		resolved,
		'A captcha should be shown when clicking on the save button'
	);
	assert.true(
		this.window.hcaptcha.execute.called,
		'should have run hCaptcha when clicking the Save button'
	);

	return result;
} );

QUnit.test( 'should measure hCaptcha load and execute timing for successful submission', function ( assert ) {
	mw.config.set( 'wgCanonicalSpecialPageName', 'CreateAccount' );

	this.measure
		.onFirstCall().returns( { duration: 1718 } )
		.onSecondCall().returns( { duration: 2314 } );

	this.window.document.head.appendChild.callsFake( () => {
		this.window.onHCaptchaSDKLoaded();
	} );
	this.window.hcaptcha.render.returns( 'some-captcha-id' );
	this.window.hcaptcha.execute.callsFake( async () => ( { response: 'some-token' } ) );

	const result = useSecureEnclave( this.window )
		.then( () => {
			assert.strictEqual( this.track.callCount, 9, 'should invoke mw.track() nine times' );
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
				[ 'stats.mediawiki_confirmedit_hcaptcha_load_attempts_total', 1, { wiki: 'testwiki', interfaceName: 'createaccount', outcome: 'success' } ],
				'should record metric for load attempts'
			);
			assert.deepEqual(
				this.track.getCall( 4 ).args,
				[ 'stats.mediawiki_confirmedit_hcaptcha_execute_total', 1, { wiki: 'testwiki', interfaceName: 'createaccount' } ],
				'should record event for execute'
			);
			assert.deepEqual(
				this.track.getCall( 5 ).args,
				[ 'stats.mediawiki_confirmedit_hcaptcha_form_submit_total', 1, { wiki: 'testwiki', interfaceName: 'createaccount' } ],
				'should record event for form submission'
			);
			assert.deepEqual(
				this.track.getCall( 6 ).args,
				[ 'specialCreateAccount.performanceTiming', 'hcaptcha-execute', 2.314 ],
				'should emit event for execution time'
			);
			assert.deepEqual(
				this.track.getCall( 7 ).args,
				[ 'stats.mediawiki_special_createaccount_hcaptcha_execute_duration_seconds', 2314, { wiki: 'testwiki' } ],
				'should record account creation specific metric for execution time'
			);
			assert.deepEqual(
				this.track.getCall( 8 ).args,
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

	// One script_error_total per failed attempt, plus one load_duration and
	// one load_attempts sample emitted on the terminal outcome, plus the
	// hCaptchaRenderCallback 'error' event from the rejection handler.
	assert.strictEqual( this.track.callCount, 5, 'should invoke mw.track() five times' );
	assert.deepEqual(
		this.track.getCall( 0 ).args,
		[
			'stats.mediawiki_confirmedit_hcaptcha_script_error_total',
			1,
			{ wiki: 'testwiki', interfaceName: 'edit' }
		],
		'should record metric for total errors after the first attempt'
	);
	assert.deepEqual(
		this.track.getCall( 1 ).args,
		[
			'stats.mediawiki_confirmedit_hcaptcha_script_error_total',
			1,
			{ wiki: 'testwiki', interfaceName: 'edit' }
		],
		'should record metric for total errors after the second attempt'
	);
	assert.deepEqual(
		this.track.getCall( 2 ).args,
		[
			'stats.mediawiki_confirmedit_hcaptcha_load_duration_seconds',
			1718,
			{ wiki: 'testwiki', interfaceName: 'edit' }
		],
		'should record metric for load time once on terminal failure'
	);
	assert.deepEqual(
		this.track.getCall( 3 ).args,
		[
			'stats.mediawiki_confirmedit_hcaptcha_load_attempts_total',
			2,
			{ wiki: 'testwiki', interfaceName: 'edit', outcome: 'failure' }
		],
		'should record attempts=MAX_LOAD_ATTEMPTS with outcome=failure on terminal failure'
	);
	assert.deepEqual(
		this.track.getCall( 4 ).args,
		[
			'confirmEdit.hCaptchaRenderCallback',
			'error',
			'edit',
			'generic-error'
		],
		'should emit event for load failure'
	);
	assert.deepEqual(
		this.window.document.head.removeChild.callCount,
		1,
		'should remove previous script tags for the hCaptcha SDK between reties'
	);

	// One time per load attempt
	assert.strictEqual(
		this.logError.callCount,
		2,
		'should invoke mw.errorLogger.logError() twice'
	);

	const logFirstCallErrorArguments = this.logError.getCall( 0 ).args;
	assert.deepEqual(
		logFirstCallErrorArguments[ 0 ].message,
		'Unable to load hCaptcha script',
		'should use correct channel for errors'
	);
	assert.deepEqual(
		logFirstCallErrorArguments[ 1 ],
		'error.confirmedit',
		'should use correct channel for errors'
	);

	const logSecondCallErrorArguments = this.logError.getCall( 0 ).args;
	assert.deepEqual(
		logSecondCallErrorArguments[ 0 ].message,
		'Unable to load hCaptcha script',
		'should use correct channel for errors'
	);
	assert.deepEqual(
		logSecondCallErrorArguments[ 1 ],
		'error.confirmedit',
		'should use correct channel for errors'
	);
} );

QUnit.test( 'should surface irrecoverable workflow execution errors as soon as possible', async function ( assert ) {
	// Explicitly set an unknown value here to test the unknown interface handling
	mw.config.set( 'wgAction', 'unknown' );

	this.window.document.head.appendChild.callsFake( () => {
		assert.false( this.isLoadingIndicatorVisible(), 'should not show loading indicator prior to execute' );
		this.window.onHCaptchaSDKLoaded();
	} );
	this.window.hcaptcha.render.returns( 'some-captcha-id' );
	this.window.hcaptcha.execute.callsFake( () => {
		assert.true( this.isLoadingIndicatorVisible(), 'loading indicator should be visible until hCaptcha finishes' );
		assert.true( this.areSubmitButtonsDisabled(), 'submit buttons should be disabled during execute' );
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
	assert.false( this.areSubmitButtonsDisabled(), 'submit buttons should be re-enabled on error' );

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

	assert.strictEqual( this.track.callCount, 5, 'should invoke mw.track() five times' );
	assert.deepEqual(
		this.track.getCall( 0 ).args,
		[ 'stats.mediawiki_confirmedit_hcaptcha_load_duration_seconds', 1718, { wiki: 'testwiki', interfaceName: 'unknown' } ],
		'should record metric for load time'
	);
	assert.deepEqual(
		this.track.getCall( 1 ).args,
		[ 'stats.mediawiki_confirmedit_hcaptcha_load_attempts_total', 1, { wiki: 'testwiki', interfaceName: 'unknown', outcome: 'success' } ],
		'should record metric for load attempts'
	);
	assert.deepEqual(
		this.track.getCall( 2 ).args,
		[ 'stats.mediawiki_confirmedit_hcaptcha_execute_total', 1, { wiki: 'testwiki', interfaceName: 'unknown' } ],
		'should emit event for execution'
	);
	assert.deepEqual(
		this.track.getCall( 3 ).args,
		[ 'stats.mediawiki_confirmedit_hcaptcha_execute_duration_seconds', 2314, { wiki: 'testwiki', interfaceName: 'unknown' } ],
		'should record metric for load time'
	);
	assert.deepEqual(
		this.track.getCall( 4 ).args,
		[ 'stats.mediawiki_confirmedit_hcaptcha_execute_workflow_error_total', 1, { wiki: 'testwiki', interfaceName: 'unknown', code: 'generic_error' } ],
		'should emit event for execution failure'
	);
} );

QUnit.test.each( 'should surface recoverable workflow execution errors on submit', {
	'challenge-closed': {
		error: 'challenge-closed',
		message: 'hcaptcha-challenge-closed'
	},
	'challenge-expired': {
		error: 'challenge-expired',
		message: 'hcaptcha-challenge-expired'
	},
	'internal-error': {
		error: 'internal-error',
		message: 'hcaptcha-internal-error'
	},
	'network-error': {
		error: 'network-error',
		message: 'hcaptcha-network-error'
	},
	'rate-limited': {
		error: 'rate-limited',
		message: 'hcaptcha-rate-limited'
	}
}, function ( assert, data ) {
	this.window.document.head.appendChild.callsFake( () => {
		assert.false( this.isLoadingIndicatorVisible(), 'should not show loading indicator prior to execute' );
		this.window.onHCaptchaSDKLoaded();
	} );

	this.window.hcaptcha.render.returns( 'some-captcha-id' );
	this.window.hcaptcha.execute.callsFake( () => {
		assert.true( this.isLoadingIndicatorVisible(), 'loading indicator should be visible during execute' );
		assert.true( this.areSubmitButtonsDisabled(), 'submit buttons should be disabled during execute' );
		return Promise.reject( data.error );
	} );

	useSecureEnclave( this.window );

	const formSubmitted = new Promise( ( resolve ) => {
		this.$form.one( 'submit', () => setTimeout( resolve ) );
	} );

	this.$form.find( '[name=some-input]' ).trigger( 'input' );
	this.$form.trigger( 'submit' );

	return formSubmitted.then( () => {
		assert.false( this.isLoadingIndicatorVisible(), 'should hide loading indicator' );
		assert.false( this.areSubmitButtonsDisabled(), 'submit buttons should be re-enabled on recoverable error' );

		assert.true( this.submit.notCalled, 'submit should have been prevented' );

		assert.notStrictEqual(
			this.$form.find( '.cdx-message' ).css( 'display' ),
			'none',
			'error message container should be visible'
		);
		assert.strictEqual(
			this.$form.find( '.cdx-message' ).text(),
			`(${ data.message })`,
			`error message should be set to (${ data.message })`
		);
	} );
} );

QUnit.test( 'should allow recovering from a recoverable error by starting a new workflow', function ( assert ) {
	this.window.document.head.appendChild.callsFake( () => {
		assert.false( this.isLoadingIndicatorVisible(), 'should not show loading indicator prior to execute' );
		this.window.onHCaptchaSDKLoaded();
	} );

	this.window.hcaptcha.render.returns( 'some-captcha-id' );
	this.window.hcaptcha.execute
		.onFirstCall().callsFake( () => {
			assert.true( this.areSubmitButtonsDisabled(), 'submit buttons should be disabled during first execute' );
			return Promise.reject( 'challenge-closed' );
		} )
		.onSecondCall().callsFake( () => {
			assert.true( this.areSubmitButtonsDisabled(), 'submit buttons should be disabled during second execute' );
			return Promise.resolve( { response: 'some-token' } );
		} );

	const result = useSecureEnclave( this.window )
		.then( () => {
			assert.false( this.isLoadingIndicatorVisible(), 'should hide loading indicator' );
			assert.false( this.areSubmitButtonsDisabled(), 'submit buttons should be re-enabled after success' );

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
		} );

	this.$form.find( '[name=some-input]' ).trigger( 'input' );

	this.$form.one( 'submit', () => setTimeout( () => this.$form.trigger( 'submit' ) ) );

	this.$form.trigger( 'submit' );

	return Promise.all( [ delay(), result ] ).then(
		() => assertSubmissionDone( assert, this )
	);
} );

QUnit.test( 'should disable submit buttons during hCaptcha execution on edit page', function ( assert ) {
	mw.config.set( 'wgAction', 'edit' );
	this.window.document.head.appendChild.callsFake( () => {
		assert.false( this.isLoadingIndicatorVisible(), 'should not show loading indicator prior to execute' );
		this.window.onHCaptchaSDKLoaded();
	} );
	this.window.hcaptcha.render.returns( 'some-captcha-id' );
	this.window.hcaptcha.execute.callsFake( async () => {
		assert.true( this.isLoadingIndicatorVisible(), 'loading indicator should be visible during execute' );
		assert.true( this.areSubmitButtonsDisabled(), 'submit button should be disabled during execute' );
		return { response: 'some-token' };
	} );

	const result = useSecureEnclave( this.window )
		.then( () => {
			assert.false( this.isLoadingIndicatorVisible(), 'should hide loading indicator' );
			assert.false( this.areSubmitButtonsDisabled(), 'submit button should be re-enabled after success' );

			const infuseCall = window.OO.ui.infuse.firstCall;
			assert.strictEqual(
				infuseCall.args[ 0 ][ 0 ],
				document.getElementById( 'wpSaveWidget' ),
				'should call OO.ui.infuse with correct element'
			);

			assert.true( this.window.hcaptcha.render.calledOnce, 'should render hCaptcha widget once' );
			assert.true( this.window.hcaptcha.execute.calledOnce, 'should run hCaptcha once' );
			assert.true( this.submit.calledOnce, 'should submit form once hCaptcha token is available' );
			assert.strictEqual(
				this.$form.find( '#h-captcha-response' ).val(),
				'some-token',
				'should add hCaptcha response token to form'
			);
		} );

	this.$form.find( '[name=some-input]' ).trigger( 'input' );
	this.$form.trigger( 'submit' );

	return result;
} );

QUnit.test( 'should fire the confirmEdit.hCaptcha.executed hook when executeHCaptcha succeeds', async function ( assert ) {
	this.window.document.head.appendChild.callsFake( () => {
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

QUnit.test( 'should submit the form immediately when wgHCaptchaTriggerFormSubmission is set', async function ( assert ) {
	this.window.document.head.appendChild.callsFake( () => {
		assert.false( this.isLoadingIndicatorVisible(), 'should not show loading indicator prior to execute' );
		this.window.onHCaptchaSDKLoaded();
	} );

	this.window.hcaptcha.render.returns( 'some-captcha-id' );
	this.window.hcaptcha.execute.callsFake( async () => ( { response: 'some-token' } ) );

	// (T411963) HCaptcha::setForceShowCaptcha() is called when the editor page is
	// reloaded because an AbuseFilter triggered an hCaptcha workflow, which in
	// turn makes the backend to set this Javascript variable on page reload.
	mw.config.set( 'wgHCaptchaTriggerFormSubmission', true );

	let isSubmitted = false;
	this.$form.on( 'submit', () => {
		isSubmitted = true;
	} );

	// The promise returned by useSecureEnclave() won't resolve
	// until the form is submitted.
	let isResolved = false;
	useSecureEnclave( this.window ).then( () => {
		isResolved = true;
	} );

	assert.false(
		isResolved,
		'useSecureEnclave should not resolve without a form submission'
	);
	assert.false(
		isSubmitted,
		'useSecureEnclave should not submit the form immediately'
	);

	// The form submission waits for the hCaptcha ID promise to resolve before
	// triggering the submission, so we use polling here.
	let iterations = 0;

	// isSubmitted is modified by a form submission event handler.
	// eslint-disable-next-line no-unmodified-loop-condition
	while ( !isSubmitted ) {
		await delay( 10 );
		iterations++;

		if ( iterations === 100 ) {
			assert.true(
				false,
				'Setting the global variable should trigger a form submission'
			);
		}
	}

	assert.false(
		isResolved,
		'Setting the global variable should make useSecureEnclave to remain unresolved'
	);
} );
