import { viewEditPage as ViewEditPage } from '../pageobjects/viewedit.page.js';

describe( 'Filter editing', () => {
	it( 'editing interface is not visible to logged-out users', async () => {
		await ViewEditPage.open( 'new' );
		await expect( ViewEditPage.error ).toBeDisplayed();
	} );
} );
