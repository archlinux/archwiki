import { createApiClient } from 'wdio-mediawiki/Api.js';
import MathPage from '../pageobjects/math.page.js';

describe( 'Math', () => {
	let api;

	before( async () => {
		api = await createApiClient();
	} );

	it( 'should work for addition', async () => {

		// page should have random name
		const pageName = Math.random().toString();

		// create a page with a simple addition
		await api.edit( pageName, '<math>3 + 2</math>' );

		await MathPage.openTitle( pageName );

		// check if the page displays the image
		await expect( await MathPage.mathml ).toExist();

	} );

} );
