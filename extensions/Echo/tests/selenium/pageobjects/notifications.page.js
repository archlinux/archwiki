'use strict';

const Page = require( 'wdio-mediawiki/Page' );

class NotificationsPage extends Page {

	get notificationHeading() {
		return $( '#firstHeading' );
	}

	async open() {
		return super.openTitle( 'Special:Notifications', { uselang: 'en' } );
	}
}

module.exports = new NotificationsPage();
