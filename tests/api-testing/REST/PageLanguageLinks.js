'use strict';

const { action, assert, REST, utils } = require( 'api-testing' );
const chai = require( 'chai' );
const expect = chai.expect;

const chaiResponseValidator = require( 'chai-openapi-response-validator' ).default;

describe( 'Page language links', () => {
	let client, mindy, openApiSpec;
	const baseEditText = "''Edit 1'' and '''Edit 2'''";
	const page = utils.title( 'PageLanguageLinks_' );

	beforeEach( async () => {
		mindy = await action.mindy();
		await mindy.edit( page, { text: baseEditText } );
		client = new REST( 'rest.php' );

		const { status, text } = await client.get( '/specs/v0/module/-' );
		assert.deepEqual( status, 200, text );

		openApiSpec = JSON.parse( text );
		chai.use( chaiResponseValidator( openApiSpec ) );

	} );

	describe( 'GET /page/{title}/links/language', () => {
		it( 'Should successfully return interlanguage links for the page', async () => {
			const res = await client.get( `/v1/page/${ page }/links/language`, null, {
				'accept-language': 'en-x-piglatin'
			} );
			const { status } = res;
			assert.deepEqual( status, 200 );
			// eslint-disable-next-line no-unused-expressions
			expect( res ).to.satisfyApiSpec;

		} );
		it( 'Should return 404 for non existing page', async () => {
			const dummyPageTitle = utils.title( 'DummyPage_' );
			const res = await client.get( `/v1/page/${ dummyPageTitle }/links/language` );
			const { status } = res;
			assert.deepEqual( status, 404 );
			// eslint-disable-next-line no-unused-expressions
			expect( res ).to.satisfyApiSpec;

		} );
	} );
} );
