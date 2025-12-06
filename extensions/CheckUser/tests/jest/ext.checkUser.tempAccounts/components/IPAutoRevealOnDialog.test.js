'use strict';

// Mock ipReveal methods to check they are called properly
const mockEnableAutoReveal = jest.fn().mockResolvedValue();
jest.mock( '../../../../modules/ext.checkUser.tempAccounts/ipReveal.js', () => ( {
	enableAutoReveal: mockEnableAutoReveal
} ) );

// Mock dynamic package file
jest.mock( '../../../../modules/ext.checkUser.tempAccounts/durations.json', () => ( [
	{
		translation: '1 hour',
		seconds: 3600
	},
	{
		translation: '1 day',
		seconds: 86400
	},
	{
		translation: '90 days',
		seconds: 7776000
	}
] ), { virtual: true } );
jest.mock( '../../../../modules/ext.checkUser.tempAccounts/useInstrument.js' );

const IPAutoRevealOnDialog = require( '../../../../modules/ext.checkUser.tempAccounts/components/IPAutoRevealOnDialog.vue' );
const useInstrument = require( '../../../../modules/ext.checkUser.tempAccounts/useInstrument.js' );
const { nextTick } = require( 'vue' );
const utils = require( '@vue/test-utils' );
const { CdxDialog, CdxSelect } = require( '@wikimedia/codex' );

const mockSetText = jest.fn();
const renderComponent = () => {
	const props = {
		toolLink: { text: mockSetText }
	};
	return utils.mount( IPAutoRevealOnDialog, {
		props: props
	} );
};

describe( 'IP auto-reveal On dialog', () => {
	let logEvent;
	let wrapper;

	beforeEach( () => {
		logEvent = jest.fn();
		useInstrument.mockImplementation( () => logEvent );

		wrapper = renderComponent();
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
		expect( logEvent ).toHaveBeenCalledTimes( 0 );
	} );

	it( 'calls enableAutoReveal and shows notification on submit', async () => {
		await wrapper.findComponent( CdxSelect ).vm.$emit( 'update:selected', '3600' );
		await wrapper.findComponent( CdxDialog ).vm.$emit( 'primary' );
		await nextTick();

		expect( mockEnableAutoReveal ).toHaveBeenCalledWith( '3600' );
		expect( mockSetText ).toHaveBeenCalled();
		expect( mw.notify ).toHaveBeenCalled();
		expect( wrapper.findComponent( CdxDialog ).props( 'open' ) ).toBe( false );
		expect( logEvent ).toHaveBeenCalledTimes( 1 );
		expect( logEvent ).toHaveBeenCalledWith( 'session_start', { sessionLength: 3600 } );
	} );

	it( 'correctly handles enableAutoReveal returning an error', async () => {
		mockEnableAutoReveal.mockRejectedValueOnce();

		await wrapper.findComponent( CdxSelect ).vm.$emit( 'update:selected', '3600' );
		await wrapper.findComponent( CdxDialog ).vm.$emit( 'primary' );
		await nextTick();

		expect( mockEnableAutoReveal ).toHaveBeenCalledWith( '3600' );
		expect( mockSetText ).not.toHaveBeenCalled();
		expect( mw.notify ).not.toHaveBeenCalled();
		expect( wrapper.findComponent( CdxDialog ).props( 'open' ) ).toBe( true );
		expect( logEvent ).toHaveBeenCalledTimes( 0 );
	} );
} );
