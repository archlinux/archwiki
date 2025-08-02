'use strict';

jest.mock( '../../../modules/ext.checkUser.tempAccounts/icons.json', () => ( {
	cdxIconInfo: ''
} ), { virtual: true } );

const { config, mount } = require( '@vue/test-utils' );
const mockPerformFullRevealRequest = jest.fn();
const getFormattedBlockDetails = jest.fn();

global.mw.util.isTemporaryUser = jest.fn().mockImplementation(
	( username ) => String( username ).startsWith( '~' )
);
global.mw.language.listToText = jest.fn().mockImplementation(
	( list ) => list.join( ', ' )
);
jest.mock(
	'../../../modules/ext.checkUser.tempAccounts/rest.js',
	() => ( { performFullRevealRequest: mockPerformFullRevealRequest } )
);

jest.mock(
	'../../../modules/ext.checkUser.tempAccounts/api.js',
	() => ( { getFormattedBlockDetails } )
);

const ShowIPButton = require( '../../../modules/ext.checkUser.tempAccounts/ShowIPButton.vue' );

const renderComponent = ( props ) => mount( ShowIPButton, {
	props,
	global: {
		stubs: {
			// Stub CdxPopover because it relies on the floating-ui library for positioning
			// in a way that causes infinite recursion when mounted in JSDOM.
			CdxPopover: true
		}
	}
} );

config.global.renderStubDefaultSlot = true;

