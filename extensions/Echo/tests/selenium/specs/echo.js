'use strict';

const assert = require( 'assert' ),
	EchoPage = require( '../pageobjects/echo.page' ),
	UserLoginPage = require( 'wdio-mediawiki/LoginPage' ),
	Util = require( 'wdio-mediawiki/Util' ),
	Api = require( 'wdio-mediawiki/Api' );

describe( 'Echo', () => {
	let bot;

	before( async () => {
		bot = await Api.bot();
	} );

	it( 'alerts and notices are visible after logging in @daily', async () => {

		await UserLoginPage.login( browser.config.mwUser, browser.config.mwPwd );

		assert( EchoPage.alerts.isExisting() );
		assert( EchoPage.notices.isExisting() );

	} );

	it( 'flyout for alert appears when clicked @daily', async () => {

		await UserLoginPage.login( browser.config.mwUser, browser.config.mwPwd );
		await EchoPage.alerts.click();
		EchoPage.alertsFlyout.waitForDisplayed();

		assert( EchoPage.alertsFlyout.isExisting() );

	} );

	it( 'flyout for notices appears when clicked @daily', async () => {

		await UserLoginPage.login( browser.config.mwUser, browser.config.mwPwd );
		await EchoPage.notices.click();
		EchoPage.noticesFlyout.waitForDisplayed();

		assert( EchoPage.noticesFlyout.isExisting() );

	} );

	// Skipped on 2022-01-17 in 754491 because of T299339
	it.skip( 'checks for welcome message after signup', async () => {

		const username = Util.getTestString( 'NewUser-' );
		const password = Util.getTestString();

		await Api.createAccount( bot, username, password );

		await UserLoginPage.login( username, password );

		await EchoPage.notices.click();

		await EchoPage.alertMessage.waitForDisplayed();
		const regexp = /Welcome to .*, .*! We're glad you're here./;
		assert( regexp.test( await EchoPage.alertMessage.getText() ) );

	} );

} );
