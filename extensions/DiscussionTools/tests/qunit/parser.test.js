/* global moment */
const
	testUtils = require( './testUtils.js' ),
	Parser = require( 'ext.discussionTools.init' ).Parser;

QUnit.module( 'mw.dt.Parser', QUnit.newMwEnvironment() );

QUnit.test( '#getTimestampRegexp', ( assert ) => {
	const cases = require( '../cases/timestamp-regex.json' ),
		parser = new Parser( require( '../data-en.json' ) );

	cases.forEach( ( caseItem ) => {
		assert.strictEqual(
			parser.getTimestampRegexp( 'en', caseItem.format, '\\d', { UTC: 'UTC' } ),
			caseItem.expected,
			caseItem.message
		);
	} );
} );

QUnit.test( '#getTimestampParser', ( assert ) => {
	const cases = require( '../cases/timestamp-parser.json' ),
		parser = new Parser( require( '../data-en.json' ) );

	cases.forEach( ( caseItem ) => {
		const tsParser = parser.getTimestampParser( 'en', caseItem.format, caseItem.digits, 'UTC', { UTC: 'UTC' } ),
			expectedDate = moment( caseItem.expected );

		assert.true(
			tsParser( caseItem.data ).date.isSame( expectedDate ),
			caseItem.message
		);
	} );
} );

QUnit.test( '#getTimestampParser (at DST change)', ( assert ) => {
	const cases = require( '../cases/timestamp-parser-dst.json' ),
		parser = new Parser( require( '../data-en.json' ) );

	cases.forEach( ( caseItem ) => {
		const regexp = parser.getTimestampRegexp( 'en', caseItem.format, '\\d', caseItem.timezoneAbbrs ),
			tsParser = parser.getTimestampParser( 'en', caseItem.format, null, caseItem.timezone, caseItem.timezoneAbbrs ),
			date = tsParser( caseItem.sample.match( regexp ) ).date;

		assert.true(
			date.isSame( caseItem.expected ),
			caseItem.message
		);
		assert.true(
			date.isSame( caseItem.expectedUtc ),
			caseItem.message
		);
	} );
} );

require( '../cases/comments.json' ).forEach( ( caseItem ) => {

	const testName = '#getThreads (' + caseItem.name + ')';
	QUnit.test( testName, ( assert ) => {
		const dom = ve.createDocumentFromHtml( require( '../' + caseItem.dom ) ),
			expected = require( caseItem.expected ),
			config = require( caseItem.config ),
			data = require( caseItem.data );

		testUtils.overrideMwConfig( config );

		const container = testUtils.getThreadContainer( dom );
		const title = mw.Title.newFromText( caseItem.title );
		const threadItemSet = new Parser( data ).parse( container, title );
		const threads = threadItemSet.getThreads();

		threads.forEach( ( thread, i ) => {
			testUtils.serializeComments( thread, container );

			assert.deepEqual(
				JSON.parse( JSON.stringify( thread ) ),
				expected[ i ],
				caseItem.name + ' section ' + i
			);
		} );

		// Uncomment this to get updated content for the JSON files, for copy/paste:
		// console.log( JSON.stringify( threads, null, 2 ) );
	} );
} );

// TODO:
// * findCommentsById
// * findCommentsByName
// * getThreadItems
