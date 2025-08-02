'use strict';
const EditPage = require( '../pageobjects/edit.page' );
const Util = require( 'wdio-mediawiki/Util' );

describe( 'Toolbar', () => {

	let name;

	beforeEach( async () => {
		name = Util.getTestString();
		await EditPage.openForEditing( name );
		await EditPage.toolbar.waitForDisplayed( { timeout: 20000 } );
	} );

	afterEach( async () => {
		// T269566: Popup with text
		// 'Leave site? Changes that you made may not be saved. Cancel/Leave'
		// appears after the browser tries to leave the page with the preview.
		await browser.reloadSession();
	} );

	it( 'should open notices popup as soon as it loads', async () => {
		await expect( await EditPage.notices ).toBeDisplayed();
	} );

	it( 'should open switch editor', async () => {
		await EditPage.switchEditorElement.click();

		await expect( await EditPage.visualEditing ).toBeDisplayed();
	} );

	it( 'should open page options', async () => {
		await EditPage.pageOptionsElement.click();

		await expect( await EditPage.options ).toBeDisplayed();
	} );

	it( 'should open help popup', async () => {
		await EditPage.helpElement.click();

		await expect( await EditPage.helpPopup ).toBeDisplayed();
	} );

	it( 'should open insert menu', async () => {
		await EditPage.insert.click();

		await expect( await EditPage.insertMenu ).toBeDisplayed();
	} );

	it( 'should open structure options menu', async () => {
		await EditPage.structureOptionsElement.click();

		await expect( await EditPage.bulletListOption ).toBeDisplayed();
	} );

	it( 'should open style text options', async () => {
		await EditPage.styleTextElement.click();

		await expect( await EditPage.boldTextStyleOption ).toBeDisplayed();
	} );

	it( 'should open format paragraph menu', async () => {
		await EditPage.formatParagraphElement.click();

		await expect( await EditPage.paragraphFormatMenu ).toBeDisplayed();
	} );

} );
