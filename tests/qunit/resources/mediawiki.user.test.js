( function () {
	var CLIENT_PREF_COOKIE_NAME = 'mwclientpreferences';
	var docClass;
	QUnit.module( 'mediawiki.user', QUnit.newMwEnvironment( {
		beforeEach: function () {
			docClass = document.documentElement.getAttribute( 'class' );
			document.documentElement.setAttribute( 'class', '' );
			// reset any cookies
			mw.cookie.set( CLIENT_PREF_COOKIE_NAME, null );
			this.server = this.sandbox.useFakeServer();
			this.server.respondImmediately = true;
			// Cannot stub by simple assignment because read-only.
			// Instead, stub in tests by using 'delete', and re-create
			// in teardown using the original descriptor (including its
			// accessors and readonly settings etc.)
			this.crypto = Object.getOwnPropertyDescriptor( window, 'crypto' );
			this.msCrypto = Object.getOwnPropertyDescriptor( window, 'msCrypto' );
		},
		afterEach: function () {
			mw.cookie.set( CLIENT_PREF_COOKIE_NAME, null );
			document.documentElement.setAttribute( 'class', docClass );
			if ( this.crypto ) {
				Object.defineProperty( window, 'crypto', this.crypto );
			}
			if ( this.msCrypto ) {
				Object.defineProperty( window, 'msCrypto', this.msCrypto );
			}
		}
	} ) );

	QUnit.test( 'options', function ( assert ) {
		assert.true( mw.user.options instanceof mw.Map, 'options instance of mw.Map' );
	} );

	QUnit.test( 'getters (anonymous)', function ( assert ) {
		// Forge an anonymous user
		mw.config.set( 'wgUserName', null );
		mw.config.set( 'wgUserId', null );

		assert.strictEqual( mw.user.getName(), null, 'getName()' );
		assert.strictEqual( mw.user.isAnon(), true, 'isAnon()' );
		assert.strictEqual( mw.user.getId(), 0, 'getId()' );
	} );

	QUnit.test( 'getters (logged-in)', function ( assert ) {
		mw.config.set( 'wgUserName', 'John' );
		mw.config.set( 'wgUserIsTemp', false );
		mw.config.set( 'wgUserId', 123 );

		assert.strictEqual( mw.user.getName(), 'John', 'getName()' );
		assert.strictEqual( mw.user.isAnon(), false, 'isAnon()' );
		assert.strictEqual( mw.user.getId(), 123, 'getId()' );
		assert.strictEqual( mw.user.isNamed(), true, 'isNamed()' );
		assert.strictEqual( mw.user.isTemp(), false, 'isTemp()' );

		assert.strictEqual( mw.user.id(), 'John', 'user.id()' );
	} );

	QUnit.test( 'getGroups (callback)', function ( assert ) {
		var done = assert.async();
		mw.config.set( 'wgUserGroups', [ '*', 'user' ] );

		mw.user.getGroups( function ( groups ) {
			assert.deepEqual( groups, [ '*', 'user' ], 'Result' );
			done();
		} );
	} );

	QUnit.test( 'getGroups (Promise)', function ( assert ) {
		mw.config.set( 'wgUserGroups', [ '*', 'user' ] );

		return mw.user.getGroups().then( function ( groups ) {
			assert.deepEqual( groups, [ '*', 'user' ], 'Result' );
		} );
	} );

	QUnit.test( 'getRights (callback)', function ( assert ) {
		var done = assert.async();

		this.server.respond( [ 200, { 'Content-Type': 'application/json' },
			'{ "query": { "userinfo": { "groups": [ "unused" ], "rights": [ "read", "edit", "createtalk" ] } } }'
		] );

		mw.user.getRights( function ( rights ) {
			assert.deepEqual( rights, [ 'read', 'edit', 'createtalk' ], 'Result (callback)' );
			done();
		} );
	} );

	QUnit.test( 'getRights (Promise)', function ( assert ) {
		this.server.respond( [ 200, { 'Content-Type': 'application/json' },
			'{ "query": { "userinfo": { "groups": [ "unused" ], "rights": [ "read", "edit", "createtalk" ] } } }'
		] );

		return mw.user.getRights().then( function ( rights ) {
			assert.deepEqual( rights, [ 'read', 'edit', 'createtalk' ], 'Result (promise)' );
		} );
	} );

	QUnit.test( 'generateRandomSessionId', function ( assert ) {
		var result, result2;

		result = mw.user.generateRandomSessionId();
		assert.strictEqual( typeof result, 'string', 'type' );
		assert.strictEqual( result.trim(), result, 'no whitespace at beginning or end' );
		assert.strictEqual( result.length, 20, 'size' );

		result2 = mw.user.generateRandomSessionId();
		assert.notStrictEqual( result, result2, 'different when called multiple times' );

	} );

	QUnit.test( 'generateRandomSessionId (fallback)', function ( assert ) {
		var result, result2;

		// Pretend crypto API is not there to test the Math.random fallback
		delete window.crypto;
		delete window.msCrypto;
		// Assert that the above actually worked. If we use the wrong method
		// of stubbing, JavaScript silently continues and we need to know that
		// it was the wrong method. As of writing, assigning undefined is
		// ineffective as the window property for Crypto is read-only.
		// However, deleting does work. (T203275)
		assert.strictEqual( window.crypto || window.msCrypto, undefined, 'fallback is active' );

		result = mw.user.generateRandomSessionId();
		assert.strictEqual( typeof result, 'string', 'type' );
		assert.strictEqual( result.trim(), result, 'no whitespace at beginning or end' );
		assert.strictEqual( result.length, 20, 'size' );

		result2 = mw.user.generateRandomSessionId();
		assert.notStrictEqual( result, result2, 'different when called multiple times' );
	} );

	QUnit.test( 'getPageviewToken', function ( assert ) {
		var result = mw.user.getPageviewToken(),
			result2 = mw.user.getPageviewToken();
		assert.strictEqual( typeof result, 'string', 'type' );
		assert.strictEqual( /^[a-f0-9]{20}$/.test( result ), true, '20 HEX symbols string' );
		assert.strictEqual( result2, result, 'sticky' );
	} );

	QUnit.test( 'sessionId', function ( assert ) {
		var result = mw.user.sessionId(),
			result2 = mw.user.sessionId();
		assert.strictEqual( typeof result, 'string', 'type' );
		assert.strictEqual( result.trim(), result, 'no leading or trailing whitespace' );
		assert.strictEqual( result2, result, 'retained' );
	} );

	QUnit.test( 'clientPrefs.get: client preferences are always obtained from HTML element the one source of truth', function ( assert ) {
		document.documentElement.setAttribute( 'class', 'client-js font-size-clientpref-1 font-size-clientpref-unrelated-class invalid-clientpref-bad-value ambiguous-clientpref-off ambiguous-clientpref-on' );
		var result = mw.user.clientPrefs.get( 'font-size' );
		var badValue = mw.user.clientPrefs.get( 'invalid' );
		var ambiguousValue = mw.user.clientPrefs.get( 'ambiguous' );
		assert.strictEqual( result, '1', 'client preferences are read from HTML element' );
		assert.strictEqual( badValue, false, 'classes in the wrong format are ignored.' );
		assert.strictEqual( ambiguousValue, false, 'ambiguous values are resolved to false' );
	} );

	QUnit.test( 'clientPrefs.get: client preferences never read from cookie', function ( assert ) {
		mw.cookie.set( CLIENT_PREF_COOKIE_NAME, 'unknown~500' );
		var resultUnknown = mw.user.clientPrefs.get( 'unknown' );
		assert.false( resultUnknown,
			'if an appropriate class is not on the HTML element it returns false even if there is a value in the cookie' );
	} );

	QUnit.test( 'clientPrefs.set: can set client valid preferences', function ( assert ) {
		document.documentElement.classList.add( 'limited-width-clientpref-1', 'font-size-clientpref-100' );
		var resultLimitedWidth = mw.user.clientPrefs.set( 'limited-width', '0' );
		var resultFontSize = mw.user.clientPrefs.set( 'font-size', '10' );
		assert.true( resultLimitedWidth, 'the client preference limited width was set correctly' );
		assert.true( resultFontSize, 'the client preference font size was set correctly' );
		assert.true( document.documentElement.classList.contains( 'limited-width-clientpref-0' ),
			'the limited width class on the document was updated' );
		assert.true( document.documentElement.classList.contains( 'font-size-clientpref-10' ),
			'the font size classes on the document was updated correctly' );
		const mwclientpreferences = mw.cookie.get( CLIENT_PREF_COOKIE_NAME );
		assert.true(
			mwclientpreferences &&
			mwclientpreferences.includes( 'limited-width-clientpref-0' ) &&
				mwclientpreferences.includes( 'font-size-clientpref-10' ),
			'cookie was set correctly'
		);
	} );

	QUnit.test( 'clientPrefs.set: cannot set invalid valid preferences', function ( assert ) {
		document.documentElement.classList.add( 'client-js' );
		var result = mw.user.clientPrefs.set( 'client', 'nojs' );
		assert.false( result, 'the client preference was rejected (lacking -clientpref- suffix)' );
		assert.true( document.documentElement.classList.contains( 'client-js' ), 'the classes on the document were not changed' );
	} );

	QUnit.test( 'clientPrefs.set: cannot set preferences with invalid characters', function ( assert ) {
		document.documentElement.setAttribute( 'class', 'client-js bar-clientpref-1' );
		[
			[ 'client-js bar', 'nojs' ],
			[ 'bar-clientpref', '50' ],
			[ 'bar', ' client-nojs' ],
			[ 'bar', 'client-nojs' ],
			[ '', 'nothing' ],
			[ 'feature', '' ],
			[ 'foo!client~no-js!bar', 'hi' ]
		].forEach( function ( test, i ) {
			var result = mw.user.clientPrefs.set( test[ 0 ], test[ 1 ] );
			assert.false( result, 'the client preference was rejected (invalid characters in name) (test case ' + i + ')' );
		} );

		assert.strictEqual( document.documentElement.getAttribute( 'class' ), 'client-js bar-clientpref-1' );
	} );

	QUnit.test( 'clientPrefs.set: always set cookie when manipulating preferences', function ( assert ) {
		document.documentElement.setAttribute( 'class', 'dark-mode-clientpref-enabled' );
		var result = mw.user.clientPrefs.set( 'dark-mode', 'disabled' );
		assert.true( result, 'the client preference was stored successfully' );
		assert.strictEqual(
			document.documentElement.getAttribute( 'class' ),
			'dark-mode-clientpref-disabled',
			'the class was modified'
		);
		let mwclientpreferences = mw.cookie.get( CLIENT_PREF_COOKIE_NAME );
		assert.strictEqual(
			mwclientpreferences,
			'dark-mode-clientpref-disabled',
			'it was stored to a cookie'
		);
		result = mw.user.clientPrefs.set( 'dark-mode', 'enabled' );
		assert.true( result, 'the 2nd client preference was also stored successfully' );
		assert.strictEqual(
			document.documentElement.getAttribute( 'class' ),
			'dark-mode-clientpref-enabled',
			'the class was also modified again'
		);
		mwclientpreferences = mw.cookie.get( CLIENT_PREF_COOKIE_NAME );
		assert.strictEqual(
			mwclientpreferences,
			'dark-mode-clientpref-enabled',
			'always store even if it matches default as we have no knowledge of what the default could be'
		);
	} );

	QUnit.test( 'clientPrefs.set: Only store values that are explicitly set', function ( assert ) {
		// set cookie and body classes
		document.documentElement.setAttribute( 'class', 'client-js not-a-feature-clientpref-bad-value font-clientpref-32 dark-mode-clientpref-32 limited-width-clientpref-enabled' );
		mw.user.clientPrefs.set( 'dark-mode', 'enabled' );
		const mwclientpreferences = mw.cookie.get( CLIENT_PREF_COOKIE_NAME );
		assert.strictEqual(
			mwclientpreferences,
			'dark-mode-clientpref-enabled',
			'always store even if it matches default as we have no knowledge of what the default could be'
		);
	} );
}() );
