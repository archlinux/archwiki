'use strict';

const useInstrument = require( 'ext.checkUser.userInfoCard/modules/ext.checkUser.userInfoCard/composables/useInstrument.js' );

// Store stubs for use in arrow functions
let configStub, newInstrumentStub, submitInteractionStub, instrumentStub;

QUnit.module( 'ext.checkUser.userInfoCard.useInstrument', QUnit.newMwEnvironment( {
	beforeEach: function () {
		// Create stubs for mw functions
		configStub = this.sandbox.stub( mw.config, 'get' );
		this.sandbox.stub( mw.user, 'generateRandomSessionId' ).returns( 'test-session-id' );
		this.sandbox.stub( mw.user, 'getId' ).returns( 123 );

		// Create stub for the instrument
		submitInteractionStub = this.sandbox.stub();
		instrumentStub = { submitInteraction: submitInteractionStub };

		if ( 'eventLog' in mw ) {
			newInstrumentStub = this.sandbox.stub( mw.eventLog, 'newInstrument' ).returns( instrumentStub );
		} else {
			newInstrumentStub = this.sandbox.stub().returns( instrumentStub );
			// Stub missing mw.eventLog
			this.sandbox.define( mw, 'eventLog', { newInstrument: newInstrumentStub } );
		}

		// Store references in this context for backward compatibility
		this.configStub = configStub;
		this.newInstrumentStub = newInstrumentStub;
		this.submitInteractionStub = submitInteractionStub;
	}
} ) );

// TODO: T386440 - Fix the other skipped tests and remove this comment
// This test fails when running in conjunction with the other test components in the
// folder (currently skipped).
// When running this test file alone, this test is passing.
QUnit.test( 'returns empty function when instrumentation is disabled', ( assert ) => {
	// Set instrumentation to disabled
	configStub.withArgs( 'wgCheckUserEnableUserInfoCardInstrumentation' ).returns( false );

	const logEvent = useInstrument();

	assert.strictEqual( typeof logEvent, 'function', 'Returns a function' );

	// Call the function and verify it does nothing
	logEvent( 'test-action' );
	assert.strictEqual( newInstrumentStub.callCount, 0, 'Does not create instrument when disabled' );
	assert.strictEqual( submitInteractionStub.callCount, 0, 'Does not log events when disabled' );
} );

QUnit.test( 'returned function logs events with correct data', ( assert ) => {
	// Set instrumentation to enabled
	configStub.withArgs( 'wgCheckUserEnableUserInfoCardInstrumentation' ).returns( true );

	const logEvent = useInstrument();

	// Call the function with an action
	logEvent( 'test-action', {
		subType: 'test-subtype',
		source: 'test-source'
	} );

	// Verify submitInteraction is called with correct parameters
	assert.strictEqual( submitInteractionStub.callCount, 1, 'Calls submitInteraction' );
	assert.strictEqual(
		submitInteractionStub.firstCall.args[ 0 ],
		'test-action',
		'Passes correct action to submitInteraction'
	);

	// Verify the interaction data
	const interactionData = submitInteractionStub.firstCall.args[ 1 ];
	assert.strictEqual(
		interactionData.funnel_entry_token,
		'test-session-id',
		'Includes funnel entry token in interaction data'
	);
	assert.strictEqual(
		interactionData.action_subtype,
		'test-subtype',
		'Includes subType in interaction data'
	);
	assert.strictEqual(
		interactionData.action_source,
		'test-source',
		'Includes source in interaction data'
	);
} );
