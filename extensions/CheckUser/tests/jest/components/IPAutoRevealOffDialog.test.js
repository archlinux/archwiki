'use strict';

// Mock utils methods to check they are called properly. Set up fake time now
// and fake expiry time 1 hour later.
const mockNowInSeconds = 1741604813;
const mockExpiryInSeconds = mockNowInSeconds + 3600;
const mockSetAutoRevealStatus = jest.fn().mockResolvedValue();
jest.mock( '../../../modules/ext.checkUser.tempAccounts/ipRevealUtils.js', () => ( {
	setAutoRevealStatus: mockSetAutoRevealStatus
} ) );

// Mock ipReveal methods to check they are called properly
const mockDisableAutoReveal = jest.fn();
jest.mock( '../../../modules/ext.checkUser.tempAccounts/ipReveal.js', () => ( {
	disableAutoReveal: mockDisableAutoReveal
} ) );

const IPAutoRevealOffDialog = require( '../../../modules/ext.checkUser.tempAccounts/components/IPAutoRevealOffDialog.vue' );
const { nextTick } = require( 'vue' );
const utils = require( '@vue/test-utils' );
const { CdxDialog } = require( '@wikimedia/codex' );

const renderComponent = ( props ) => {
	const defaultProps = { expiryTimestamp: String( mockExpiryInSeconds ) };
	return utils.mount( IPAutoRevealOffDialog, {
		props: Object.assign( {}, defaultProps, props )
	} );
};

describe( 'IP auto-reveal Off dialog', () => {
	beforeEach( () => {
		jest.useFakeTimers();
		jest.spyOn( Date, 'now' ).mockReturnValue( mockNowInSeconds * 1000 );
	} );

	afterEach( () => {
		jest.useRealTimers();
		jest.restoreAllMocks();
	} );

	it( 'mounts correctly', () => {
		const wrapper = renderComponent();
		expect( wrapper.exists() ).toEqual( true );
	} );

	it( 'displays the expiry time correctly', async () => {
		const wrapper = renderComponent();
		await nextTick();

		const expiryText = wrapper.find( 'p' ).html();
		expect( expiryText ).toContain( 'checkuser-ip-auto-reveal-off-dialog-text-expiry' );
		expect( expiryText ).toContain( '1:00:00' );
	} );

	it( 'updates the displayed time correctly', async () => {
		const wrapper = renderComponent();
		await nextTick();

		const initialExpiryText = wrapper.find( 'p' ).html();
		expect( initialExpiryText ).toContain( '1:00:00' );

		jest.advanceTimersByTime( 1001 );
		await nextTick();

		const updatedExpiryText = wrapper.find( 'p' ).html();
		expect( updatedExpiryText ).toContain( '0:59:59' );
	} );

	it( 'calls setAutoRevealStatus with extended time on default action', async () => {
		const wrapper = renderComponent();
		await nextTick();

		await wrapper.findComponent( CdxDialog ).vm.$emit( 'default' );
		await nextTick();

		expect( mockSetAutoRevealStatus ).toHaveBeenCalled();
		const calledWith = mockSetAutoRevealStatus.mock.calls[ 0 ][ 0 ];

		// Check that time is extended by 600 seconds
		const expectedExpiryInSeconds = mockExpiryInSeconds + 600 - mockNowInSeconds;
		expect( calledWith ).toBeCloseTo( expectedExpiryInSeconds, 0 );
		expect( wrapper.findComponent( CdxDialog ).props( 'open' ) ).toBe( false );
	} );

	it( 'calls setAutoRevealStatus with extended time on default action after expiry', async () => {
		const wrapper = renderComponent( { expiryTimestamp: '' } );
		await nextTick();

		await wrapper.findComponent( CdxDialog ).vm.$emit( 'default' );
		await nextTick();

		const expectedExpiryInSeconds = 600;

		// Check that expiry is set to 600 seconds from now
		expect( mockSetAutoRevealStatus ).toHaveBeenCalledTimes( 1 );
		expect( mockSetAutoRevealStatus ).toHaveBeenLastCalledWith( expectedExpiryInSeconds );
		expect( wrapper.findComponent( CdxDialog ).props( 'open' ) ).toBe( false );
	} );

	it( 'shows error on default action if new expiry is greater than 24 hours', async () => {
		require( '../../../modules/ext.checkUser.tempAccounts/ipRevealUtils.js' ).setAutoRevealStatus.mockRejectedValue();

		// Set expiry to a day from now
		const expiry = String( mockNowInSeconds + 86400 );
		const wrapper = renderComponent( { expiryTimestamp: expiry } );
		await nextTick();

		await wrapper.findComponent( CdxDialog ).vm.$emit( 'default' );
		await nextTick();

		// Check that error message is shown
		expect( mockSetAutoRevealStatus ).toHaveBeenCalledTimes( 1 );
		expect( wrapper.find( '.cdx-message' ).exists() ).toBe( true );
		expect( wrapper.findComponent( CdxDialog ).props( 'open' ) ).toBe( true );
	} );

	it( 'calls disableAutoReveal and shows notification on off action click', async () => {
		const wrapper = renderComponent();
		await nextTick();

		await wrapper.findComponent( CdxDialog ).vm.$emit( 'primary' );

		expect( mockDisableAutoReveal ).toHaveBeenCalled();
		expect( mw.notify ).toHaveBeenCalled();
		expect( wrapper.findComponent( CdxDialog ).props( 'open' ) ).toBe( false );
	} );
} );
