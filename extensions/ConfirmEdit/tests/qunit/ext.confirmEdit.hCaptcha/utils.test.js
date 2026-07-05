QUnit.module( 'ext.confirmEdit.hCaptcha.utils', QUnit.newMwEnvironment( {
	beforeEach() {
		mw.config.set( 'wgDBname', 'testwiki' );

		this.track = this.sandbox.stub( mw, 'track' );
		this.logError = this.sandbox.stub( mw.errorLogger, 'logError' );

		// (T411576) Mock for testing that instrumentation code measuring time spent on
		// hCaptcha execution falls back to performance.getEntriesByName() if
		// performance.measure()  does not return the PerformanceMeasure directly.
		this.getEntriesByName = this.sandbox.stub( performance, 'getEntriesByName' );

		// Sinon fake timers as of v21 only return a static fake value from performance.measure(),
		// so use a regular stub instead.
		this.measure = this.sandbox.stub( performance, 'measure' );
		this.measure.returns( { duration: 0 } );

		// (T411576) Mock for testing that instrumentation code measuring time spent on
		// hCaptcha execution falls back to mw.now() if using performance.measure() is
		// not possible.
		this.now = this.sandbox.stub( mw, 'now' );

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
					appendChild: this.sandbox.stub()
				},
				querySelectorAll: this.sandbox.stub().returns( [] )
			},
			performance: {
				measure: this.measure,
				getEntriesByName: this.getEntriesByName
			}
		};

		// Subject under test
		this.utils = require( 'ext.confirmEdit.hCaptcha/ext.confirmEdit.hCaptcha/utils.js' );
	},

	afterEach() {
		this.getEntriesByName.restore();
		this.logError.restore();
		this.measure.restore();
		this.now.restore();
		this.track.restore();

		// The result of require() is cached, so this is needed in order to drop
		// references to widgets added to the page by previous tests.
		this.utils.reset();
	}
} ) );

QUnit.test( 'should handle exception being thrown by hcaptcha.execute', async function ( assert ) {
	this.window.hcaptcha.execute.throws( new Error( 'generic-failure' ) );

	this.measure.onFirstCall().returns( { duration: 2314 } );
	this.measure.onSecondCall().returns( { duration: 1234 } );

	return this.utils.executeHCaptcha( this.window, 'captcha-id', 'testinterface' )
		.then( () => {
			// False positive
			// eslint-disable-next-line no-jquery/no-done-fail
			assert.fail( 'Did not expect promise to fulfill' );
		} )
		.catch( ( error ) => {
			assert.strictEqual(
				error,
				'generic-failure',
				'should return error to caller'
			);

			assert.strictEqual( this.track.callCount, 3, 'should invoke mw.track() three times' );
			assert.deepEqual(
				this.track.getCall( 0 ).args,
				[ 'stats.mediawiki_confirmedit_hcaptcha_execute_total', 1, { wiki: 'testwiki', interfaceName: 'testinterface' } ],
				'should emit event for execution'
			);
			assert.deepEqual(
				this.track.getCall( 1 ).args,
				[ 'stats.mediawiki_confirmedit_hcaptcha_execute_workflow_error_total', 1, { wiki: 'testwiki', interfaceName: 'testinterface', code: 'generic_failure' } ],
				'should emit event for execution'
			);
			assert.deepEqual(
				this.track.getCall( 2 ).args,
				[ 'stats.mediawiki_confirmedit_hcaptcha_execute_duration_seconds', 2314, { wiki: 'testwiki', interfaceName: 'testinterface' } ],
				'should record metric for load time'
			);

			assert.strictEqual( this.logError.callCount, 1, 'should invoke mw.errorLogger.logError() once' );
			const logErrorArguments = this.logError.getCall( 0 ).args;
			assert.deepEqual(
				logErrorArguments[ 0 ].message,
				'generic-failure',
				'should use correct channel for errors'
			);
			assert.deepEqual(
				logErrorArguments[ 1 ],
				'error.confirmedit',
				'should use correct channel for errors'
			);
		} );
} );

QUnit.test( 'loadHCaptcha should return early if previous hCaptcha SDK load succeeded', async function ( assert ) {
	this.window.document.head.appendChild.callsFake( async () => {
		this.window.onHCaptchaSDKLoaded();
	} );

	const $qunitFixture = $( '#qunit-fixture' );

	const script = document.createElement( 'script' );
	script.className = 'mw-confirmedit-hcaptcha-script mw-confirmedit-hcaptcha-script-loading-finished';
	$qunitFixture.append( script );

	this.window.document.querySelectorAll.returns( [ script ] );

	return this.utils.loadHCaptcha( this.window, 'testinterface' )
		.then( () => {
			assert.true( this.window.document.head.appendChild.notCalled, 'should not load hCaptcha SDK' );
			assert.true( this.track.notCalled, 'should not emit hCaptcha performance events' );
		} )
		.catch( () => {
			// False positive
			// eslint-disable-next-line no-jquery/no-done-fail
			assert.fail( 'Did not expect promise to reject' );
		} );
} );

