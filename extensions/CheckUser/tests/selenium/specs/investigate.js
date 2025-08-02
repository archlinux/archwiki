'use strict';

const LoginAsCheckUser = require( '../checkuserlogin' );
const InvestigatePage = require( '../pageobjects/investigate.page' );

describe( 'Investigate', () => {
	describe( 'Without CheckUser user group', () => {
		it( 'Should display permission error to logged-out user', async () => {
			await InvestigatePage.open();

			await expect( await InvestigatePage.hasPermissionErrors ).toExist();
		} );
	} );
	describe( 'With CheckUser user group', () => {
		before( async () => {
			await LoginAsCheckUser.loginAsCheckUser();
			await InvestigatePage.open();
		} );
		it( 'Should show targets input', async () => {
			await expect( await InvestigatePage.targetsInput ).toExist();
		} );
		it( 'Should show duration selector', async () => {
			await expect( await InvestigatePage.durationSelector ).toExist();
		} );
		it( 'Should show reason field', async () => {
			await expect( await InvestigatePage.reasonInput ).toExist();
		} );
	} );
} );
