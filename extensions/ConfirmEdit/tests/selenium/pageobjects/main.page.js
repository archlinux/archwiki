import Page from 'wdio-mediawiki/Page.js';

class MainPage extends Page {
	get heading() {
		return $( '#firstHeading' );
	}

	async open() {
		await super.openTitle( 'Main_Page' );
	}
}

export default new MainPage();
