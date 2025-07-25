'use strict';

const LoginPage = require( 'wdio-mediawiki/LoginPage' );

class LoginAsCheckUser {
	/**
	 * Returns the password and username for the account that has the checkuser group.
	 *
	 * @return {{password: string, username: string}}
	 */
	getCheckUserAccountDetails() {
		return { username: 'SeleniumCheckUserAccount', password: 'SeleniumCheckUserPassword' };
	}

	/**
	 * Logs in to the account created for CheckUser by
	 * this.createCheckUserAccount.
	 *
	 * @return {Promise<void>}
	 */
	async loginAsCheckUser() {
		const checkUserAccountDetails = this.getCheckUserAccountDetails();
		await LoginPage.login( checkUserAccountDetails.username, checkUserAccountDetails.password );
	}
}

module.exports = new LoginAsCheckUser();
