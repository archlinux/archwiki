'use strict';

const LoginAsCheckUser = require( '../checkuserlogin' );
const CheckUserPage = require( '../pageobjects/checkuser.page' );

describe( 'CheckUser', () => {
	describe( 'Without CheckUser user group', () => {
		it( 'Should display permission error to logged-out user', async () => {
			await CheckUserPage.open();

			await expect( await CheckUserPage.hasPermissionErrors ).toExist();
		} );
	} );
	describe( 'With CheckUser user group', () => {
		before( async () => {
			await LoginAsCheckUser.loginAsCheckUser();
			await CheckUserPage.open();
		} );
		describe( 'Verify checkuser can make checks:', () => {
			it( 'Should show target input', async () => {
				await expect( await CheckUserPage.checkTarget ).toExist();
			} );
			it( 'Should show checkuser radios', async () => {
				await expect( await CheckUserPage.checkTypeRadios ).toExist();
				await expect( await CheckUserPage.getIPsCheckTypeRadio ).toExist();
				await expect( await CheckUserPage.getActionsCheckTypeRadio ).toExist();
				await expect( await CheckUserPage.getUsersCheckTypeRadio ).toExist();
			} );
			it( 'Should show duration selector', async () => {
				// Check the duration selector exists
				await expect( await CheckUserPage.durationSelector ).toExist();
			} );
			it( 'Should show check reason input', async () => {
				await expect( await CheckUserPage.checkReasonInput ).toExist();
			} );
			it( 'Should show submit button', async () => {
				await expect( await CheckUserPage.submit ).toExist();
			} );
			it( 'Should show CIDR form before check is run', async () => {
				await expect( await CheckUserPage.cidrForm ).toExist();
			} );
			it( 'Should be able to run \'Get IPs\' check', async () => {
				await CheckUserPage.open();
				await CheckUserPage.getIPsCheckTypeRadio.click();
				await CheckUserPage.checkTarget.setValue( process.env.MEDIAWIKI_USER );
				await CheckUserPage.checkReasonInput.setValue( 'Selenium browser testing' );
				await CheckUserPage.submit.click();
				await expect( await CheckUserPage.getIPsResults ).toExist();
				// CheckUser helper should never be present on Get IPs
				await expect( await CheckUserPage.checkUserHelper ).not.toExist();
				await expect( await CheckUserPage.cidrForm ).toExist();
			} );
			it( 'Should be able to run \'Get actions\' check', async () => {
				await CheckUserPage.open();
				await CheckUserPage.getActionsCheckTypeRadio.click();
				await CheckUserPage.checkTarget.setValue( process.env.MEDIAWIKI_USER );
				await CheckUserPage.checkReasonInput.setValue( 'Selenium browser testing' );
				await CheckUserPage.submit.click();
				await expect( await CheckUserPage.getActionsResults ).toExist();
				await expect( await CheckUserPage.checkUserHelper ).toExist();
				await expect( await CheckUserPage.cidrForm ).toExist();
			} );
			it( 'Should be able to run \'Get users\' check', async () => {
				await CheckUserPage.open();
				await CheckUserPage.getUsersCheckTypeRadio.click();
				await CheckUserPage.checkTarget.setValue( '127.0.0.1' );
				await CheckUserPage.checkReasonInput.setValue( 'Selenium browser testing' );
				await CheckUserPage.submit.click();
				await expect( await CheckUserPage.getUsersResults ).toExist();
				await expect( await CheckUserPage.checkUserHelper ).toExist();
				await expect( await CheckUserPage.cidrForm ).toExist();
			} );
		} );
	} );
} );
