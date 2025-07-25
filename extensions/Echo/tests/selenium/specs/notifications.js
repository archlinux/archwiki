'use strict';

const NotificationsPage = require( '../pageobjects/notifications.page' );
const UserLoginPage = require( 'wdio-mediawiki/LoginPage' );

describe( 'Notifications', () => {

	it( 'checks for Notifications Page @daily', async () => {

		await UserLoginPage.login( browser.config.mwUser, browser.config.mwPwd );
		await NotificationsPage.open();

		await expect( await NotificationsPage.notificationHeading ).toHaveText( 'Notifications' );

	} );

} );
