import LoginPage from 'wdio-mediawiki/LoginPage';
import { viewListPage as ViewListPage } from '../pageobjects/viewlist.page.js';

describe( 'Special:AbuseFilter', () => {
	it( 'page should exist on installation', async () => {
		await ViewListPage.open();
		expect( await ViewListPage.title.getText() ).toBe( 'Abuse filter management' );
	} );
	it( 'page should have the button for creating a new filter', async () => {
		await LoginPage.loginAdmin();
		await ViewListPage.open();
		expect( await ViewListPage.newFilterButton.getText() ).toBe( 'Create a new filter' );
		const newFilterButton = await ViewListPage.newFilterButton.getAttribute( 'href' );
		expect( newFilterButton.indexOf( 'Special:AbuseFilter/new' ) ).not.toBe( -1 );
	} );
} );
