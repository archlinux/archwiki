'use strict';

const Api = require( 'wdio-mediawiki/Api' );
const MathPage = require( '../pageobjects/math.page' );

describe( 'Math', () => {
	let bot;

	before( async () => {
		bot = await Api.bot();
	} );

	it( 'should work for addition', async () => {

		// page should have random name
		const pageName = Math.random().toString();

		// create a page with a simple addition
		await bot.edit( pageName, '<math>3 + 2</math>' );

		await MathPage.openTitle( pageName );

		// check if the page displays the image
		await expect( await MathPage.mathml ).toExist();

	} );

} );
