'use strict';

const { shallowMount } = require( 'vue-test-utils' );
const UserCardHeader = require( 'ext.checkUser.userInfoCard/modules/ext.checkUser.userInfoCard/components/UserCardHeader.vue' );

QUnit.module( 'ext.checkUser.userInfoCard.UserCardHeader', QUnit.newMwEnvironment( {
	beforeEach: function () {
		this.server = this.sandbox.useFakeServer();
		this.server.respondImmediately = true;

		this.sandbox.stub( mw, 'msg' ).callsFake( ( key, ...args ) => {
			let returnValue = '(' + key;
			if ( args.length !== 0 ) {
				returnValue += ': ' + args.join( ', ' );
			}
			return returnValue + ')';
		} );
	},
	afterEach: function () {
		this.server.restore();
	}
} ) );

// Reusable mount helper
function mountComponent( props = {} ) {
	return shallowMount( UserCardHeader, {
		propsData: {
			userId: '123',
			username: 'TestUser',
			userPageUrl: '/wiki/User:TestUser',
			userPageIsKnown: true,
			userPageWatched: false,
			...props
		}
	} );
}

QUnit.test( 'renders correctly with all required props', ( assert ) => {
	const wrapper = mountComponent();

	assert.true( wrapper.exists(), 'Component renders' );
	assert.true(
		wrapper.classes().includes( 'ext-checkuser-userinfocard-header' ),
		'Header has correct class'
	);
	assert.strictEqual(
		wrapper.find( '.ext-checkuser-userinfocard-header-username a' ).text(),
		'TestUser',
		'Username is displayed correctly'
	);
} );

QUnit.test( 'applies the correct class to username link when userPageIsKnown is true', ( assert ) => {
	// Test with userPageIsKnown = true
	const wrapperExists = mountComponent();

	const userLinkExists = wrapperExists.find( '.ext-checkuser-userinfocard-header-username a' );
	assert.true(
		userLinkExists.classes().includes( 'mw-userlink' ),
		'Link has mw-userlink class when page exists'
	);
	assert.false(
		userLinkExists.classes().includes( 'new' ),
		'Link does not have new class when page exists'
	);
} );

QUnit.test( 'applies the correct class to username link when userPageIsKnown is false', ( assert ) => {
	// Test with userPageIsKnown = false
	const wrapperNotExists = mountComponent( { userPageIsKnown: false } );

	const userLinkNotExists = wrapperNotExists.find(
		'.ext-checkuser-userinfocard-header-username a'
	);
	assert.true(
		userLinkNotExists.classes().includes( 'new' ),
		'Link has new class when page does not exist'
	);
	assert.false(
		userLinkNotExists.classes().includes( 'mw-userlink' ),
		'Link does not have mw-userlink class when page does not exist'
	);
} );

QUnit.test( 'sets the correct href on the username link', ( assert ) => {
	const wrapper = mountComponent();

	const userLink = wrapper.find( '.ext-checkuser-userinfocard-header-username a' );
	assert.strictEqual(
		userLink.attributes( 'href' ),
		'/wiki/User:TestUser',
		'Username link has correct href'
	);
} );

QUnit.test( 'passes the correct props to UserCardMenu', ( assert ) => {
	const wrapper = mountComponent();

	const userCardMenu = wrapper.findComponent( { name: 'UserCardMenu' } );
	assert.strictEqual(
		userCardMenu.props( 'username' ),
		'TestUser',
		'UserCardMenu receives correct username'
	);
	assert.strictEqual(
		userCardMenu.props( 'userPageWatched' ),
		false,
		'UserCardMenu receives correct userPageWatched'
	);
} );

QUnit.test( 'emits close event when close button is clicked', ( assert ) => {
	const done = assert.async();
	const wrapper = mountComponent();

	assert.strictEqual( wrapper.emitted().close, undefined, 'No close events emitted initially' );

	const closeButton = wrapper.findComponent( { name: 'CdxButton' } );

	closeButton.trigger( 'click' ).then( () => {
		assert.true( wrapper.emitted().close !== undefined, 'Close event is emitted' );
		done();
	} );
} );

QUnit.test( 'sets the correct aria-label on the close button', ( assert ) => {
	const wrapper = mountComponent();

	const closeButton = wrapper.findComponent( { name: 'CdxButton' } );
	assert.strictEqual(
		closeButton.attributes( 'aria-label' ),
		'(checkuser-userinfocard-close-button-aria-label)',
		'Close button has correct aria-label'
	);
} );

// TODO: T386440 - Fix the test and remove the skip
// This test fails when running in conjunction with the other test components in this folder.
// When running this test file alone, this test is passing.
QUnit.test.skip( 'logs an event when onUsernameClick is called', function ( assert ) {
	mw.config.set( 'CheckUserEnableUserInfoCardInstrumentation', true );
	this.sandbox.stub( mw.user, 'sessionId' ).returns( 'test-session-id' );
	this.sandbox.stub( mw.user, 'getId' ).returns( 123 );
	const submitInteractionStub = this.sandbox.stub();
	submitInteractionStub.respondImmediately = true;
	const instrumentStub = { submitInteraction: submitInteractionStub };
	this.sandbox.stub( mw.eventLog, 'newInstrument' ).returns( instrumentStub );

	const wrapper = mountComponent();
	wrapper.vm.onUsernameClick();

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
		'user_page',
		'Includes correct subType in interaction data'
	);
	assert.strictEqual(
		interactionData.action_source,
		'card_header',
		'Includes correct source in interaction data'
	);
} );
