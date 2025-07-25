'use strict';

const ipRevealUtils = require( '../../../modules/ext.checkUser.tempAccounts/ipRevealUtils.js' );
const autoRevealPreferenceName = 'checkuser-temporary-account-enable-auto-reveal';

QUnit.module( 'ext.checkUser.tempAccounts.ipRevealUtils', QUnit.newMwEnvironment( {
	beforeEach() {
		this.dateNow = sinon.stub( Date, 'now' );

		// Simulate a consistent time in tests.
		this.mockTime = 1746185208561;
		this.dateNow.returns( this.mockTime );
	},
	afterEach() {
		this.dateNow.restore();
	}
} ) );

QUnit.test( 'Test getRevealedStatus when no value set', ( assert ) => {
	assert.strictEqual(
		ipRevealUtils.getRevealedStatus( 'abcdef' ),
		null,
		'getRevealedStatus return value when setRevealedStatus has not been called'
	);
} );

QUnit.test( 'Test setRevealedStatus', ( assert ) => {
	mw.config.set( 'wgCheckUserTemporaryAccountMaxAge', 1500 );
	ipRevealUtils.setRevealedStatus( 'abcdef' );
	assert.strictEqual(
		ipRevealUtils.getRevealedStatus( 'abcdef' ),
		'true',
		'getRevealedStatus return value after setRevealedStatus is called'
	);
	// Remove the cookie after the test to avoid breaking other tests.
	mw.storage.remove( 'mw-checkuser-temp-abcdef' );
} );

QUnit.test( 'Test getAutoRevealStatus when no value set', function ( assert ) {
	mw.config.set( 'wgCheckUserTemporaryAccountAutoRevealAllowed', true );
	const apiMock = this.sandbox.mock( mw.Api.prototype );
	apiMock.expects( 'get' )
		.withArgs( {
			action: 'query',
			meta: 'globalpreferences',
			gprprop: 'preferences'
		} )
		.returns( $.Deferred().resolve( {
			query: {
				globalpreferences: {
					preferences: {}
				}
			}
		} ) );

	return ipRevealUtils.getAutoRevealStatus().then( ( status ) => {
		assert.strictEqual( status, false, 'Should return false when preference is not set' );
	} );
} );

QUnit.test( 'Test getAutoRevealStatus with expiry in the past', function ( assert ) {
	mw.config.set( 'wgCheckUserTemporaryAccountAutoRevealAllowed', true );
	const pastTimestamp = Math.round( this.mockTime / 1000 ) - 100;
	const apiMock = this.sandbox.mock( mw.Api.prototype );
	apiMock.expects( 'get' )
		.withArgs( {
			action: 'query',
			meta: 'globalpreferences',
			gprprop: 'preferences'
		} )
		.returns( $.Deferred().resolve( {
			query: {
				globalpreferences: {
					preferences: {
						[ autoRevealPreferenceName ]: pastTimestamp
					}
				}
			}
		} ) );
	apiMock.expects( 'postWithToken' )
		.withArgs( 'csrf', {
			action: 'globalpreferences',
			optionname: autoRevealPreferenceName,
			optionvalue: undefined
		} )
		.returns( $.Deferred().resolve() );

	return ipRevealUtils.getAutoRevealStatus().then( ( status ) => {
		assert.strictEqual( status, false, 'Should return false when expiry is set to a past timestamp' );
	} );
} );

QUnit.test( 'Test getAutoRevealStatus with expiry in the future', function ( assert ) {
	mw.config.set( 'wgCheckUserTemporaryAccountAutoRevealAllowed', true );
	const futureTimestamp = Math.round( this.mockTime / 1000 ) + 3600;
	const apiMock = this.sandbox.mock( mw.Api.prototype );
	apiMock.expects( 'get' )
		.withArgs( {
			action: 'query',
			meta: 'globalpreferences',
			gprprop: 'preferences'
		} )
		.returns( $.Deferred().resolve( {
			query: {
				globalpreferences: {
					preferences: {
						[ autoRevealPreferenceName ]: futureTimestamp
					}
				}
			}
		} ) );

	return ipRevealUtils.getAutoRevealStatus().then( ( status ) => {
		assert.strictEqual( status, futureTimestamp, 'Should return the expiry when it is set to a future timestamp' );
	} );
} );

QUnit.test( 'Test getAutoRevealStatus with API failure', function ( assert ) {
	mw.config.set( 'wgCheckUserTemporaryAccountAutoRevealAllowed', true );
	const apiMock = this.sandbox.mock( mw.Api.prototype );
	apiMock.expects( 'get' )
		.withArgs( {
			action: 'query',
			meta: 'globalpreferences',
			gprprop: 'preferences'
		} )
		.returns( $.Deferred().reject() );

	return ipRevealUtils.getAutoRevealStatus().then( ( status ) => {
		assert.strictEqual( status, false, 'Should return false when API request fails' );
	} );
} );

QUnit.test( 'Test setAutoRevealStatus (enable)', function ( assert ) {
	const relativeExpiry = 3600;
	const expectedExpiry = Math.round( this.mockTime / 1000 ) + relativeExpiry;
	const apiMock = this.sandbox.mock( mw.Api.prototype );
	apiMock.expects( 'postWithToken' )
		.withArgs( 'csrf', {
			action: 'globalpreferences',
			optionname: autoRevealPreferenceName,
			optionvalue: expectedExpiry
		} )
		.returns( $.Deferred().resolve() );

	return ipRevealUtils.setAutoRevealStatus( relativeExpiry ).then( () => {
		assert.true( true, 'setAutoRevealStatus should resolve successfully' );
	} );
} );

QUnit.test( 'Test setAutoRevealStatus (disable)', function ( assert ) {
	const apiMock = this.sandbox.mock( mw.Api.prototype );
	apiMock.expects( 'postWithToken' )
		.withArgs( 'csrf', {
			action: 'globalpreferences',
			optionname: autoRevealPreferenceName,
			optionvalue: undefined
		} )
		.returns( $.Deferred().resolve() );

	return ipRevealUtils.setAutoRevealStatus().then( () => {
		assert.true( true, 'setAutoRevealStatus should resolve successfully when disabling' );
	} );
} );
