'use strict';

const { shallowMount } = require( 'vue-test-utils' );
const InfoRow = require( 'ext.checkUser.userInfoCard/modules/ext.checkUser.userInfoCard/components/InfoRow.vue' );

QUnit.module( 'ext.checkUser.userInfoCard.InfoRow' );

// Sample icon for testing
const sampleIcon = {
	path: 'M10 10 H 90 V 90 H 10 Z',
	width: 24,
	height: 24
};

// Reusable mount helper
function mountComponent( content = '', props = {} ) {
	return shallowMount( InfoRow, {
		propsData: props,
		slots: {
			default: content
		}
	} );
}

QUnit.test( 'renders correctly with minimal props', ( assert ) => {
	const wrapper = mountComponent( 'Lorem ipsum' );

	assert.true( wrapper.exists(), 'Component renders' );
	assert.true(
		wrapper.find( 'p' ).classes().includes( 'ext-checkuser-userinfocard-short-paragraph' ),
		'Paragraph has correct class'
	);
	assert.true(
		wrapper.find( 'p' ).text().includes( 'Lorem ipsum' ),
		'Paragraph contains slot content'
	);
} );

QUnit.test( 'does not render icon when icon prop is not provided', ( assert ) => {
	const wrapper = mountComponent();

	const icon = wrapper.findComponent( { name: 'CdxIcon' } );
	assert.false( icon.exists(), 'Icon does not exist when icon prop is not provided' );
} );

QUnit.test( 'renders icon when icon prop is provided', ( assert ) => {
	const wrapper = mountComponent( '', { icon: sampleIcon, iconClass: 'test-icon-class' } );

	const icon = wrapper.findComponent( { name: 'CdxIcon' } );
	assert.true( icon.exists(), 'Icon exists when icon prop is provided' );
	assert.deepEqual( icon.props( 'icon' ), sampleIcon, 'Icon has correct icon prop' );
	assert.true( icon.classes().includes( 'test-icon-class' ), 'Icon has correct class' );
} );
