import EditPage from '../pageobjects/edit.page.js';
import { createApiClient } from 'wdio-mediawiki/Api.js';
import LoginPage from 'wdio-mediawiki/LoginPage.js';

describe( 'TemplateData users can favorite templates via VisualEditor', function () {

	before( async function () {
		const api = await createApiClient();
		await api.edit( 'Template:Without_TemplateData_1', 'Template 1.' );
		await api.edit( 'Template:Without_TemplateData_2', 'Template 2.' );
		if ( !await EditPage.openTemplateInsertionDialog() ) {
			this.skip();
		}
	} );

	it( 'show a template search field, with focus', async function () {
		await expect( await EditPage.templateSearchFieldInput ).toBeFocused( 'Template search field has focus.' );
	} );

	it( 'show anon users a message about needing to log in', async function () {
		await expect( await EditPage.emptyListLabel ).toHaveText( 'Please log in to mark templates as favorites', { message: 'Message is shown.' } );
	} );

	it( 'logged-in users can favorite templates from the search results', async function () {
		await LoginPage.loginAdmin();
		await EditPage.openTemplateInsertionDialog();
		// A user initially has zero favorites.
		await expect( await EditPage.templateListMenuItems ).toBeElementsArrayOfSize( 0 );
		// Search for the prefix of the test templates.
		await EditPage.templateSearchFieldInput.setValue( 'Without' );
		await EditPage.searchResultsMenu.waitForDisplayed();
		// See both test templates in the search results.
		await expect( await EditPage.searchResultsMenu ).toHaveChildren( 2, { message: 'Two search results are shown.' } );
		// Favorite both of them.
		const faveButtonOne = await EditPage.getSearchResultFavoriteButton( 1 );
		await faveButtonOne.click();
		await expect( await faveButtonOne.$( '.oo-ui-iconElement-icon' ) ).toHaveElementClass( 'oo-ui-icon-bookmark' );
		const faveButtonTwo = await EditPage.getSearchResultFavoriteButton( 2 );
		await faveButtonTwo.click();
		await expect( await faveButtonTwo.$( '.oo-ui-iconElement-icon' ) ).toHaveElementClass( 'oo-ui-icon-bookmark' );
		// Click away from the search field, and count the number of displayed favorites.
		await EditPage.dialogHeader.click();
		await expect( await EditPage.templateListMenuItems ).toBeElementsArrayOfSize( 2 );
		// Load the page again, and still find the two favorites.
		await EditPage.openTemplateInsertionDialog();
		await expect( await EditPage.templateListMenuItems ).toBeElementsArrayOfSize( 2 );
	} );

} );
