'use strict';

const specialSuggestedInvestigations = require( 'ext.checkUser.suggestedInvestigations/SpecialSuggestedInvestigations.js' );

QUnit.module( 'ext.checkUser.suggestedInvestigations.SpecialSuggestedInvestigations', QUnit.newMwEnvironment() );

const commonDismissableWarningTest = ( assert, messageClass ) => {
	// Mock setTimeout so we can control when the callback is executed
	let setTimeoutCallback = null;
	const mockWindow = {
		setTimeout: function ( callback ) {
			setTimeoutCallback = callback;
		}
	};

	// Set up the DOM to include the warning message with the dismiss button
	const $warningDismissButton = $( '<button>' )
		.addClass( 'ext-checkuser-suggestedinvestigations-warning-dismiss' );

	// eslint-disable-next-line mediawiki/class-doc
	const $warningMessage = $( '<div>' )
		.addClass( 'cdx-message--user-dismissable' )
		.addClass( messageClass )
		.append( $warningDismissButton );

	// eslint-disable-next-line no-jquery/no-global-selector
	const $qunitFixture = $( '#qunit-fixture' );
	$qunitFixture.append( $warningMessage );

	// Execute the code under test to set up the click handler
	specialSuggestedInvestigations( mockWindow );

	// Click the dismiss button
	$warningDismissButton.trigger( 'click' );

	// Verify the warning message is fading out.
	/* eslint-disable no-jquery/no-class-state */
	assert.true(
		$warningMessage.hasClass(
			'ext-checkuser-suggestedinvestigations-dismissable-warning--fading'
		),
		'Warning has fading animation'
	);

	// Run the timeout callback and then assert the element gets the hidden class
	setTimeoutCallback();

	assert.true(
		$warningMessage.hasClass(
			'ext-checkuser-suggestedinvestigations-dismissable-warning--hidden'
		),
		'Warning is hidden after fading complete'
	);
	/* eslint-enable no-jquery/no-class-state */
};

QUnit.test( 'Dismissable private data warning sets user option on dismiss', function ( assert ) {
	const saveOptionStub = this.sandbox.stub( mw.Api.prototype, 'saveOption' );

	commonDismissableWarningTest( assert, 'ext-checkuser-suggestedinvestigations-private-data-warning' );

	assert.deepEqual(
		saveOptionStub.callCount,
		1,
		'mw.Api().saveOption is called once'
	);
	assert.deepEqual(
		saveOptionStub.firstCall.args[ 0 ],
		'checkuser-suggested-investigations-private-data-warning-seen',
		'The option name is as expected'
	);
	assert.deepEqual(
		saveOptionStub.firstCall.args[ 1 ],
		1,
		'The option value is as expected'
	);
	assert.deepEqual(
		saveOptionStub.firstCall.args[ 2 ],
		{ global: 'update' },
		'The options should be updated globally'
	);
} );

QUnit.test( 'General warning does not attempt to update the user options', function ( assert ) {
	const saveOptionStub = this.sandbox.stub( mw.Api.prototype, 'saveOption' );

	commonDismissableWarningTest( assert, 'ext-checkuser-suggestedinvestigations-warning' );

	assert.deepEqual(
		saveOptionStub.callCount,
		0,
		'mw.Api().saveOption is never called'
	);
} );
