'use strict';

const { mount } = require( '@vue/test-utils' );

global.mw.util.isIPAddress = jest.fn().mockImplementation(
	( ip, allowCidr ) => {
		const ipStr = String( ip );
		return ipStr.includes( '.' ) && ( allowCidr || !ipStr.includes( '/' ) );
	}
);

const TempUsersMessage = require( '../../../../modules/ext.checkUser.tempAccounts/components/TempUsersMessage.vue' );

const renderComponent = ( props ) => mount( TempUsersMessage, {
	props
} );

describe( 'TempUsersMessage', () => {
	jest.spyOn( global.mw, 'message' )
		.mockImplementation( ( key, ...params ) => ( {
			parse: () => `(${ key }, ${ params.join( ', ' ) })`
		} ) );

	const renderTestCases = {
		'does not render when no target user is set': [ null, false ],
		'does not render for named users': [ 'TestUser', false ],
		'renders for IP users': [ '127.0.0.1', true ],
		'renders for IP ranges': [ '127.0.0.0/24', true ]
	};

	for ( const [ testName, [ targetUser, shouldRender ] ] of Object.entries( renderTestCases ) ) {
		it( testName, () => {
			const wrapper = renderComponent( { targetUser } );
			expect( wrapper.find( '.ext-checkuser-tempaccount-specialblock-ips' ).exists() )
				.toStrictEqual( shouldRender );
		} );
	}

	it( 'displays the correct message for IP addresses', () => {
		const wrapper = renderComponent( { targetUser: '127.0.0.1' } );
		expect( wrapper.find( '.ext-checkuser-tempaccount-specialblock-ips p' ).html() )
			.toStrictEqual( '<p>(checkuser-tempaccount-specialblock-ip-target, 127.0.0.1)</p>' );
	} );

	it( 'displays the correct message for IP ranges', () => {
		const wrapper = renderComponent( { targetUser: '127.0.0.0/24' } );
		expect( wrapper.find( '.ext-checkuser-tempaccount-specialblock-ips p' ).html() )
			.toStrictEqual( '<p>(checkuser-tempaccount-specialblock-iprange-target, 127.0.0.0/24)</p>' );
	} );

	it( 'should clear the message when the target changes to non-IP', async () => {
		const wrapper = renderComponent( { targetUser: '127.0.0.1' } );
		expect( wrapper.find( '.ext-checkuser-tempaccount-specialblock-ips' ).exists() )
			.toStrictEqual( true );
		await wrapper.setProps( { targetUser: 'TestUser' } );
		expect( wrapper.find( '.ext-checkuser-tempaccount-specialblock-ips' ).exists() )
			.toStrictEqual( false );
	} );
} );
