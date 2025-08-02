'use strict';

const Page = require( 'wdio-mediawiki/Page' );

class LogoutPage extends Page {
	get submit() {
		return $( 'button[type="submit"]' );
	}

	get personalToolsDropdown() {
		return $( '#vector-user-links-dropdown' );
	}

	get logoutMenuItem() {
		return $( '#pt-logout' );
	}

	async open() {
		return super.openTitle( 'Special:UserLogout' );
	}

	/**
	 * Logout via clicking the logout menu item in personal tools
	 */
	async logoutViaMenuItem() {
		await this.open();
		await this.personalToolsDropdown.click();
		await this.logoutMenuItem.click();
	}
}

module.exports = new LogoutPage();
