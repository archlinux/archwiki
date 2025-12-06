import NotificationsPage from '../pageobjects/notifications.page.js';
import UserLoginPage from 'wdio-mediawiki/LoginPage';

describe( 'Notifications', () => {

	it( 'checks for Notifications Page @daily', async () => {

		await UserLoginPage.login( browser.options.capabilities[ 'mw:user' ], browser.options.capabilities[ 'mw:pwd' ] );
		await NotificationsPage.open();

		await expect( await NotificationsPage.notificationHeading ).toHaveText( 'Notifications' );

	} );

} );