QUnit.test( 'loadHCaptcha emits load_duration and load_attempts=1 on first-attempt success', async function ( assert ) {
	this.window.document.head.appendChild.callsFake( async () => {
		this.window.onHCaptchaSDKLoaded();
	} );

	this.measure.onFirstCall().returns( { duration: 123 } );

	return this.utils.loadHCaptcha( this.window, 'testinterface' ).then( () => {
		const trackCalls = this.track.getCalls().map( ( c ) => c.args );

		assert.deepEqual(
			trackCalls,
			[
				[
					'stats.mediawiki_confirmedit_hcaptcha_load_duration_seconds',
					123,
					{ wiki: 'testwiki', interfaceName: 'testinterface' }
				],
				[
					'stats.mediawiki_confirmedit_hcaptcha_load_attempts_total',
					1,
					{ wiki: 'testwiki', interfaceName: 'testinterface', outcome: 'success' }
				]
			],
			'should emit one duration sample and attempts=1 on terminal success'
		);
	} );
} );

QUnit.test( 'loadHCaptcha emits script_error on each retry but load_duration only on terminal outcome', async function ( assert ) {
	// Override MAX_LOAD_ATTEMPTS and BASE_RETRY_DELAY so the terminal failure
	// branch is reached quickly (2 attempts with 0ms backoff between them).
	mw.config.set( 'wgHCaptchaMaxLoadAttempts', 2 );
	mw.config.set( 'wgHCaptchaBaseRetryDelay', 0 );

	this.measure.onFirstCall().returns( { duration: 456 } );

	// Simulate each script tag insertion failing immediately. `script` holds
	// the most recently-created stub element so the appendChild fake can
	// trigger its onerror handler.
	let script;
	this.window.document.createElement.callsFake( () => {
		script = {
			classList: { contains: this.sandbox.stub().returns( false ) },
			onerror: null
		};
		return script;
	} );
	this.window.document.head.appendChild.callsFake( () => {
		setTimeout( () => script.onerror(), 0 );
	} );
	this.window.document.head.removeChild = this.sandbox.stub();

	return this.utils.loadHCaptcha( this.window, 'testinterface' )
		.then( () => {
			// False positive
			// eslint-disable-next-line no-jquery/no-done-fail
			assert.fail( 'Did not expect promise to fulfill' );
		} )
		.catch( ( error ) => {
			assert.strictEqual( error, 'generic-error', 'should reject with generic-error' );

			const durationCalls = this.track.getCalls()
				.filter( ( c ) => c.args[ 0 ] === 'stats.mediawiki_confirmedit_hcaptcha_load_duration_seconds' );
			assert.strictEqual(
				durationCalls.length,
				1,
				'should emit load_duration exactly once across multiple failed attempts'
			);

			const errorCalls = this.track.getCalls()
				.filter( ( c ) => c.args[ 0 ] === 'stats.mediawiki_confirmedit_hcaptcha_script_error_total' );
			assert.strictEqual(
				errorCalls.length,
				2,
				'should emit script_error_total once per failed attempt'
			);

			const attemptsCalls = this.track.getCalls()
				.filter( ( c ) => c.args[ 0 ] === 'stats.mediawiki_confirmedit_hcaptcha_load_attempts_total' );
			assert.deepEqual(
				attemptsCalls.map( ( c ) => c.args ),
				[ [
					'stats.mediawiki_confirmedit_hcaptcha_load_attempts_total',
					2,
					{ wiki: 'testwiki', interfaceName: 'testinterface', outcome: 'failure' }
				] ],
				'should emit attempts=MAX_LOAD_ATTEMPTS with outcome=failure once'
			);
		} );
} );

QUnit.test( 'loadHCaptcha should load hCaptcha SDK if previous attempt failed', async function ( assert ) {
	this.window.document.head.appendChild.callsFake( async () => {
		this.window.onHCaptchaSDKLoaded();
	} );

	const $qunitFixture = $( '#qunit-fixture' );

	const script = document.createElement( 'script' );
	script.className = 'mw-confirmedit-hcaptcha-script mw-confirmedit-hcaptcha-script-loading-failed';
	$qunitFixture.append( script );

	assert.true( this.window.document.head.appendChild.notCalled, 'should not have loaded hCaptcha SDK until call' );

	return this.utils.loadHCaptcha( this.window, 'testinterface' )
		.then( () => {
			assert.true( this.window.document.head.appendChild.calledOnce, 'should load hCaptcha SDK' );
		} )
		.catch( () => {
			// False positive
			// eslint-disable-next-line no-jquery/no-done-fail
			assert.fail( 'Did not expect promise to reject' );
		} );
} );

