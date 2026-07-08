'use strict';

const ipRevealUtils = require( 'ext.checkUser.tempAccounts/ipRevealUtils.js' );
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

QUnit.test( 'Test getAutoRevealStatus with expiry too far in the future', function ( assert ) {
	mw.config.set( 'wgCheckUserTemporaryAccountAutoRevealAllowed', true );
	const maximumExpiry = 86400;
	const invalidExpiry = Math.round( this.mockTime / 1000 ) + maximumExpiry + 100;
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
						[ autoRevealPreferenceName ]: invalidExpiry
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
		assert.strictEqual( status, false, 'Should return false when expiry is set too far in the future' );
	} );
} );

QUnit.test( 'Test getAutoRevealStatus with expiry in the future', function ( assert ) {
	mw.config.set( 'wgCheckUserTemporaryAccountAutoRevealAllowed', true );
	mw.config.set( 'wgCheckUserAutoRevealMaximumExpiry', 86400 );
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
	mw.config.set( 'wgCheckUserAutoRevealMaximumExpiry', 86400 );
	const relativeExpiry = 3600;
	const expectedExpiry = Math.floor( this.mockTime / 1000 ) + relativeExpiry;
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
		assert.strictEqual(
			mw.user.options.get( 'checkuser-temporary-account-enable-auto-reveal' ),
			String( expectedExpiry )
		);
	} );
} );

QUnit.test( 'Test setAutoRevealStatus (enable, maximum expiry)', function ( assert ) {
	mw.config.set( 'wgCheckUserAutoRevealMaximumExpiry', 86400 );
	const relativeExpiry = 86400;
	const expectedExpiry = Math.floor( this.mockTime / 1000 ) + relativeExpiry;
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
		assert.strictEqual(
			mw.user.options.get( 'checkuser-temporary-account-enable-auto-reveal' ),
			String( expectedExpiry )
		);
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
		assert.strictEqual(
			mw.user.options.get( 'checkuser-temporary-account-enable-auto-reveal' ),
			null
		);
	} );
} );

function performGetUserNameFromUrlTest( assert, url, expectedUserName ) {
	// On purpose the special page name is set not to the default "Contributions",
	// so that we can verify that the "localized" name is used.
	mw.config.set( 'wgCheckUserContribsPageLocalName', 'Cntrbs' );
	const result = ipRevealUtils.getUserNameFromUrl( url );
	assert.strictEqual( result, expectedUserName, `URL: ${ url }` );
}

QUnit.test( 'Test getUserNameFromUrl, link to user page through /wiki/',
	( assert ) => performGetUserNameFromUrlTest(
		assert,
		mw.util.getUrl( 'User:Example_User' ),
		'Example User'
	)
);

QUnit.test( 'Test getUserNameFromUrl, link to user page through /w/',
	( assert ) => performGetUserNameFromUrlTest(
		assert,
		mw.util.getUrl( 'User:Example_User', { action: 'edit' } ),
		'Example User'
	)
);

QUnit.test( 'Test getUserNameFromUrl, link to user contributions through /wiki/',
	( assert ) => performGetUserNameFromUrlTest(
		assert,
		mw.util.getUrl( 'Special:Cntrbs/Example_User' ),
		'Example User'
	)
);

QUnit.test( 'Test getUserNameFromUrl, link to user contributions through /w/',
	( assert ) => performGetUserNameFromUrlTest(
		assert,
		mw.util.getUrl( 'Special:Cntrbs/Example_User', { uselang: 'qqx' } ),
		'Example User'
	)
);

QUnit.test( 'Test getUserNameFromUrl, link to user talk page through /wiki/',
	( assert ) => performGetUserNameFromUrlTest(
		assert,
		mw.util.getUrl( 'User_talk:Example_User' ),
		undefined
	)
);

QUnit.test( 'Test getUserNameFromUrl, link to user talk page through /w/',
	( assert ) => performGetUserNameFromUrlTest(
		assert,
		mw.util.getUrl( 'User_talk:Example_User', { action: 'edit' } ),
		undefined
	)
);

QUnit.test( 'Test getUserNameFromUrl, link to user page with diacritics through /wiki/',
	( assert ) => performGetUserNameFromUrlTest(
		assert,
		mw.util.getUrl( 'User:Ťęśŧ' ),
		'Ťęśŧ'
	)
);

QUnit.test( 'Test getUserNameFromUrl, link to user page with diacritics through /w/',
	( assert ) => performGetUserNameFromUrlTest(
		assert,
		mw.util.getUrl( 'User:Ťęśŧ', { action: 'edit' } ),
		'Ťęśŧ'
	)
);
