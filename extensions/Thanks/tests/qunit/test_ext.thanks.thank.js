QUnit.module( 'Thanks thank', QUnit.newMwEnvironment( {
	beforeEach: function () {
		this.server = this.sandbox.useFakeServer();
		this.server.respondImmediately = true;
	}
} ) );

QUnit.test( 'thanked cookie', ( assert ) => {
	const thankId = '8';
	const thankIdNonExisting = '13';

	mw.cookie.set( mw.thanks.thanked.cookieName, escape( '17,11' ) );
	assert.deepEqual( mw.thanks.thanked.load(), [ '17', '11' ], 'cookie with two values' );

	// Add a 0 the 100th element

	mw.cookie.set( mw.thanks.thanked.cookieName, escape( '9,'.repeat( mw.thanks.thanked.maxHistory - 1 ) + '0' ) );
	assert.strictEqual( mw.thanks.thanked.load()[ mw.thanks.thanked.maxHistory - 1 ], '0', 'load ids from a cookie' );

	mw.thanks.thanked.push( thankId );
	assert.strictEqual( mw.thanks.thanked.load().length, mw.thanks.thanked.maxHistory, 'cut to maxHistory' );
	assert.strictEqual( mw.thanks.thanked.load()[ mw.thanks.thanked.maxHistory - 1 ], thankId, 'add to the end' );

	assert.strictEqual( mw.thanks.thanked.contains( thankId ), true, 'cookie contains id' );
	assert.strictEqual( mw.thanks.thanked.contains( thankIdNonExisting ), false, 'cookie does not contain id' );
} );

QUnit.test( 'gets user gender', function ( assert ) {
	this.server.respond( /user1/, ( request ) => {
		request.respond( 200, { 'Content-Type': 'application/json' },
			'{"batchcomplete":"","query":{"users":[{"userid":1,"name":"user1","gender":"male"}]}}'
		);
	} );
	this.server.respond( /user2/, ( request ) => {
		request.respond( 200, { 'Content-Type': 'application/json' },
			'{"batchcomplete":"","query":{"users":[{"userid":2,"name":"user2","gender":"unknown"}]}}'
		);
	} );
	this.server.respond( /user3/, ( request ) => {
		request.respond( 200, { 'Content-Type': 'application/json' },
			'{"batchcomplete":"","query":{"users":[{"name":"user3","missing":""}]}}'
		);
	} );

	const maleUser = mw.thanks.getUserGender( 'user1' );
	const unknownGenderUser = mw.thanks.getUserGender( 'user2' );
	const nonExistingUser = mw.thanks.getUserGender( 'user3' );
	const done = assert.async( 3 );

	maleUser.then( ( recipientGender ) => {
		assert.strictEqual( recipientGender, 'male', 'gender for male user' );
		done();
	} );
	unknownGenderUser.then( ( recipientGender ) => {
		assert.strictEqual( recipientGender, 'unknown', 'gender for unknown-gender user' );
		done();
	} );
	nonExistingUser.then( ( recipientGender ) => {
		assert.strictEqual( recipientGender, 'unknown', 'gender for non-existing user' );
		done();
	} );
} );
