import EditPage from '../pageobjects/edit.page.js';
import LoginPage from 'wdio-mediawiki/LoginPage';
import * as Util from 'wdio-mediawiki/Util';
import { Key } from 'webdriverio';

describe( 'Content Editable', () => {

	let name, content;

	before( async () => {
		await LoginPage.loginAdmin();
	} );

	beforeEach( async () => {
		await EditPage.clearBeforeUnload();
		content = Util.getTestString();
		name = Util.getTestString();
		await EditPage.openForEditing( name );
		await EditPage.activationComplete();
		await EditPage.focusRootNode();
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

	it( 'should insert a table', async () => {
		await EditPage.veRootNode.setValue( '{|' );

		await expect( await EditPage.insertedTable ).toBeDisplayed();
	} );

	it( 'should insert Bullet list', async () => {
		await EditPage.veRootNode.setValue( '* ' );

		await expect( await EditPage.insertedBulletList ).toBeDisplayed();
	} );

	it( 'should insert Numbered list', async () => {
		await EditPage.veRootNode.setValue( '1. ' );

		await expect( await EditPage.insertedNumberedList ).toBeDisplayed();
	} );

	it( 'should insert and indent Bullet list', async () => {
		await EditPage.veRootNode.setValue( '* ' );

		await expect( await EditPage.insertedBulletList ).toBeDisplayed();

		await browser.keys( [ Key.Tab ] );

		await expect( await EditPage.indentedBulletList ).toBeDisplayed();
	} );

	it( 'should insert and indent Numbered list', async () => {
		await EditPage.veRootNode.setValue( '1. ' );

		await expect( await EditPage.insertedNumberedList ).toBeDisplayed();

		await browser.keys( [ Key.Tab ] );

		await expect( await EditPage.indentedNumberedList ).toBeDisplayed();
	} );

	it( 'should insert an internal link', async () => {
		await EditPage.veRootNode.setValue( '[[' );

		await expect( await EditPage.linkMenu ).toBeDisplayed();

		await EditPage.linkInput.setValue( 'Main_Page' );
		await browser.keys( [ Key.Enter ] );

		await expect( await EditPage.insertedInternalLink ).toBeDisplayed();
	} );

	it( 'should insert an external link', async () => {
		await EditPage.veRootNode.setValue( '[[' );

		await expect( await EditPage.linkMenu ).toBeDisplayed();

		await EditPage.linkInput.setValue( 'https://mediawiki.org' );
		await browser.keys( [ Key.Enter ] );

		await expect( await EditPage.insertedExternalLink ).toBeDisplayed();
	} );

	it( 'should insert a comment', async () => {
		await EditPage.veRootNode.setValue( '<!--' );

		await expect( await EditPage.commentMenu ).toBeDisplayed();

		await EditPage.commentInput.setValue( 'foobar' );
		await browser.keys( [ Key.Control, Key.Enter ] );

		await expect( await EditPage.insertedComment ).toBeDisplayed();
	} );

} );
