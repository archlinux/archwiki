const utils = require( 'ext.discussionTools.init' ).utils;

QUnit.module( 'mw.dt.utils', QUnit.newMwEnvironment() );

QUnit.test( '#linearWalk', ( assert ) => {
	const cases = require( '../cases/linearWalk.json' );

	cases.forEach( ( caseItem ) => {
		const
			doc = ve.createDocumentFromHtml( require( '../' + caseItem.dom ) ),
			expected = require( caseItem.expected );

		const actual = [];
		utils.linearWalk( doc, ( event, node ) => {
			actual.push( event + ' ' + node.nodeName.toLowerCase() + '(' + node.nodeType + ')' );
		} );

		const actualBackwards = [];
		utils.linearWalkBackwards( doc, ( event, node ) => {
			actualBackwards.push( event + ' ' + node.nodeName.toLowerCase() + '(' + node.nodeType + ')' );
		} );

		assert.deepEqual( actual, expected, caseItem.name );

		const expectedBackwards = expected.slice().reverse().map( ( a ) => ( a.slice( 0, 5 ) === 'enter' ? 'leave' : 'enter' ) + a.slice( 5 ) );
		assert.deepEqual( actualBackwards, expectedBackwards, caseItem.name + ' (backwards)' );

		// Uncomment this to get updated content for the JSON files, for copy/paste:
		// console.log( JSON.stringify( actual, null, 2 ) );
	} );
} );
