const
	testUtils = require( './testUtils.js' ),
	Parser = require( 'ext.discussionTools.init' ).Parser,
	modifier = require( 'ext.discussionTools.init' ).modifier;

QUnit.module( 'mw.dt.modifier', QUnit.newMwEnvironment() );

require( '../cases/modified.json' ).forEach( ( caseItem ) => {
	const testName = '#addListItem/#removeAddedListItem (' + caseItem.name + ')';
	// This should be one test with many cases, rather than multiple tests, but the cases are large
	// enough that processing all of them at once causes timeouts in Karma test runner.
	const skipTests = require( '../skip.json' )[ 'cases/modified.json' ];
	if ( skipTests.indexOf( caseItem.name ) !== -1 ) {
		QUnit.skip( testName );
		return;
	}
	QUnit.test( testName, ( assert ) => {
		const dom = ve.createDocumentFromHtml( require( '../' + caseItem.dom ) ),
			expected = ve.createDocumentFromHtml( require( '../' + caseItem.expected ) ),
			config = require( caseItem.config ),
			data = require( caseItem.data );

		testUtils.overrideMwConfig( config );

		const expectedHtml = testUtils.getThreadContainer( expected ).innerHTML;
		const reverseExpectedHtml = testUtils.getThreadContainer( dom ).innerHTML;

		const container = testUtils.getThreadContainer( dom );
		const title = mw.Title.newFromText( caseItem.title );
		const threadItemSet = new Parser( data ).parse( container, title );
		const comments = threadItemSet.getCommentItems();

		// Add a reply to every comment. Note that this inserts *all* of the replies, unlike the real
		// thing, which only deals with one at a time. This isn't ideal but resetting everything after
		// every reply would be super slow.
		const nodes = [];
		comments.forEach( ( comment ) => {
			const node = modifier.addListItem( comment, caseItem.replyIndentation || 'invisible' );
			node.textContent = 'Reply to ' + comment.id;
			nodes.push( node );
		} );

		// Uncomment this to get updated content for the "modified HTML" files, for copy/paste:
		// console.log( container.innerHTML );

		const actualHtml = container.innerHTML;

		assert.strictEqual(
			actualHtml,
			expectedHtml,
			comments.length + ' replies added'
		);

		// Now discard the replies and verify we get the original document back.
		nodes.forEach( ( node ) => {
			modifier.removeAddedListItem( node );
		} );

		const reverseActualHtml = container.innerHTML;
		assert.strictEqual(
			reverseActualHtml,
			reverseExpectedHtml,
			nodes.length + ' replies removed'
		);
	} );
} );

QUnit.test( '#addReplyLink', ( assert ) => {
	const cases = require( '../cases/reply.json' );

	cases.forEach( ( caseItem ) => {
		const dom = ve.createDocumentFromHtml( require( '../' + caseItem.dom ) ),
			expected = ve.createDocumentFromHtml( require( '../' + caseItem.expected ) ),
			config = require( caseItem.config ),
			data = require( caseItem.data );

		testUtils.overrideMwConfig( config );

		const expectedHtml = testUtils.getThreadContainer( expected ).innerHTML;

		const container = testUtils.getThreadContainer( dom );
		const title = mw.Title.newFromText( caseItem.title );
		const threadItemSet = new Parser( data ).parse( container, title );
		const comments = threadItemSet.getCommentItems();

		// Add a reply link to every comment.
		comments.forEach( ( comment ) => {
			const linkNode = document.createElement( 'a' );
			linkNode.textContent = 'Reply';
			linkNode.href = '#';
			modifier.addReplyLink( comment, linkNode );
		} );

		// Uncomment this to get updated content for the "reply HTML" files, for copy/paste:
		// console.log( container.innerHTML );

		const actualHtml = container.innerHTML;

		assert.strictEqual(
			actualHtml,
			expectedHtml,
			caseItem.name
		);
	} );
} );

QUnit.test( '#unwrapList', ( assert ) => {
	const cases = require( '../cases/unwrap.json' );

	cases.forEach( ( caseItem ) => {
		const container = document.createElement( 'div' );

		container.innerHTML = caseItem.html;
		modifier.unwrapList( container.childNodes[ caseItem.index || 0 ] );

		assert.strictEqual(
			container.innerHTML,
			caseItem.expected,
			caseItem.name
		);
	} );
} );

QUnit.test( 'sanitizeWikitextLinebreaks', ( assert ) => {
	const cases = require( '../cases/sanitize-wikitext-linebreaks.json' );

	cases.forEach( ( caseItem ) => {
		assert.strictEqual(
			modifier.sanitizeWikitextLinebreaks( caseItem.wikitext ),
			caseItem.expected,
			caseItem.msg
		);
	} );
} );

// TODO:
// * addSiblingListItem
