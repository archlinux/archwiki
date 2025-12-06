'use strict';

const createTableText = require( 'ext.checkUser/checkuser/checkUserHelper/createTableText.js' );

QUnit.module( 'ext.checkUser.checkuser.checkUserHelper.createTableText' );

QUnit.test( 'Test that createTableText returns the expected wikitext', ( assert ) => {
	const cases = require( './cases/createTableText.json' );

	cases.forEach( ( caseItem ) => {
		assert.strictEqual(
			createTableText( caseItem.data, caseItem.showCounts ),
			caseItem.expectedWikitext,
			caseItem.msg + ' with Client Hints display enabled.'
		);
	} );
} );
