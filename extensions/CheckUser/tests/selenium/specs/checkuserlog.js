'use strict';

const LoginAsCheckUser = require( '../checkuserlogin' );
const CheckUserLogPage = require( '../pageobjects/checkuserlog.page' );

describe( 'CheckUserLog', () => {
	describe( 'Without CheckUser user group', () => {
		it( 'Should display permission error to logged-out user', async () => {
			await CheckUserLogPage.open();

			await expect( await CheckUserLogPage.hasPermissionErrors ).toExist();
		} );
	} );
	describe( 'With CheckUser user group', () => {
		before( async () => {
			await LoginAsCheckUser.loginAsCheckUser();
			await CheckUserLogPage.open();
		} );
		describe( 'Verify checkuser can interact with the CheckUser log', () => {
			it( 'Should show target input', async () => {
				await expect( await CheckUserLogPage.targetInput ).toExist();
			} );
			it( 'Should show initiator input', async () => {
				await expect( await CheckUserLogPage.initiatorInput ).toExist();
			} );
			it( 'Should show start date selector', async () => {
				await expect( await CheckUserLogPage.startDateSelector ).toExist();
			} );
			it( 'Should show end date selector', async () => {
				await expect( await CheckUserLogPage.endDateSelector ).toExist();
			} );
			it( 'Should show search button', async () => {
				await expect( await CheckUserLogPage.search ).toExist();
			} );
			it( 'Should be able to use the filters to search', async () => {
				// @todo check if the filters had any effect?
				await CheckUserLogPage.initiatorInput.setValue( process.env.MEDIAWIKI_USER );
				await CheckUserLogPage.initiatorInput.click();
				// Initiator input will be missing if the request failed.
				await expect( await CheckUserLogPage.initiatorInput ).toExist();
			} );
		} );
	} );
} );
