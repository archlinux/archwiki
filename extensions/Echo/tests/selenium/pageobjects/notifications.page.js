import Page from 'wdio-mediawiki/Page';

class NotificationsPage extends Page {

	get notificationHeading() {
		return $( '#firstHeading' );
	}

	async open() {
		return super.openTitle( 'Special:Notifications', { uselang: 'en' } );
	}

}

export default new NotificationsPage();
