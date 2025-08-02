'use strict';

const Api = require( 'wdio-mediawiki/Api' ),
	LoginAsCheckUser = require( '../checkuserlogin.js' ),
	HistoryPage = require( '../pageobjects/historyWithOnboardingDialog.page.js' );

async function getCheckUserAccountApiBot() {
	const checkUserAccountDetails = LoginAsCheckUser.getCheckUserAccountDetails();
	return Api.bot( checkUserAccountDetails.username, checkUserAccountDetails.password );
}

async function checkExtensionIsInstalled( extensionName ) {
	const bot = await Api.bot();
	const siteInfoApiResponse = await bot.request( {
		action: 'query',
		meta: 'siteinfo',
		siprop: 'extensions',
		formatversion: 2
	} );
	const installedExtensions = siteInfoApiResponse.query.extensions.map( ( item ) => item.name );
	return installedExtensions.includes( extensionName );
}

describe( 'Temporary Accounts Onboarding Dialog', () => {
	before( async function () {
		// Skip the test if IPInfo is not installed.
		const isIPInfoInstalled = await checkExtensionIsInstalled( 'IPInfo' );
		if ( !isIPInfoInstalled ) {
			this.skip();
		}
		await LoginAsCheckUser.loginAsCheckUser();
		const bot = await getCheckUserAccountApiBot();
		// Mark the onboarding dialog as not seen, in case it is marked as seen by default.
		await bot.request( {
			action: 'options',
			optionname: 'checkuser-temporary-accounts-onboarding-dialog-seen',
			optionvalue: 0,
			token: bot.editToken,
			formatversion: 2
		} );
	} );
	it( 'Verify onboarding dialog displays correctly and IPInfo preference works', async () => {
		// Open the history page and verify that the dialog is shown.
		await HistoryPage.open( 'Main Page' );
		await browser.waitUntil(
			async () => HistoryPage.tempAccountsOnboardingDialog.isExisting(),
			{ timeout: 10 * 1000, timeoutMsg: 'Dialog did not open in time', interval: 100 }
		);
		await expect( await HistoryPage.tempAccountsOnboardingDialog ).toExist();
		await expect( await HistoryPage.tempAccountsOnboardingDialogNextButton ).toExist();

		// Navigate to the next step to see the IPInfo step.
		await HistoryPage.tempAccountsOnboardingDialogNextButton.click();
		await expect(
			await HistoryPage.tempAccountsOnboardingDialogIPInfoPreferenceCheckbox
		).toExist();
		await expect(
			await HistoryPage.tempAccountsOnboardingDialogIPInfoPreferenceCheckboxLabel
		).toExist();

		// Check the IPInfo preference and press the "Save preference" button
		await HistoryPage.tempAccountsOnboardingDialogIPInfoPreferenceCheckboxLabel.click();
		await expect(
			await HistoryPage.tempAccountsOnboardingDialogIPInfoPreferenceCheckbox
		).toBeChecked();
		await HistoryPage.tempAccountsOnboardingDialogIPInfoSavePreferenceButton.click();

		// Wait until the meta=userinfo API has the IPInfo preference correctly updated
		const bot = await getCheckUserAccountApiBot();
		await browser.waitUntil( async () => {
			// Wait for the edit to be applied and the API to return that the edit
			// has been made. This is needed for wikis which have a multi-DB setup
			// and the edit may take a little bit to be replicated.
			const userInfoApiResponse = await bot.request( {
				action: 'query',
				meta: 'userinfo',
				uiprop: 'options',
				formatversion: 2
			} );
			return !!userInfoApiResponse.query.userinfo.options[ 'ipinfo-use-agreement' ];
		}, { timeout: 1, timeoutMsg: 'IPInfo preference was not set', interval: 100 } );
	} );
	after( async () => {
		const bot = await getCheckUserAccountApiBot();
		// Clear the IPInfo preference from the test user, so that any
		// retries can have the preference as the initial value before proceeding.
		await bot.request( {
			action: 'options',
			optionname: 'ipinfo-use-agreement',
			token: bot.editToken,
			formatversion: 2
		} );
		// Mark the onboarding dialog as not seen, in case it was marked as seen during the test
		await bot.request( {
			action: 'options',
			optionname: 'checkuser-temporary-accounts-onboarding-dialog-seen',
			optionvalue: 0,
			token: bot.editToken,
			formatversion: 2
		} );
	} );
} );