QUnit.test( 'renderHCaptcha should instrument events', async function ( assert ) {
	this.utils.renderHCaptcha( this.window, 'testinterface', 'container-id', {} );

	assert.true( this.window.hcaptcha.render.calledOnce, 'should render hCaptcha' );
	assert.strictEqual( this.track.callCount, 0, 'No events should be tracked initially' );

	const actualRenderOptions = this.window.hcaptcha.render.getCall( 0 ).args[ 1 ];

	actualRenderOptions[ 'open-callback' ]();
	assert.strictEqual( this.track.callCount, 2, 'Should track open event' );
	assert.deepEqual(
		this.track.getCall( 0 ).args,
		[
			'stats.mediawiki_confirmedit_hcaptcha_open_callback_total',
			1,
			{ wiki: 'testwiki', interfaceName: 'testinterface' }
		],
		'Should track open event using expected interface'
	);
	assert.deepEqual(
		this.track.getCall( 1 ).args,
		[ 'confirmEdit.hCaptchaRenderCallback', 'open', 'testinterface' ],
		'Should track open event using expected interface'
	);

	actualRenderOptions[ 'close-callback' ]();
	assert.strictEqual( this.track.callCount, 3, 'Should track close event' );
	assert.deepEqual(
		this.track.getCall( 2 ).args,
		[ 'confirmEdit.hCaptchaRenderCallback', 'close', 'testinterface' ],
		'Should track close event using expected interface'
	);

	actualRenderOptions[ 'expired-callback' ]();
	assert.strictEqual( this.track.callCount, 4, 'Should track expired event' );
	assert.deepEqual(
		this.track.getCall( 3 ).args,
		[ 'confirmEdit.hCaptchaRenderCallback', 'expired', 'testinterface' ],
		'Should track close event using expected interface'
	);

	actualRenderOptions[ 'chalexpired-callback' ]();
	assert.strictEqual( this.track.callCount, 5, 'Should track close event' );
	assert.deepEqual(
		this.track.getCall( 4 ).args,
		[ 'confirmEdit.hCaptchaRenderCallback', 'chalexpired', 'testinterface' ],
		'Should track close event using expected interface'
	);

	actualRenderOptions[ 'error-callback' ]( 'error-code' );
	assert.strictEqual( this.track.callCount, 6, 'Should track close event' );
	assert.deepEqual(
		this.track.getCall( 5 ).args,
		[ 'confirmEdit.hCaptchaRenderCallback', 'error', 'testinterface', 'error-code' ],
		'Should track close event using expected interface'
	);
} );

QUnit.test( 'renderHCaptcha should use provided renderOptions', async function ( assert ) {
	const renderOptions = {
		'open-callback': this.sandbox.stub(),
		'close-callback': this.sandbox.stub(),
		'chalexpired-callback': this.sandbox.stub(),
		'expired-callback': this.sandbox.stub(),
		'error-callback': this.sandbox.stub(),
		callback: this.sandbox.stub(),
		sitekey: 'sitekey',
		'challenge-container': 'challenge-container-id'
	};

	this.utils.renderHCaptcha( this.window, 'testinterface', 'container-id', renderOptions );

	assert.true( this.window.hcaptcha.render.calledOnce, 'should render hCaptcha' );
	assert.strictEqual( this.track.callCount, 0, 'No events should be tracked initially' );

	const actualRenderOptions = this.window.hcaptcha.render.getCall( 0 ).args[ 1 ];

	assert.strictEqual(
		actualRenderOptions.sitekey,
		renderOptions.sitekey,
		'Custom sitekey should be used'
	);
	assert.strictEqual(
		actualRenderOptions[ 'challenge-container' ],
		renderOptions[ 'challenge-container' ],
		'challenge-container should be used'
	);

	actualRenderOptions[ 'open-callback' ]();
	assert.true(
		renderOptions[ 'open-callback' ].calledOnce,
		'open-callback should be called'
	);

	actualRenderOptions[ 'close-callback' ]();
	assert.true(
		renderOptions[ 'close-callback' ].calledOnce,
		'close-callback should be called'
	);

	actualRenderOptions[ 'expired-callback' ]();
	assert.true(
		renderOptions[ 'expired-callback' ].calledOnce,
		'expired-callback should be called'
	);

	actualRenderOptions[ 'chalexpired-callback' ]();
	assert.true(
		renderOptions[ 'chalexpired-callback' ].calledOnce,
		'chalexpired-callback should be called'
	);

	actualRenderOptions[ 'error-callback' ]( 'error-code' );
	assert.true(
		renderOptions[ 'error-callback' ].calledOnce,
		'error-callback should be called'
	);
} );

