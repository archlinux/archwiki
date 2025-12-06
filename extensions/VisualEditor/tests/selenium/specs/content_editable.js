import EditPage from '../pageobjects/edit.page.js';
import LoginPage from 'wdio-mediawiki/LoginPage';
import * as Util from 'wdio-mediawiki/Util';

describe( 'Content Editable', () => {

	let name, content;

	beforeEach( async () => {
		content = Util.getTestString();
		name = Util.getTestString();
		await LoginPage.loginAdmin();

		await EditPage.openForEditing( name );
		await EditPage.toolbar.waitForDisplayed( { timeout: 20000 } );
	} );

	afterEach( async () => {
		// T269566: Popup with text
		// 'Leave site? Changes that you made may not be saved. Cancel/Leave'
		// appears after the browser tries to leave the page with the preview.
		await browser.reloadSession();
	} );

	it( 'should load when an url is opened @daily', async () => {
		await expect( await EditPage.toolbar ).toBeDisplayed();
	} );

	it( 'should be editable', async () => {
		await EditPage.veRootNode.setValue( content );

		await expect( await EditPage.veRootNode ).toHaveText( content );
	} );

	// Skipped starting 2025-08-14 because of T401573
	it.skip( 'should save an edit', async () => {
		await EditPage.veRootNode.setValue( content );
		await EditPage.savePageDots.click();
		await EditPage.savePage.waitForClickable();
		await EditPage.savePage.click();

		await EditPage.saveComplete();
		await expect( await EditPage.notification ).toHaveText( 'The page has been created.' );
	} );

	// Skipped starting 2025-08-14 because of T401573
	it.skip( 'should insert a table', async () => {
		await EditPage.insertTable();

		await expect( await EditPage.insertedTable ).toBeDisplayed();
	} );

} );
