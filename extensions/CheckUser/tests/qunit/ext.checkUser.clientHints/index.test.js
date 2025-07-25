'use strict';

QUnit.module( 'ext.checkUser.clientHints', QUnit.newMwEnvironment( {} ) );

QUnit.test( 'Client hints code is setup if navigator.userAgentData.getHighEntropyValues() is available', function ( assert ) {
	const clientHints = require( '../../../modules/ext.checkUser.clientHints/index.js' );
	const responseMock = {
		platform: 'macOS'
	};
	const navigatorData = {
		userAgentData: {
			getHighEntropyValues: this.sandbox.stub().returns(
				$.Deferred().resolve( responseMock )
			)
		}
	};
	assert.true( clientHints.init( navigatorData ) );
} );

QUnit.test( 'Client hints code is not setup if navigator.userAgentData is available but navigator.userAgentData.getHighEntropyValues() is not available', ( assert ) => {
	const clientHints = require( '../../../modules/ext.checkUser.clientHints/index.js' );
	const navigatorData = { userAgentData: {} };
	assert.false( clientHints.init( navigatorData ) );
} );
QUnit.test( 'Client hints code is not setup if navigator.userAgentData is not defined', ( assert ) => {
	const clientHints = require( '../../../modules/ext.checkUser.clientHints/index.js' );
	const navigatorData = {};
	assert.false( clientHints.init( navigatorData ) );
} );
