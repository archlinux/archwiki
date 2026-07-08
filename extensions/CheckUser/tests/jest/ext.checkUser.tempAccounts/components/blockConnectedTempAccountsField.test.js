'use strict';

const blockConnectedTempAccountsField = require( '../../../../modules/ext.checkUser.tempAccounts/components/blockConnectedTempAccountsField.vue' );
const utils = require( '@vue/test-utils' );

describe( 'blockConnectedTempAccountsField', () => {
	const hooks = {};
	function setupAndMount( propsState = {}, configState = {}, restResult = {} ) {
		global.mw = {
			util: {
				isTemporaryUser: jest.fn().mockReturnValue( true )
			},
			user: {
				tokens: { get: jest.fn().mockReturnValue( 'csrf-token' ) }
			},

			message: jest.fn().mockReturnValue( { parse: () => '' } ),
			msg: jest.fn().mockReturnValue( '' ),
			language: { listToText: jest.fn() },
			hook: ( name ) => ( {
				add: ( callback ) => {
					hooks[ name ] = callback;
				}
			} ),
			config: { get: () => {} },
			track: () => {},
			Rest: class {
				async post() {}
			}
		};

		const config = {
			blockEnableMultiblocks: true,
			wgTemporaryAccountIPRevealAllowed: true,
			wgCUDMaxAge: 2592000,
			...configState
		};
		jest.spyOn( mw.config, 'get' ).mockImplementation( ( key ) => {
			if ( key in config ) {
				return config[ key ];
			} else {
				return null;
			}
		} );
		jest.spyOn( mw.Rest.prototype, 'post' ).mockResolvedValue( restResult );
		jest.spyOn( mw, 'track' ).mockImplementation( () => {} );
		return utils.mount( blockConnectedTempAccountsField, {
			props: {
				targetUser: '',
				blockId: null,
				...propsState
			}
		} );
	}

	afterEach( () => {
		jest.resetAllMocks();
	} );

	it( 'Tracks nothing when the field isn\'t visible', async () => {
		setupAndMount( {}, { wgTemporaryAccountIPRevealAllowed: false } );

		// In an erroring test, this waits for rest calls to finish
		await new Promise( ( resolve ) => {
			setTimeout( () => {
				resolve();
			}, 0 );
		} );

		expect( mw.track ).not.toHaveBeenCalled();
	} );
	it( 'Tracks the first view of a temp account with an eligible performer', async () => {
		setupAndMount();
		expect( mw.track ).toHaveBeenCalledWith(
			expect.any( String ),
			1,
			expect.objectContaining( { action: 'viewed-tempaccount' } )
		);
	} );
	it( 'Tracks if the temp account has connected accounts', async () => {
		const restResult = {
			connectedAccounts: [],
			ipsUsedCount: 1
		};

		setupAndMount( { targetUser: '~2026-1' }, {}, restResult );

		await new Promise( ( resolve ) => {
			setTimeout( () => {
				resolve();
			}, 0 );
		} );

		// Viewed temp account, nothing else to track
		expect( mw.track ).toHaveBeenCalledTimes( 1 );
		expect( mw.track.mock.calls ).toEqual( [
			[
				'stats.mediawiki_checkuser_connected_tempaccounts_bulkblock_total',
				1,
				{ action: 'viewed-tempaccount' }
			]
		] );
	} );
	it( 'Skips tracking if no connected accounts are found', async () => {
		const restResult = {
			connectedAccounts: [ '~2026-2', '~2026-3' ],
			ipsUsedCount: 1
		};

		setupAndMount( { targetUser: '~2026-1' }, {}, restResult );

		await new Promise( ( resolve ) => {
			setTimeout( () => {
				resolve();
			}, 0 );
		} );

		// Viewed temp account, has connected accounts, number of connected accounts, aggregate count
		expect( mw.track ).toHaveBeenCalledTimes( 4 );
		expect( mw.track.mock.calls ).toEqual( [
			[
				'stats.mediawiki_checkuser_connected_tempaccounts_bulkblock_total',
				1,
				{ action: 'viewed-tempaccount' }
			],
			[
				'stats.mediawiki_checkuser_connected_tempaccounts_bulkblock_total',
				1,
				{ action: 'found-connected-tempaccounts' }
			],
			[ 'stats.mediawiki_checkuser_connected_tempaccounts_bulkblock_total',
				1,
				{ action: 'found-connected-tempaccounts-count', count: 2 }
			],
			[
				'stats.mediawiki_checkuser_connected_tempaccounts_bulkblock_total',
				2,
				{ action: 'found-connected-tempaccounts-sum' }
			]
		] );
	} );

	it.each( [
		{
			shouldBlock: true,
			expectedAction: 'is-bulk-blocking'
		},
		{
			shouldBlock: false,
			expectedAction: 'not-bulk-blocking'
		}
	] )( 'Tracks when a block is submitted', async ( { shouldBlock, expectedAction } ) => {
		const wrapper = setupAndMount(
			{},
			{},
			{
				connectedAccounts: [ '~2026-2', '~2026-3' ],
				ipsUsedCount: 1
			}
		);

		wrapper.vm.shouldBlockConnectedTempAccounts = shouldBlock;
		await wrapper.vm.$nextTick();
		hooks[ 'mw.special.block.doBlockParamsReady' ]( {
			additionalBlocksStatuses: {}
		} );

		expect( mw.track ).toHaveBeenCalledTimes( 5 );
		expect( mw.track.mock.calls ).toEqual( [
			[
				'stats.mediawiki_checkuser_connected_tempaccounts_bulkblock_total',
				1,
				{ action: 'viewed-tempaccount' }
			],
			[
				'stats.mediawiki_checkuser_connected_tempaccounts_bulkblock_total',
				1,
				{ action: 'found-connected-tempaccounts' }
			],
			[
				'stats.mediawiki_checkuser_connected_tempaccounts_bulkblock_total',
				1,
				{ action: 'found-connected-tempaccounts-count', count: 2 }
			],
			[
				'stats.mediawiki_checkuser_connected_tempaccounts_bulkblock_total',
				2,
				{ action: 'found-connected-tempaccounts-sum' }
			],
			[
				'stats.mediawiki_checkuser_connected_tempaccounts_bulkblock_total',
				1,
				{ action: expectedAction }
			]
		] );
	} );
	it( 'Tracks block stats on successful additional block', async () => {
		const restResult = {
			connectedAccounts: [ '~2026-2', '~2026-3' ],
			ipsUsedCount: 1
		};

		setupAndMount( { targetUser: '~2026-1' }, {}, restResult );

		await new Promise( ( resolve ) => {
			setTimeout( () => {
				resolve();
			}, 0 );
		} );

		hooks[ 'SpecialBlock.block' ]( {
			additionalBlocksStatuses: {
				'~2026-2': [],
				'~2026-3': [ 'already blocked' ]
			}
		} );

		expect( mw.track.mock.calls ).toEqual( [
			[
				'stats.mediawiki_checkuser_connected_tempaccounts_bulkblock_total',
				1,
				{ action: 'viewed-tempaccount' }
			],
			[
				'stats.mediawiki_checkuser_connected_tempaccounts_bulkblock_total',
				1,
				{ action: 'found-connected-tempaccounts' }
			],
			[
				'stats.mediawiki_checkuser_connected_tempaccounts_bulkblock_total',
				1,
				{ action: 'found-connected-tempaccounts-count', count: 2 }
			],
			[
				'stats.mediawiki_checkuser_connected_tempaccounts_bulkblock_total',
				2,
				{ action: 'found-connected-tempaccounts-sum' }
			],
			[
				'stats.mediawiki_checkuser_connected_tempaccounts_bulkblock_total',
				1,
				{
					totalblocksattempted: 2,
					totalblockssucceeded: 1,
					totalalreadyblocked: 1
				}
			],
			[
				'stats.mediawiki_checkuser_connected_tempaccounts_bulkblock_total',
				1,
				{ action: 'successfully-blocked-connected-tempaccount-sum' }
			],
			[
				'stats.mediawiki_checkuser_connected_tempaccounts_bulkblock_total',
				1,
				{ action: 'already-blocked-connected-tempaccount-sum' }
			]
		] );
	} );

	it( 'Skips tracking block stats if no addtiional blocks were made', async () => {
		const restResult = {
			connectedAccounts: [ '~2026-2', '~2026-3' ],
			ipsUsedCount: 1
		};

		setupAndMount( { targetUser: '~2026-1' }, {}, restResult );

		await new Promise( ( resolve ) => {
			setTimeout( () => {
				resolve();
			}, 0 );
		} );

		hooks[ 'SpecialBlock.block' ]( {
			additionalBlocksStatuses: {}
		} );

		expect( mw.track.mock.calls ).toEqual( [
			[
				'stats.mediawiki_checkuser_connected_tempaccounts_bulkblock_total',
				1,
				{ action: 'viewed-tempaccount' }
			],
			[
				'stats.mediawiki_checkuser_connected_tempaccounts_bulkblock_total',
				1,
				{ action: 'found-connected-tempaccounts' }
			],
			[
				'stats.mediawiki_checkuser_connected_tempaccounts_bulkblock_total',
				1,
				{ action: 'found-connected-tempaccounts-count', count: 2 }
			],
			[
				'stats.mediawiki_checkuser_connected_tempaccounts_bulkblock_total',
				2,
				{ action: 'found-connected-tempaccounts-sum' }
			],
			[
				'stats.mediawiki_checkuser_connected_tempaccounts_bulkblock_total',
				1,
				{
					totalblocksattempted: 0,
					totalblockssucceeded: 0,
					totalalreadyblocked: 0
				}
			]
		] );
	} );
} );
