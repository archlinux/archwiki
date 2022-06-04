'use strict';

const Page = require( 'wdio-mediawiki/Page' );

class ViewListPage extends Page {
	get title() { return $( '#firstHeading' ); }
	get newFilterButton() { return $( '.oo-ui-buttonElement a' ); }

	get filterSavedNotice() { return $( '.successbox' ); }

	get savedFilterID() {
		const succesMsg = this.filterSavedNotice.getHTML(),
			regexp = /\/history\/(\d+)\//;
		return regexp.exec( succesMsg )[ 1 ];
	}

	get savedFilterHistoryID() {
		const succesMsg = this.filterSavedNotice.getHTML(),
			regexp = /\/diff\/prev\/(\d+)/;
		return regexp.exec( succesMsg )[ 1 ];
	}

	open() {
		super.openTitle( 'Special:AbuseFilter' );
	}
}
module.exports = new ViewListPage();
