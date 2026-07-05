'use strict';

const useInstrument = require( 'ext.checkUser.suggestedInvestigations/composables/useInstrument.js' );

// Store stubs for use in arrow functions
let newInstrumentStub, submitInteractionStub;

QUnit.module( 'ext.checkUser.suggestedInvestigations.useInstrument', QUnit.newMwEnvironment( {
	beforeEach: function () {
		// Create stub for the instrument
		submitInteractionStub = this.sandbox.stub();
		const instrumentStub = { submitInteraction: submitInteractionStub };

		if ( 'eventLog' in mw ) {
			newInstrumentStub = this.sandbox.stub( mw.eventLog, 'newInstrument' ).returns( instrumentStub );
		} else {
			newInstrumentStub = this.sandbox.stub().returns( instrumentStub );
			// Stub missing mw.eventLog
			this.sandbox.define( mw, 'eventLog', { newInstrument: newInstrumentStub } );
		}
	}
} ) );

QUnit.test( 'returned function logs events with correct data', ( assert ) => {
	const logEvent = useInstrument();

	assert.strictEqual( newInstrumentStub.callCount, 1, 'Calls mw.eventLog.newInstrument' );
	assert.strictEqual(
		newInstrumentStub.firstCall.args[ 0 ],
		'mediawiki.product_metrics.suggested_investigations_interaction.v2',
		'Uses expected stream name when calling newInstrument'
	);
	assert.strictEqual(
		newInstrumentStub.firstCall.args[ 1 ],
		'/analytics/mediawiki/suggested_investigations/interaction/1.1.4',
		'Uses expected schema when calling newInstrument'
	);

	logEvent( 'test-action', {
		context: 'Test context'
	} );

	// Verify submitInteraction is called with correct parameters
	assert.strictEqual( submitInteractionStub.callCount, 1, 'Calls submitInteraction' );
	assert.strictEqual(
		submitInteractionStub.firstCall.args[ 0 ],
		'test-action',
		'Passes correct action to submitInteraction'
	);
	assert.strictEqual(
		submitInteractionStub.firstCall.args[ 1 ].action_context,
		'Test context',
		'Includes context in interaction data'
	);
} );
