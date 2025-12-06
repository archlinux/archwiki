const { executeHCaptcha, loadHCaptcha } = require( 'ext.confirmEdit.hCaptcha/ext.confirmEdit.hCaptcha/utils.js' );

QUnit.module( 'ext.confirmEdit.hCaptcha.utils', QUnit.newMwEnvironment( {
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
	},

	afterEach() {
		this.track.restore();
		this.measure.restore();
		this.logError.restore();
	}
} ) );

QUnit.test( 'should handle exception being thrown by hcaptcha.execute', async function ( assert ) {
	this.window.hcaptcha.execute.throws( new Error( 'generic-failure' ) );

	this.measure.onFirstCall().returns( { duration: 2314 } );

	return executeHCaptcha( this.window, 'captcha-id', 'testinterface' )
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

	return loadHCaptcha( this.window, 'testinterface' )
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

QUnit.test( 'loadHCaptcha should load hCaptcha SDK if previous attempt failed', async function ( assert ) {
	this.window.document.head.appendChild.callsFake( async () => {
		this.window.onHCaptchaSDKLoaded();
	} );

	const $qunitFixture = $( '#qunit-fixture' );

	const script = document.createElement( 'script' );
	script.className = 'mw-confirmedit-hcaptcha-script mw-confirmedit-hcaptcha-script-loading-failed';
	$qunitFixture.append( script );

	assert.true( this.window.document.head.appendChild.notCalled, 'should not have loaded hCaptcha SDK until call' );

	return loadHCaptcha( this.window, 'testinterface' )
		.then( () => {
			assert.true( this.window.document.head.appendChild.calledOnce, 'should load hCaptcha SDK' );
		} )
		.catch( () => {
			// False positive
			// eslint-disable-next-line no-jquery/no-done-fail
			assert.fail( 'Did not expect promise to reject' );
		} );
} );
