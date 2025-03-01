'use strict';

const { action, assert, REST, utils } = require( 'api-testing' );

let pathPrefix = 'rest.php/content/v1';

describe( 'Revision', () => {
	const page = utils.title( 'Revision' );
	let client;
	let mindy;
	let newrevid, pageid, param_summary;

	before( async () => {
		client = new REST( pathPrefix );
		mindy = await action.mindy();

		const resp = await mindy.edit( page, {
			text: 'Hello World',
			summary: 'creating page'
		} );
		( { newrevid, pageid, param_summary } = resp );
	} );

	describe( 'GET /revision/{id}', () => {
		it( 'should successfully get source and metadata for revision', async () => {
			const { status, body, text, headers } = await client.get( `/revision/${ newrevid }` );

			assert.strictEqual( status, 200, text );
			assert.match( headers[ 'content-type' ], /^application\/json/ );
			assert.match( headers.vary, /\bx-restbase-compat\b/ );
			assert.strictEqual( body.id, newrevid );
			assert.strictEqual( body.minor, false );
			assert.deepEqual( body.page, { id: pageid, title: page, key: utils.dbkey( page ) } );
			assert.nestedProperty( body, 'timestamp' );
			assert.nestedPropertyVal( body, 'user.name', mindy.username );
			assert.strictEqual( body.comment, param_summary );
			assert.isOk( headers.etag, 'etag' );
			assert.equal( Date.parse( body.timestamp ), Date.parse( headers[ 'last-modified' ] ) );
			assert.nestedPropertyVal( body, 'source', 'Hello World' );
		} );

		it( 'should return 404 for revision that does not exist', async () => {
			const { status } = await client.get( '/revision/99999999' );

			assert.strictEqual( status, 404 );
		} );
	} );

	describe( 'GET /revision/{id}/bare', () => {
		it( 'should successfully get information about revision', async () => {
			const { status, body, text, headers } = await client.get( `/revision/${ newrevid }/bare` );

			assert.strictEqual( status, 200, text );
			assert.match( headers[ 'content-type' ], /^application\/json/ );
			assert.strictEqual( body.id, newrevid );
			assert.strictEqual( body.minor, false );
			assert.deepEqual( body.page, { id: pageid, title: page, key: utils.dbkey( page ) } );
			assert.nestedProperty( body, 'timestamp' );
			assert.nestedPropertyVal( body, 'user.name', mindy.username );
			assert.strictEqual( body.comment, param_summary );
			assert.isOk( headers.etag, 'etag' );
			assert.equal( Date.parse( body.timestamp ), Date.parse( headers[ 'last-modified' ] ) );
			assert.nestedProperty( body, 'html_url' );
		} );

		it( 'should return 404 for revision that does not exist', async () => {
			const { status } = await client.get( '/revision/99999999/bare' );

			assert.strictEqual( status, 404 );
		} );
	} );

	describe( 'GET /revision/{id}/bare with x-restbase-compat', () => {
		it( 'Should successfully return restbase-compatible revision meta-data', async () => {
			const { status, body, text, headers } = await client
				.get( `/revision/${ newrevid }/bare` )
				.set( 'x-restbase-compat', 'true' );

			assert.deepEqual( status, 200, text );
			assert.match( headers[ 'content-type' ], /^application\/json/ );
			assert.match( headers.vary, /\bx-restbase-compat\b/ );
			assert.containsAllKeys( body.items[ 0 ], [ 'title', 'page_id', 'rev', 'tid', 'namespace', 'user_id',
				'user_text', 'timestamp', 'comment', 'tags', 'restrictions', 'page_language', 'redirect' ] );

			assert.deepEqual( body.items[ 0 ].title, utils.dbkey( page ) );
			assert.deepEqual( body.items[ 0 ].page_id, pageid );
			assert.deepEqual( body.items[ 0 ].rev, newrevid );
		} );

		it( 'Should successfully return restbase-compatible errors', async () => {
			const { status, body, text, headers } = await client
				.get( '/revision/987654321/bare' )
				.set( 'x-restbase-compat', 'true' );

			assert.deepEqual( status, 404, text );
			assert.match( headers[ 'content-type' ], /^application\/json/ );
			assert.containsAllKeys( body, [ 'type', 'title', 'method', 'detail', 'uri' ] );
		} );
	} );

	describe( 'GET /revision/{id}/with_html', () => {
		it( 'should successfully get metadata and HTML of revision', async () => {
			const { status, body, text, headers } = await client.get( `/revision/${ newrevid }/with_html` );

			assert.strictEqual( status, 200, text );
			assert.match( headers[ 'content-type' ], /^application\/json/ );
			assert.strictEqual( body.id, newrevid );
			assert.strictEqual( body.minor, false );
			assert.deepEqual( body.page, { id: pageid, title: page, key: utils.dbkey( page ) } );
			assert.nestedProperty( body, 'timestamp' );
			assert.nestedPropertyVal( body, 'user.name', mindy.username );
			assert.strictEqual( body.comment, param_summary );
			assert.isOk( headers.etag, 'etag' );

			assert.nestedProperty( body, 'html' );
			assert.match( body.html, /<html / );
			assert.match( body.html, /Hello World/ );

			// The last-modified date is the render timestamp, which may be newer than the revision
			const headerDate = Date.parse( headers[ 'last-modified' ] );
			const revDate = Date.parse( body.timestamp );
			assert.strictEqual( revDate.valueOf() <= headerDate.valueOf(), true );
		} );

		it( 'should return 404 for revision that does not exist', async () => {
			const { status } = await client.get( '/revision/99999999/with_html' );

			assert.strictEqual( status, 404 );
		} );

		it( 'should perform variant conversion', async () => {
			const { headers, text } = await client.get( `/revision/${ newrevid }/with_html`, null, {
				'accept-language': 'en-x-piglatin'
			} );

			assert.match( text, /Ellohay/ );
			assert.match( text, /Orldway/ );
			assert.match( headers[ 'content-type' ], /^application\/json/ );
			assert.match( headers.vary, /\bAccept-Language\b/i );
			assert.match( headers.etag, /en-x-piglatin/i );
			// Since with_html returns JSON, content language is not set
			// but if its set, we expect it to be set correctly.
			const contentLanguageHeader = headers[ 'content-language' ];
			if ( contentLanguageHeader ) {
				assert.match( headers[ 'content-language' ], /en-x-piglatin/i );
			}
		} );
	} );

	describe( 'GET /revision/{id}/html', () => {
		it( 'should successfully get HTML of revision', async () => {
			const { status, text, headers } = await client.get( `/revision/${ newrevid }/html` );

			assert.strictEqual( status, 200, text );
			assert.containsAllKeys( headers, [ 'etag', 'cache-control', 'last-modified', 'content-type' ] );
			assert.match( headers[ 'content-type' ], /^text\/html/ );

			assert.match( text, /<html / );
			assert.match( text, /Hello World/ );
			assert.match( headers.etag, /^".*"$/, 'ETag must be present and not marked weak' );
		} );

		it( 'should return 404 for revision that does not exist', async () => {
			const { status } = await client.get( '/revision/99999999/html' );

			assert.strictEqual( status, 404 );
		} );

		it( 'should perform variant conversion', async () => {
			const { headers, text } = await client.get( `/revision/${ newrevid }/html`, null, {
				'accept-language': 'en-x-piglatin'
			} );

			assert.match( text, /Ellohay/ );
			assert.match( text, /Orldway/ );
			assert.match( headers[ 'content-type' ], /^text\/html/ );
			assert.match( headers.vary, /\bAccept-Language\b/i );
			assert.match( headers[ 'content-language' ], /en-x-piglatin/i );
			assert.match( headers.etag, /en-x-piglatin/i );
		} );
	} );

	describe( 'GET /revision/{id}/html with x-restbase-compat', () => {
		it( 'Should successfully return restbase-compatible errors', async () => {
			const { body, headers } = await client
				.get( '/revision/99999999/html' )
				.set( 'x-restbase-compat', 'true' );

			assert.match( headers[ 'content-type' ], /^application\/json/ );
			assert.containsAllKeys( body, [ 'type', 'title', 'method', 'detail', 'uri' ] );
		} );
	} );
} );

// eslint-disable-next-line mocha/no-exports
exports.init = function ( pp ) {
	// Allow testing both legacy and module paths using the same tests
	pathPrefix = pp;
};
