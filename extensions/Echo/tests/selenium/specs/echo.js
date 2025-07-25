'use strict';

const assert = require( 'assert' ),
	EchoPage = require( '../pageobjects/echo.page' ),
	UserLoginPage = require( 'wdio-mediawiki/LoginPage' );

describe( 'Echo', () => {
	it( 'alerts and notices are visible after logging in @daily', async () => {

		await UserLoginPage.login( browser.config.mwUser, browser.config.mwPwd );

		assert( await EchoPage.alerts.isExisting() );
		assert( await EchoPage.notices.isExisting() );

	} );

	it( 'flyout for alert appears when clicked @daily', async () => {

		await UserLoginPage.login( browser.config.mwUser, browser.config.mwPwd );
		await EchoPage.alerts.click();
		await EchoPage.alertsFlyout.waitForDisplayed();

		assert( await EchoPage.alertsFlyout.isExisting() );

	} );

	it( 'flyout for notices appears when clicked @daily', async () => {

		await UserLoginPage.login( browser.config.mwUser, browser.config.mwPwd );
		await EchoPage.notices.click();
		await EchoPage.noticesFlyout.waitForDisplayed();

		assert( await EchoPage.noticesFlyout.isExisting() );

	} );
} );
