'use strict';

const rest = require( 'ext.checkUser.suggestedInvestigations/rest.js' );

let server;

QUnit.module( 'ext.checkUser.suggestedInvestigations.rest', QUnit.newMwEnvironment( {
	beforeEach: function () {
		this.server = this.sandbox.useFakeServer();
		this.server.respondImmediately = true;
		server = this.server;
	},
	afterEach: function () {
		server.restore();
	}
} ) );

QUnit.test( 'Test setCaseStatus for correct status name', ( assert ) => {
	server.respond( ( request ) => {
		assert.true( request.url.endsWith( '/checkuser/v0/suggestedinvestigations/case/1/update' ) );
		request.respond(
			200, { 'Content-Type': 'application/json' }, '{"caseId":1,"status":"resolved","reason":"Reason text"}'
		);
	} );

	return rest.setCaseStatus( 1, 'resolved', 'Reason text' ).then( ( data ) => {
		assert.deepEqual( data, { caseId: 1, status: 'resolved', reason: 'Reason text' } );
	} );
} );

QUnit.test( 'Test setCaseStatus for incorrect status name', ( assert ) => {
	server.respond( ( request ) => {
		assert.true( request.url.endsWith( '/checkuser/v0/suggestedinvestigations/case/1/update' ) );
		request.respond(
			400, { 'Content-Type': 'application/json' }, '{"errorKey": "rest-body-validation-error"}'
		);
	} );

	return assert.rejects(
		rest.setCaseStatus( 1, 'incorrect', 'Reason text' ),
		'The request should have failed'
	);
} );

QUnit.test( 'Test setCaseStatus on bad CSRF token for first attempt', ( assert ) => {
	let csrfTokenUpdated = false;
	server.respond( ( request ) => {
		// Respond to a full reveal API request.
		if ( request.url.endsWith( '/checkuser/v0/suggestedinvestigations/case/1/update' ) ) {
			// If the CSRF token has been updated, then return a valid response. Otherwise, return a
			// response indicating that the CSRF token is invalid.
			if ( csrfTokenUpdated ) {
				request.respond(
					200, { 'Content-Type': 'application/json' }, '{"caseId":1,"status":"resolved","reason":"Reason text"}'
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
	return rest.setCaseStatus( 1, 'resolved', 'Reason text' ).then( ( data ) => {
		assert.deepEqual( data, { caseId: 1, status: 'resolved', reason: 'Reason text' } );
	} );
} );
