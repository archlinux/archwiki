'use strict';

/* eslint-disable no-undef */
const { config } = require( '@vue/test-utils' );
const mockMediaWiki = require( '@wikimedia/mw-node-qunit/src/mockMediaWiki.js' );

// Mock Vue plugins in test suites
global.mw = mockMediaWiki();

global.mw.message = jest.fn( ( ...messageKeyAndParams ) => ( {
	text: () => `(${ messageKeyAndParams.join( ', ' ) })`,
	// These mocks don't actually produce escaped/parsed content,
	// leading to very different results in tests and in real code…
	parse: () => `(${ messageKeyAndParams.map( ( p ) => {
		if ( p instanceof Node ) {
			return p.textContent;
		}
		return p;
	} ).join( ', ' ) })`,
	escaped: () => `(${ messageKeyAndParams.join( ', ' ) })`
} ) );

global.mw.msg = jest.fn( ( ...messageKeyAndParams ) => `(${ messageKeyAndParams.join( ', ' ) })` );

global.mw.storage = {
	session: {
		get: jest.fn(),
		set: jest.fn()
	},
	set: jest.fn()
};

global.mw.notify = jest.fn();

config.global.mocks = {
	$i18n: ( ...messageKeyAndParams ) => ( {
		text: () => '(' + messageKeyAndParams.join( ', ' ) + ')',
		parse: () => '(' + messageKeyAndParams.join( ', ' ) + ')'
	} )
};
config.global.directives = {
	'i18n-html': ( el, binding ) => {
		el.innerHTML = `${ binding.arg } (${ binding.value })`;
	}
};

// Ignore all "teleport" behavior for the purpose of testing Dialog;
// see https://test-utils.vuejs.org/guide/advanced/teleport.html
config.global.stubs = {
	teleport: true
};
