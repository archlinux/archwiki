'use strict';

const { mount } = require( 'vue-test-utils' );
const UserCardLoadingView = require( 'ext.checkUser.userInfoCard/modules/ext.checkUser.userInfoCard/components/UserCardLoadingView.vue' );

QUnit.module( 'ext.checkUser.userInfoCard.UserCardLoadingView', QUnit.newMwEnvironment( {
	beforeEach: function () {
		this.sandbox.stub( mw, 'msg' ).callsFake( ( key, ...args ) => {
			let returnValue = '(' + key;
			if ( args.length !== 0 ) {
				returnValue += ': ' + args.join( ', ' );
			}
			return returnValue + ')';
		} );
	}
} ) );

// Reusable mount helper
function mountComponent() {
	return mount( UserCardLoadingView );
}

QUnit.test( 'renders correctly', ( assert ) => {
	const wrapper = mountComponent();

	assert.true( wrapper.exists(), 'Component renders' );
	assert.true(
		wrapper.classes().includes( 'ext-checkuser-userinfocard-loading-indicator' ),
		'Loading indicator has correct class'
	);
} );

QUnit.test( 'uses CdxProgressIndicator component', ( assert ) => {
	const wrapper = mountComponent();

	const progressIndicator = wrapper.findComponent( { name: 'CdxProgressIndicator' } );
	assert.true( progressIndicator.exists(), 'CdxProgressIndicator component exists' );
} );

QUnit.test( 'displays the correct loading label', ( assert ) => {
	const wrapper = mountComponent();

	const progressIndicator = wrapper.findComponent( { name: 'CdxProgressIndicator' } );
	assert.strictEqual(
		progressIndicator.text(),
		'(checkuser-userinfocard-loading-label)',
		'Progress indicator displays the correct loading label'
	);
} );

QUnit.test( 'setup function returns the correct loadingLabel', ( assert ) => {
	const wrapper = mountComponent();

	assert.strictEqual(
		wrapper.vm.loadingLabel,
		'(checkuser-userinfocard-loading-label)',
		'loadingLabel is set correctly from mw.msg'
	);
} );
