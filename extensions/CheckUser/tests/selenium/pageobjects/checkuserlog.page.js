'use strict';

const Page = require( 'wdio-mediawiki/Page' );

class CheckUserLogPage extends Page {
	get hasPermissionErrors() {
		return $( '.permissions-errors' );
	}

	get targetInput() {
		return $( '#mw-target-user-or-ip input' );
	}

	get initiatorInput() {
		return $( 'input[name=cuInitiator]' );
	}

	get startDateSelector() {
		return $( '#mw-date-start' );
	}

	get endDateSelector() {
		return $( '#mw-date-end' );
	}

	get search() {
		return $( '.mw-htmlform-submit button[value="Search"]' );
	}

	async open() {
		await super.openTitle( 'Special:CheckUserLog' );
	}
}

module.exports = new CheckUserLogPage();