QUnit.test( 'getDuration falls back to getEntriesByName() if measure() does not return a value', async function ( assert ) {
	this.window.document.head.appendChild.callsFake( async () => {
		this.window.onHCaptchaSDKLoaded();
	} );

	// (T411576) Fake the behavior of older browsers which don't return a
	// PerformanceMeasure object when calling performance.measure().
	this.measure.onFirstCall().returns( undefined );

	this.getEntriesByName.returns( [
		{ duration: 123 }
	] );

	// loadHCaptcha() sets an onHCaptchaSDKLoaded listener that calls
	// trackPerformanceTiming(). In turn, that calls getDuration(), which has a
	// fallback to getEntriesByName() if performance.measure() returns undefined.
	// Most browsers added support for getEntriesByName() in 2015 or earlier,
	// while returning a PerformanceMeasure object started around 2020.
	return this.utils.loadHCaptcha( this.window, 'testinterface' ).then( () => {
		assert.strictEqual(
			this.measure.callCount,
			1,
			'should have tried to use performance.measure first'
		);
		assert.strictEqual(
			this.getEntriesByName.callCount,
			1,
			'should fall back to getEntriesByName'
		);
		// Note getPerformanceStartMark() always calls mw.now() at least once in
		// order to build the timestamp associated with the startMark (the value
		// is only used if other measuring methods fail).
		assert.strictEqual(
			this.now.callCount,
			1,
			'should not fall back to mw.now() if getEntriesByName is available'
		);
	} );
} );

QUnit.test( 'getDuration falls back to mw.now() as a last resort', async function ( assert ) {
	this.window.document.head.appendChild.callsFake( async () => {
		this.window.onHCaptchaSDKLoaded();
	} );

	// (T411576) Fake the behavior of older browsers which don't return a
	// PerformanceMeasure object when calling performance.measure(), and make
	// getEntriesByName() to return empty data, which makes the fallback to
	// mw.now() to be triggered.
	this.measure.onFirstCall().returns( undefined );
	this.getEntriesByName.onFirstCall().returns( [] );

	this.now.returns( 987 );

	// loadHCaptcha() sets an onHCaptchaSDKLoaded listener that calls
	// trackPerformanceTiming(). In turn, that calls getDuration(), which has a
	// fallback to getEntriesByName() if performance.measure() returns undefined.
	// Most browsers added support for getEntriesByName() in 2015 or earlier,
	// while returning a PerformanceMeasure object started around 2020.
	return this.utils.loadHCaptcha( this.window, 'testinterface' ).then( () => {
		assert.strictEqual(
			this.measure.callCount,
			1,
			'should have tried to use performance.measure first'
		);
		assert.strictEqual(
			this.getEntriesByName.callCount,
			1,
			'should fall back to getEntriesByName first'
		);
		// Note getPerformanceStartMark() always calls mw.now() at least once in
		// order to build the timestamp associated with the startMark (the value
		// is only used if other measuring methods fail). The second call is
		// used in order to actually measure the duration based on that value.
		assert.strictEqual(
			this.now.callCount,
			2,
			'should fall back to mw.now() last'
		);
	} );
} );

const defaultRecoverableErrors = [
	'challenge-closed',
	'challenge-expired',
	'internal-error',
	'network-error',
	'rate-limited'
];

QUnit.test.each(
	'getRecoverableErrors returns the expected error codes',
	[
		{ interfaceName: null, expected: defaultRecoverableErrors },
		{ interfaceName: 'createaccount', expected: defaultRecoverableErrors },
		{ interfaceName: 'edit', expected: defaultRecoverableErrors },
		{
			interfaceName: 'mobilefrontend-editor',
			expected: defaultRecoverableErrors.filter(
				// The MobileFrontend should not handle closing the challenge as
				// an error.
				//
				// 'challenge-closed' is triggered whenever the user clicks
				// outside the challenge popup, and handling it as recoverable
				// would cause it to be shown again if the user clicks outside
				// it in order to close it, making it impossible to dismiss the
				// dialog in order to make changes in the edit summary.
				( e ) => e !== 'challenge-closed'
			)
		}
	],
	function ( assert, data ) {
		const errors = this.utils.getRecoverableErrors(
			data.interfaceName
		);

		// Arrays are sorted so that they match regardless of the order of their
		// elements. slice() is used to prevent modifying the underlying array
		// returned by getRecoverableErrors().
		assert.deepEqual(
			errors.sort(),
			data.expected.sort(),
			`Unexpected recoverable errors for ${ data.interfaceName }`
		);
	}
);
