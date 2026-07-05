'use strict';

const { mount } = require( 'vue-test-utils' );
const UserCardButton = require( 'ext.checkUser.userInfoCard/components/UserCardButton.vue' );
const { cdxIconUserAvatar, cdxIconUserBlocked, cdxIconUserTemporary } = require( './icons.json' );

QUnit.module( 'ext.checkUser.userInfoCard.UserCardButton', QUnit.newMwEnvironment() );

function mountComponent( username = 'TestUser' ) {
	return mount( UserCardButton, { props: { username } } );
}

QUnit.test( 'button label contains username', async ( assert ) => {
	const wrapper = mountComponent();
	await wrapper.setData( { ready: true } );

	assert.true( wrapper.getComponent( { name: 'CdxButton' } )
		.attributes( 'aria-label' )
		.includes( 'TestUser' ) );
} );

QUnit.test( 'button click toggles popover', async ( assert ) => {
	const wrapper = mountComponent();
	await wrapper.setData( { ready: true } );

	let value = false;
	wrapper.vm.togglePopover = () => {
		value = !value;
		assert.step( value ? 'opened' : 'closed' );
	};

	const button = wrapper.getComponent( { name: 'CdxButton' } );
	button.trigger( 'click' );
	button.trigger( 'click' );

	assert.verifySteps( [ 'opened', 'closed' ] );
} );

QUnit.test( 'icon matches user state', async ( assert ) => {
	const wrapper = mountComponent();
	await wrapper.setData( { ready: true } );

	const icon = wrapper.getComponent( { name: 'CdxIcon' } );
	assert.strictEqual( icon.props( 'icon' ), cdxIconUserAvatar, 'normal user' );

	await wrapper.setProps( { username: '~2026-1' } );
	assert.propEqual( icon.props( 'icon' ), cdxIconUserTemporary, 'temporary user' );

	await wrapper.setData( { blocked: true } );
	assert.propEqual( icon.props( 'icon' ), cdxIconUserBlocked, 'blocked user' );
} );
