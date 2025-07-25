'use strict';

const createTableText = require( '../../../../../modules/ext.checkUser/checkuser/checkUserHelper/createTableText.js' );

QUnit.module( 'ext.checkUser.checkuser.checkUserHelper.createTableText' );

QUnit.test( 'Test that createTableText returns the expected wikitext', ( assert ) => {
	const cases = require( './cases/createTableText.json' );

	cases.forEach( ( caseItem ) => {
		mw.config.set( 'wgCheckUserDisplayClientHints', false );
		assert.strictEqual(
			createTableText( caseItem.data, caseItem.showCounts ),
			caseItem.expectedWikitext,
			caseItem.msg
		);

		mw.config.set( 'wgCheckUserDisplayClientHints', true );
		assert.strictEqual(
			createTableText( caseItem.data, caseItem.showCounts ),
			caseItem.expectedWikitextWhenClientHintsEnabled,
			caseItem.msg + ' with Client Hints display enabled.'
		);
	} );
} );