describe( 'ShowIPButton', () => {
	const configMap = new Map();
	jest.spyOn( global.mw.config, 'get' )
		.mockImplementation( ( key ) => configMap.get( key ) );

	beforeEach( () => {
		configMap.clear();
		configMap.set( 'wgCheckUserIsPerformerBlocked', false );
		configMap.set( 'wgCUDMaxAge', 90 * 86400 );

		// Use a well-known window name to have the component avoid passing an anchor to CdxPopover.
		// CdxPopover needs to be shallow rendered for reasons outlined above, and vue-test-utils
		// is unable to stringify references holding an HTML element.
		global.window.name = 'ShowIPButtonTests';
	} );

	const renderTestCases = {
		'does not render when no target user is set': [ null, false ],
		'does not render for named users': [ 'TestUser', false ],
		'does not render for IP users': [ '192.168.0.1', false ],
		'renders a button when given a valid temporary user': [ '~2025', true ]
	};

	for ( const [ testName, [ targetUser, shouldRender ] ] of Object.entries( renderTestCases ) ) {
		it( testName, () => {
			const wrapper = renderComponent( { targetUser } );
			expect( wrapper.find( '.ext-checkuser-tempaccount-specialblock-ips-link' ).exists() )
				.toStrictEqual( shouldRender );
			expect( wrapper.find( '.ext-checkuser-tempaccount-specialblock-block-details-toggle' ).exists() )
				.toStrictEqual( false );
			expect( wrapper.find( '.ext-checkuser-tempaccount-specialblock-block-details' ).exists() )
				.toStrictEqual( false );
		} );
	}

	it( 'renders a button and info icon if the performer is blocked when given a valid temporary user', () => {
		configMap.set( 'wgCheckUserIsPerformerBlocked', true );

		const wrapper = renderComponent( { targetUser: '~2025' } );

		expect( wrapper.find( '.ext-checkuser-tempaccount-specialblock-ips-link' ).exists() )
			.toStrictEqual( true );
		expect( wrapper.find( '.ext-checkuser-tempaccount-specialblock-block-details-toggle' ).exists() )
			.toStrictEqual( true );
		expect( wrapper.find( '.ext-checkuser-tempaccount-specialblock-block-details' ).exists() )
			.toStrictEqual( true );
	} );

	const blockInfoTestCases = {
		'should show formatted block info': [
			{ query: { checkuserformattedblockinfo: { details: '<p>some HTML</p>' } } },
			'<p>some HTML</p>',
			null,
			null
		],
		'should show success message if block info is missing': [
			{ query: { checkuserformattedblockinfo: { details: null } } },
			null,
			'success',
			'(checkuser-tempaccount-reveal-blocked-missingblock)'
		],
		'should show error message if block info API call fails': [
			null,
			null,
			'error',
			'(checkuser-tempaccount-reveal-blocked-error)'
		]
	};

	for ( const [
		testName, [ mockResponse, expectedHtml, messageType, messageText ]
	] of Object.entries( blockInfoTestCases ) ) {
		it( testName, async () => {
			configMap.set( 'wgCheckUserIsPerformerBlocked', true );

			const wrapper = renderComponent( { targetUser: '~2025' } );

			getFormattedBlockDetails.mockImplementation( async () => {
				// Delay returning the mock response to allow testing the loading state.
				await wrapper.vm.$nextTick();

				if ( mockResponse ) {
					return mockResponse;
				} else {
					throw new Error( '' );
				}
			} );

			await wrapper.find( '.ext-checkuser-tempaccount-specialblock-block-details-toggle' ).trigger( 'click' );

			expect( wrapper.find( '.ext-checkuser-tempaccount-specialblock-block-details-indicator-wrapper' ).exists() )
				.toStrictEqual( true );

			await wrapper.vm.$nextTick();

			expect( wrapper.find( '.ext-checkuser-tempaccount-specialblock-block-details-indicator-wrapper' ).exists() )
				.toStrictEqual( false );

			if ( expectedHtml ) {
				expect( wrapper.find( '.ext-checkuser-tempaccount-specialblock-block-details-content > p' ).html() )
					.toStrictEqual( expectedHtml );
			} else {
				expect( wrapper.find( '.ext-checkuser-tempaccount-specialblock-block-details-content' ).exists() )
					.toStrictEqual( false );
			}

			if ( messageType ) {
				expect( wrapper.find( `.ext-checkuser-tempaccount-specialblock-block-details .cdx-message--${ messageType }` ).text() )
					.toStrictEqual( messageText );
			} else {
				expect( wrapper.find( '.ext-checkuser-tempaccount-specialblock-block-details .cdx-message' ).exists() )
					.toStrictEqual( false );
			}
		} );
	}

	const revealTestCases = {
		'should show checkuser-tempaccount-specialblock-ips for returned ips': [
			{ ips: [ '1.2.3.4', '5.6.7.8' ] },
			'(checkuser-tempaccount-specialblock-ips, 2, 1.2.3.4, 5.6.7.8)'
		],
		'should show checkuser-tempaccount-no-ip-results when there are no results': [
			{ ips: [] },
			'(checkuser-tempaccount-no-ip-results, 90)'
		],
		'should show checkuser-tempaccount-reveal-ip-error for a failed request': [
			null,
			'(checkuser-tempaccount-reveal-ip-error)'
		]
	};

	for ( const [
		testName, [ mockResponse, expectedText ]
	] of Object.entries( revealTestCases ) ) {
		it( testName, async () => {
			if ( mockResponse ) {
				mockPerformFullRevealRequest.mockResolvedValue( mockResponse );
			} else {
				mockPerformFullRevealRequest.mockRejectedValue( {} );
			}

			const wrapper = renderComponent( { targetUser: '~2025' } );
			await wrapper.find( '.ext-checkuser-tempaccount-specialblock-ips-link' ).trigger( 'click' );

			expect( mockPerformFullRevealRequest ).toHaveBeenCalledWith( '~2025', [], [] );
			expect( wrapper.find( '.ext-checkuser-tempaccount-specialblock-ips' ).text() )
				.toStrictEqual( expectedText );
		} );
	}

	it( 'should clear the message and show the button when the target changes', async () => {
		const wrapper = renderComponent( { targetUser: '~2025' } );
		wrapper.vm.message = 'foo';
		await wrapper.setProps( { targetUser: '~2026' } );
		expect( wrapper.vm.message ).toStrictEqual( '' );
		expect( wrapper.find( '.ext-checkuser-tempaccount-specialblock-ips-link' ).exists() )
			.toStrictEqual( true );
	} );
} );
