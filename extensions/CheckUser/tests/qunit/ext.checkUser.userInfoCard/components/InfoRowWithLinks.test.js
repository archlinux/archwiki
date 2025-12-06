'use strict';

const { shallowMount, mount } = require( 'vue-test-utils' );
const InfoRowWithLinks = require( 'ext.checkUser.userInfoCard/modules/ext.checkUser.userInfoCard/components/InfoRowWithLinks.vue' );

QUnit.module( 'ext.checkUser.userInfoCard.InfoRowWithLinks', QUnit.newMwEnvironment( {
	beforeEach: function () {
		// Mock mw.message to return a mock object with parse method
		this.sandbox.stub( mw, 'message' ).callsFake( ( key, ...args ) => ( {
			parse: () => {
				// Simple mock implementation that includes the arguments
				if ( args.length === 2 ) {
					// Two arguments (main and suffix)
					return `${ key } with ${ args[ 0 ].get( 0 ).outerHTML } and ${ args[ 1 ].get( 0 ).outerHTML }`;
				} else if ( args.length === 1 ) {
					// One argument (main only)
					return `${ key } with ${ args[ 0 ].get( 0 ).outerHTML }`;
				}
				return key;
			}
		} ) );

		mw.config.set( 'CheckUserEnableUserInfoCardInstrumentation', false );
	}
} ) );

// Reusable mount helper
function mountComponent( props = {} ) {
	return shallowMount( InfoRowWithLinks, {
		propsData: {
			messageKey: 'checkuser-userinfocard-active-blocks',
			mainValue: 'Test Value',
			...props
		}
	} );
}

QUnit.test( 'formattedMessage computed property creates correct HTML for main value only', ( assert ) => {
	const wrapper = mountComponent( {
		messageKey: 'checkuser-userinfocard-global-edits',
		mainValue: 'Test Value',
		mainLink: 'https://example.com'
	} );

	const formattedMessage = wrapper.vm.formattedMessage;
	assert.true(
		formattedMessage.includes( 'checkuser-userinfocard-global-edits' ),
		'Formatted message includes message key'
	);
	assert.true(
		formattedMessage.includes( 'Test Value' ),
		'Formatted message includes main value'
	);
	assert.true(
		formattedMessage.includes( 'href="https://example.com"' ),
		'Formatted message includes main link'
	);
} );

QUnit.test( 'formattedMessage computed property creates correct HTML for main and suffix values', ( assert ) => {
	const wrapper = mountComponent( {
		messageKey: 'checkuser-userinfocard-active-blocks',
		mainValue: 'Main Value',
		mainLink: 'https://example.com',
		suffixValue: 'Suffix Value',
		suffixLink: 'https://example.org'
	} );

	const formattedMessage = wrapper.vm.formattedMessage;
	assert.true(
		formattedMessage.includes( 'checkuser-userinfocard-active-blocks' ),
		'Formatted message includes message key'
	);
	assert.true(
		formattedMessage.includes( 'Main Value' ),
		'Formatted message includes main value'
	);
	assert.true(
		formattedMessage.includes( 'Suffix Value' ),
		'Formatted message includes suffix value'
	);
	assert.true(
		formattedMessage.includes( 'href="https://example.com"' ),
		'Formatted message includes main link'
	);
	assert.true(
		formattedMessage.includes( 'href="https://example.org"' ),
		'Formatted message includes suffix link'
	);
} );

QUnit.test( 'creates span instead of link when no URL is provided', ( assert ) => {
	const wrapper = mountComponent( {
		messageKey: 'checkuser-userinfocard-global-edits',
		mainValue: 'Test Value'
		// No mainLink provided
	} );

	const formattedMessage = wrapper.vm.formattedMessage;
	assert.true(
		formattedMessage.includes( '<span>Test Value</span>' ),
		'Creates span when no link is provided'
	);
	assert.false(
		formattedMessage.includes( '<a' ),
		'Does not create link when no URL is provided'
	);
} );

QUnit.test( 'does not include suffix when suffixValue is empty, null, or undefined', ( assert ) => {
	const testCases = [ '', null, undefined ];

	testCases.forEach( ( suffixValue ) => {
		const wrapper = mountComponent( {
			messageKey: 'checkuser-userinfocard-active-blocks',
			mainValue: 'Main Value',
			suffixValue: suffixValue
		} );

		const formattedMessage = wrapper.vm.formattedMessage;
		// Should only have one argument (main) passed to mw.message
		assert.true(
			formattedMessage.includes( 'checkuser-userinfocard-active-blocks with' ),
			`Formatted message is created for suffixValue: ${ suffixValue }`
		);
	} );
} );

QUnit.test( 'handles numeric values correctly', ( assert ) => {
	const wrapper = mountComponent( {
		messageKey: 'checkuser-userinfocard-active-blocks',
		mainValue: 42,
		suffixValue: 123
	} );

	const formattedMessage = wrapper.vm.formattedMessage;
	assert.true(
		formattedMessage.includes( '42' ),
		'Numeric main value is converted to string'
	);
	assert.true(
		formattedMessage.includes( '123' ),
		'Numeric suffix value is converted to string'
	);
} );

QUnit.test( 'renders formatted message using v-html', ( assert ) => {
	const wrapper = mount( InfoRowWithLinks, {
		propsData: {
			messageKey: 'checkuser-userinfocard-global-edits',
			mainValue: 'Test Value'
		}
	} );

	const span = wrapper.find( 'span' );
	assert.true( span.exists(), 'Span element exists for v-html content' );
	// The actual HTML content is set via v-html, so we check the computed property
	assert.true(
		wrapper.vm.formattedMessage.includes( 'Test Value' ),
		'Formatted message contains expected content'
	);
} );

// TODO: T386440 - Fix the test and remove the skip
// This test fails when running in conjunction with the other test components in this folder.
// When running this test file alone, this test is passing.
QUnit.test.skip( 'logs an event when onLinkClick is called', function ( assert ) {
	mw.config.set( 'CheckUserEnableUserInfoCardInstrumentation', true );
	this.sandbox.stub( mw.user, 'sessionId' ).returns( 'test-session-id' );
	this.sandbox.stub( mw.user, 'getId' ).returns( 123 );
	const submitInteractionStub = this.sandbox.stub().resolves();
	submitInteractionStub.respondImmediately = true;
	const instrumentStub = { submitInteraction: submitInteractionStub };
	this.sandbox.stub( mw.eventLog, 'newInstrument' ).returns( instrumentStub );

	const wrapper = mountComponent( {
		mainLinkLogId: 'main_link_id'
	} );
	wrapper.vm.onLinkClick( 'main_link_id' );

	assert.strictEqual( submitInteractionStub.callCount, 1, 'submitInteraction is called once' );
	assert.strictEqual(
		submitInteractionStub.firstCall.args[ 0 ],
		'link_click',
		'First argument is "link_click"'
	);

	const interactionData = submitInteractionStub.firstCall.args[ 1 ];
	assert.strictEqual(
		interactionData.funnel_entry_token,
		'test-session-id',
		'Includes session token in interaction data'
	);
	assert.strictEqual(
		interactionData.action_subtype,
		'main_link_id',
		'Includes correct subType in interaction data'
	);
	assert.strictEqual(
		interactionData.action_source,
		'card_body',
		'Includes correct source in interaction data'
	);
} );
