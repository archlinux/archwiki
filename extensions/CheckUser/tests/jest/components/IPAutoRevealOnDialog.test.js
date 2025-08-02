'use strict';

// Mock ipReveal methods to check they are called properly
const mockEnableAutoReveal = jest.fn();
jest.mock( '../../../modules/ext.checkUser.tempAccounts/ipReveal.js', () => ( {
	enableAutoReveal: mockEnableAutoReveal
} ) );

// Mock dynamic package file
jest.mock( '../../../modules/ext.checkUser.tempAccounts/durations.json', () => ( [
	{
		translation: '30 minutes',
		seconds: 1800
	},
	{
		translation: '1 hour',
		seconds: 3600
	}
] ), { virtual: true } );

const IPAutoRevealOnDialog = require( '../../../modules/ext.checkUser.tempAccounts/components/IPAutoRevealOnDialog.vue' );
const utils = require( '@vue/test-utils' );
const { CdxDialog, CdxSelect } = require( '@wikimedia/codex' );

describe( 'IP auto-reveal On dialog', () => {
	let wrapper;

	beforeEach( () => {
		wrapper = utils.mount( IPAutoRevealOnDialog );
	} );

	it( 'mounts correctly', () => {
		expect( wrapper.exists() ).toEqual( true );
	} );

	it( 'disables the primary action button initially', () => {
		expect( wrapper.findComponent( CdxDialog ).props( 'primaryAction' ).disabled ).toEqual( true );
	} );

	it( 'enables the primary action button when a selection is made', async () => {
		await wrapper.findComponent( CdxSelect ).vm.$emit( 'update:selected', '1800' );

		expect( wrapper.findComponent( CdxDialog ).props( 'primaryAction' ).disabled ).toEqual( false );
	} );

	it( 'calls enableAutoReveal and shows notification on submit', async () => {
		await wrapper.findComponent( CdxSelect ).vm.$emit( 'update:selected', '3600' );
		await wrapper.findComponent( CdxDialog ).vm.$emit( 'primary' );

		expect( mockEnableAutoReveal ).toHaveBeenCalledWith( '3600' );
		expect( mw.notify ).toHaveBeenCalled();
		expect( wrapper.findComponent( CdxDialog ).props( 'open' ) ).toBe( false );
	} );
} );
