'use strict';

const Utils = require( '../../../../../modules/ext.checkUser/checkuser/checkUserHelper/utils.js' );

QUnit.module( 'ext.checkUser.checkuser.checkUserHelper.utils' );

QUnit.test( 'Test that calculateIPNumber returns the expected value', ( assert ) => {
	const cases = require( './cases/calculateIPNumber.json' );

	cases.forEach( ( caseItem ) => {
		assert.strictEqual(
			Utils.calculateIPNumber( caseItem.IP ),
			caseItem.expectedIPNumber,
			caseItem.msg
		);
	} );
} );

QUnit.test( 'Test that compareIPs returns the expected value', ( assert ) => {
	const cases = require( './cases/compareIPs.json' );

	cases.forEach( ( caseItem ) => {
		assert.strictEqual(
			Utils.compareIPs( caseItem.IP1, caseItem.IP2 ),
			caseItem.expectedReturnValue,
			caseItem.msg
		);
	} );
} );
