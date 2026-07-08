import MainPage from '../pageobjects/main.page.js';

describe( 'ConfirmEdit', () => {
	it( 'Main page should be accessible', async () => {
		await MainPage.open();
		await expect( MainPage.heading ).toBeDisplayed();
	} );
} );
