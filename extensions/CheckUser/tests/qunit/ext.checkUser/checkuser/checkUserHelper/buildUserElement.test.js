'use strict';

const buildUserElement = require( '../../../../../modules/ext.checkUser/checkuser/checkUserHelper/buildUserElement.js' );

QUnit.module( 'ext.checkUser.checkuser.checkUserHelper.buildUserElement', QUnit.newMwEnvironment( {
	beforeEach: function () {
		mw.config.set( {
			wgArticlePath: '/wiki/$1'
		} );
	}
} ) );

QUnit.test( 'buildUserElement formats usernames as expected', ( assert ) => {
	const cases = require( './cases/buildUserElement.json' );

	cases.forEach( ( caseItem ) => {
		const element = buildUserElement(
			caseItem.userName,
			caseItem.userData
		);

		assert.strictEqual( element.outerHTML, caseItem.expected, caseItem.msg );
	} );
} );
