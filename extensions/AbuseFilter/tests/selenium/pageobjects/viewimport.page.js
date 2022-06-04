'use strict';

const Page = require( 'wdio-mediawiki/Page' );

class ViewImportPage extends Page {
	get importData() { return $( 'textarea[name="wpImportText"]' ); }
	get submit() { return $( 'button[type="submit"]' ); }

	importText( text ) {
		this.open();
		this.importData.setValue( text );
		this.submit.click();
	}

	open() {
		super.openTitle( 'Special:AbuseFilter/import' );
	}
}
module.exports = new ViewImportPage();
