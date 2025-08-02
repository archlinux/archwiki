'use strict';

const Page = require( 'wdio-mediawiki/Page' );

class InvestigatePage extends Page {
	get hasPermissionErrors() {
		return $( '.permissions-errors' );
	}

	get targetsInput() {
		return $( '#targets' );
	}

	get durationSelector() {
		return $( '#investigate-duration' );
	}

	get reasonInput() {
		return $( '#investigate-reason' );
	}

	async open() {
		await super.openTitle( 'Special:Investigate' );
	}
}

module.exports = new InvestigatePage();
