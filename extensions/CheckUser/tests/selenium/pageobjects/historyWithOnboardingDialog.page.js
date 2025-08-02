'use strict';

// This is a modified copy of the tests/selenium/pageobjects/history.page.js
// file from MediaWiki core.

const Page = require( 'wdio-mediawiki/Page' );

class HistoryPageWithOnboardingDialog extends Page {
	get tempAccountsOnboardingDialog() {
		return $( '.ext-checkuser-temp-account-onboarding-dialog' );
	}

	get tempAccountsOnboardingDialogNextButton() {
		return $( '.ext-checkuser-temp-account-onboarding-dialog__footer__navigation--next' );
	}

	get tempAccountsOnboardingDialogIPInfoPreferenceCheckbox() {
		return $( '.ext-checkuser-temp-account-onboarding-dialog-ip-info-preference input[type="checkbox"]' );
	}

	get tempAccountsOnboardingDialogIPInfoPreferenceCheckboxLabel() {
		return $( '.ext-checkuser-temp-account-onboarding-dialog-ip-info-preference label' );
	}

	get tempAccountsOnboardingDialogIPInfoSavePreferenceButton() {
		return $( '.ext-checkuser-temp-account-onboarding-dialog-ip-info-save-preference button' );
	}

	async open( title ) {
		return super.openTitle( title, { action: 'history' } );
	}
}

module.exports = new HistoryPageWithOnboardingDialog();
