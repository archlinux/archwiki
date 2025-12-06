// Adapted from CommunityRequests (https://w.wiki/Ewtf)

const vueConfig = require( '@vue/test-utils' ).config;

global.mw = require( '@wikimedia/mw-node-qunit/src/mockMediaWiki.js' )();

jest.mock( 'mediawiki.api', () => {
	mw.Api = class {
		get( params ) {
			if (
				params.action === 'query' &&
				params.meta === 'siteinfo' &&
				params.siprop === 'namespaces'
			) {
				return Promise.resolve( require( '../assets/namespaces.json' ) );
			}
			if (
				params.action === 'query' &&
				params.list === 'allusers'
			) {
				if ( params.auprefix === 'Timeout' ) {
					return Promise.reject( {
						error: {
							code: 'maxlag',
							info: 'Waiting for test.wiki: 0 seconds lagged',
							host: 'test.wiki',
							lag: 0,
							'*': 'See https://www.mediawiki.org/w/api.php for API usage'
						}
					} );
				}

				let users = require( '../assets/allusers.json' ).query.allusers;
				if ( params.auprefix ) {
					users = users.filter( ( v ) => v.name.startsWith( params.auprefix ) );
				}
				if ( params.aulimit ) {
					users = users.slice( 0, params.aulimit + 1 );
				}
				return Promise.resolve( {
					query: {
						allusers: users
					}
				} );
			}
		}
	};
}, { virtual: true } );
jest.mock( 'mediawiki.util', () => {
	mw.util = mw.util || {};
	/**
	 * Don't actually do any debouncing. This can make tests flaky.
	 *
	 * @param {Function} func
	 * @return {Function} func
	 */
	mw.util.debounce = ( func ) => func;
}, { virtual: true } );

/**
 * Mock for the calls to Core's $i18n plugin which returns a mw.Message object.
 *
 * @param {string} key The key of the message to parse.
 * @param {...*} args Arbitrary number of arguments to be parsed.
 * @return {Object} mw.Message-like object with .text() and .parse() methods.
 */
function $i18nMock( key, ...args ) {
	function serializeArgs() {
		return args.length ? `${ key }:[${ args.join( ',' ) }]` : key;
	}
	return {
		text: () => serializeArgs(),
		parse: () => serializeArgs()
	};
}
// Mock Vue plugins in test suites.
vueConfig.global.provide = {
	i18n: $i18nMock
};
vueConfig.global.mocks = {
	$i18n: $i18nMock
};
vueConfig.global.directives = {
	'i18n-html': ( el, binding ) => {
		el.innerHTML = `${ binding.arg } (${ binding.value })`;
	}
};
