import EditPage from '../pageobjects/edit.page.js';
import LoginPage from 'wdio-mediawiki/LoginPage';
import * as Util from 'wdio-mediawiki/Util';
import { Key } from 'webdriverio';

describe( 'Content Editable Formatting', () => {

	let name;

	before( async () => {
		await LoginPage.loginAdmin();
	} );

	beforeEach( async () => {
		await EditPage.clearBeforeUnload();
		name = Util.getTestString();
		await EditPage.openForEditing( name );
		await EditPage.activationComplete();
		await EditPage.focusRootNode();
	} );

	it( 'should change text to Page title', async () => {
		await EditPage.veRootNode.setValue( 'Page title' );

		await browser.keys( [ Key.Control, '1' ] );
		await expect( await EditPage.pageTitle ).toBeDisplayed();
	} );

	it( 'should change text to Heading', async () => {
		await EditPage.veRootNode.setValue( 'Heading' );

		await browser.keys( [ Key.Control, '2' ] );
		await expect( await EditPage.heading ).toBeDisplayed();
	} );

	it( 'should change text to Sub-heading 1', async () => {
		await EditPage.veRootNode.setValue( 'Sub-heading 1' );

		await browser.keys( [ Key.Control, '3' ] );
		await expect( await EditPage.subHeadingOne ).toBeDisplayed();
	} );

	it( 'should change text to Sub-heading 2', async () => {
		await EditPage.veRootNode.setValue( 'Sub-heading 2' );

		await browser.keys( [ Key.Control, '4' ] );
		await expect( await EditPage.subHeadingTwo ).toBeDisplayed();
	} );

	it( 'should change text to Sub-heading 3', async () => {
		await EditPage.veRootNode.setValue( 'Sub-heading 3' );

		await browser.keys( [ Key.Control, '5' ] );
		await expect( await EditPage.subHeadingThree ).toBeDisplayed();
	} );

	it( 'should change text to Sub-heading 4', async () => {
		await EditPage.veRootNode.setValue( 'Sub-heading 4' );

		await browser.keys( [ Key.Control, '6' ] );
		await expect( await EditPage.subHeadingFour ).toBeDisplayed();
	} );

	it( 'should change text to Preformatted', async () => {
		await EditPage.veRootNode.setValue( 'Preformatted' );

		await browser.keys( [ Key.Control, '7' ] );
		await expect( await EditPage.preformatted ).toBeDisplayed();
	} );

	it( 'should change text to Block quote', async () => {
		await EditPage.veRootNode.setValue( 'Block quote' );

		await browser.keys( [ Key.Control, '8' ] );
		await expect( await EditPage.blockQuote ).toBeDisplayed();
	} );

	it( 'should change formatting to Bold', async () => {
		await browser.keys( [ Key.Control, 'b' ] );

		await expect( await EditPage.bold ).toBeDisplayed();
	} );

	it( 'should change formatting to Italic', async () => {
		await browser.keys( [ Key.Control, 'i' ] );

		await expect( await EditPage.italic ).toBeDisplayed();
	} );

	it( 'should change formatting to Superscript', async () => {
		await browser.keys( [ Key.Control, '.' ] );

		await expect( await EditPage.superscript ).toBeDisplayed();
	} );

	it( 'should change formatting to Subscript', async () => {
		await browser.keys( [ Key.Control, ',' ] );

		await expect( await EditPage.subscript ).toBeDisplayed();
	} );

	it( 'should change formatting to Computer code', async () => {
		await browser.keys( [ Key.Control, Key.Shift, '6' ] );

		await expect( await EditPage.code ).toBeDisplayed();
	} );

	it( 'should change formatting to Strikethrough', async () => {
		await browser.keys( [ Key.Control, Key.Shift, '5' ] );

		await expect( await EditPage.strikethrough ).toBeDisplayed();
	} );

	it( 'should change formatting to Underline', async () => {
		await browser.keys( [ Key.Control, 'u' ] );

		await expect( await EditPage.underline ).toBeDisplayed();
	} );

} );
