'use strict';

const { mount } = require( 'vue-test-utils' );
const UserInfoCardError = require( 'ext.checkUser.userInfoCard/modules/ext.checkUser.userInfoCard/components/UserInfoCardError.vue' );

QUnit.module( 'ext.checkUser.userInfoCard.UserInfoCardError', QUnit.newMwEnvironment( {
	beforeEach: function () {
		this.sandbox.stub( mw, 'msg' ).callsFake( ( key ) => key );
	}
} ) );

// Reusable mount helper
function mountComponent( props = {} ) {
	return mount( UserInfoCardError, {
		propsData: {
			message: 'Test error message',
			...props
		}
	} );
}

QUnit.test( 'renders correctly with required props', ( assert ) => {
	const wrapper = mountComponent();

	assert.true( wrapper.exists(), 'Component renders' );
	assert.true(
		wrapper.classes().includes( 'ext-checkuser-userinfocard-error' ),
		'Error component has correct class'
	);
} );

QUnit.test( 'uses CdxMessage component with correct type', ( assert ) => {
	const wrapper = mountComponent();

	const cdxMessage = wrapper.findComponent( { name: 'CdxMessage' } );
	assert.true( cdxMessage.exists(), 'CdxMessage component exists' );
	assert.strictEqual(
		cdxMessage.props( 'type' ),
		'error',
		'CdxMessage has error type'
	);
} );

QUnit.test( 'displays the correct error message', ( assert ) => {
	const customMessage = 'Custom error message';
	const wrapper = mountComponent( { message: customMessage } );

	const paragraphs = wrapper.findAll( 'p' );
	assert.strictEqual( paragraphs.length, 2, 'Component has two paragraphs' );
	assert.strictEqual(
		paragraphs[ 1 ].text(),
		customMessage,
		'Second paragraph displays the provided error message'
	);
} );

QUnit.test( 'displays the correct strong message from mw.msg', ( assert ) => {
	const wrapper = mountComponent();

	const paragraphs = wrapper.findAll( 'p' );
	const strongElement = paragraphs[ 0 ].find( 'strong' );
	assert.true( strongElement.exists(), 'Strong element exists in first paragraph' );
	assert.strictEqual(
		strongElement.text(),
		'checkuser-userinfocard-error-title',
		'Strong element displays the correct message key'
	);
} );

QUnit.test( 'setup function returns the correct strongMessage', ( assert ) => {
	const wrapper = mountComponent();

	assert.strictEqual(
		wrapper.vm.strongMessage,
		'checkuser-userinfocard-error-title',
		'strongMessage is set correctly from mw.msg'
	);
} );
