import assert from 'node:assert';
import EchoPage from '../pageobjects/echo.page.js';
import UserLoginPage from 'wdio-mediawiki/LoginPage';

describe( 'Echo', () => {

	before( async () => {
		await UserLoginPage.login( browser.options.capabilities[ 'mw:user' ], browser.options.capabilities[ 'mw:pwd' ] );
	} );

	it( 'alerts and notices are visible after logging in @daily', async () => {

		assert( await EchoPage.alerts.isExisting() );
		assert( await EchoPage.notices.isExisting() );

	} );

	it( 'flyout for alert appears when clicked @daily', async () => {

		await EchoPage.alerts.click();
		await EchoPage.alertsFlyout.waitForDisplayed();

		assert( await EchoPage.alertsFlyout.isExisting() );

	} );

	it( 'flyout for notices appears when clicked @daily', async () => {

		await EchoPage.notices.click();
		await EchoPage.noticesFlyout.waitForDisplayed();

		assert( await EchoPage.noticesFlyout.isExisting() );

	} );
} );
