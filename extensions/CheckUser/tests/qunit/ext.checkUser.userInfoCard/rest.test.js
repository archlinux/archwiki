'use strict';

const rest = require( 'ext.checkUser.userInfoCard/rest.js' );

let server;

QUnit.module( 'ext.checkUser.userInfoCard.rest', QUnit.newMwEnvironment( {
	beforeEach: function () {
		this.server = this.sandbox.useFakeServer();
		this.server.respondImmediately = true;
		server = this.server;

		this.sandbox.stub( mw.config, 'get' ).callsFake( ( key ) => {
			switch ( key ) {
				case 'wgUserLanguage':
					return 'en';
			}
		} );
	},
	afterEach: function () {
		server.restore();
	}
} ) );

// Other functionality is tested through UserCardView.test.js,
// so no need to repeat those tests here
QUnit.test( 'Test getUserInfo on bad CSRF token for first attempt', ( assert ) => {
	let csrfTokenUpdated = false;
	server.respond( ( request ) => {
		if ( request.url.endsWith( '/checkuser/v0/userinfo?uselang=en' ) ) {
			// If the CSRF token has been updated, then return a valid response. Otherwise, return a
			// response indicating that the CSRF token is invalid.
			if ( csrfTokenUpdated ) {
				request.respond(
					200,
					{ 'Content-Type': 'application/json' },
					JSON.stringify( { name: 'TestUser1' } )
				);
			} else {
				request.respond(
					400,
					{ 'Content-Type': 'application/json' },
					JSON.stringify( { errorKey: 'rest-badtoken' } )
				);
			}
		} else if (
			request.url.includes( 'type=csrf' ) &&
			request.url.includes( 'meta=tokens' ) &&
			!csrfTokenUpdated
		) {
			request.respond( 200, { 'Content-Type': 'application/json' }, JSON.stringify( {
				query: { tokens: { csrftoken: 'newtoken' } }
			} ) );
			csrfTokenUpdated = true;
		} else {
			// All API requests except the above are not expected to be called during the test.
			// To prevent the test from silently failing, we will fail the test if an
			// unexpected API request is made.
			assert.true( false, 'Unexpected API request to' + request.url );
		}
	} );

	// Call the method under test
	return rest.getUserInfo( 'TestUser1' ).then( ( data ) => {
		assert.deepEqual(
			data,
			{ name: 'TestUser1' },
			'getUserInfo should still return good data after second API call'
		);
		assert.strictEqual(
			csrfTokenUpdated,
			true,
			'CSRF token should have been refreshed'
		);
	} );
} );
