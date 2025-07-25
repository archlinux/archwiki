'use strict';

const LoginAsCheckUser = require( '../checkuserlogin' ),
	CheckUserPage = require( '../pageobjects/checkuser.page' ),
	EditPage = require( '../pageobjects/edit.page' ),
	CreateAccountPage = require( 'wdio-mediawiki/CreateAccountPage' ),
	LogoutPage = require( '../pageobjects/logout.page' ),
	MWBot = require( 'mwbot' ),
	Util = require( 'wdio-mediawiki/Util' ),
	childProcess = require( 'child_process' );

const supportedBrowsers = [ 'chrome', 'chromium', 'msedge' ];

function checkIfBrowserSupportsClientHints( browserName ) {
	return supportedBrowsers.includes( browserName );
}

let tempAccountUsername = '';
const createdAccountUsername = Util.getTestString( 'ClientHintsAccountCreationTest' );

describe( 'Client Hints', () => {
	before( async () => {
		// Skip the tests if we are using a browser which does not support client hints,
		// because the tests need data to be sent to the API to e2e test.
		if ( !checkIfBrowserSupportsClientHints( driver.requestedCapabilities.browserName ) ) {
			return;
		}
		// Edit the page such that a temporary account is created.
		const temporaryAccountEditTitle = Util.getTestString( 'ClientHintsTempAccountEditTest' );
		await EditPage.edit(
			temporaryAccountEditTitle,
			'testing1234-temp-account-edit',
			'test-temp-account-edit-to-test-client-hints'
		);
		// Wait until the API responds that the page we just edited exists and then get
		// the temporary account name used to edit the page for use in the tests below.
		let lastRevisionResponse;
		const bot = new MWBot( {
			apiUrl: `${ browser.config.baseUrl }/api.php`
		} );
		await browser.waitUntil( async () => {
			// Wait for the edit to be applied and the API to return that the edit
			// has been made. This is needed for wikis which have a multi-DB setup
			// and the edit may take a little bit to be replicated.
			lastRevisionResponse = await bot.request( {
				action: 'query',
				prop: 'revisions',
				titles: temporaryAccountEditTitle,
				rvprop: 'user',
				formatversion: 2
			} );
			return !( lastRevisionResponse.query.pages[ 0 ].missing );
		}, { timeout: 10 * 1000, timeoutMsg: 'Revision was not found in time', interval: 100 } );
		tempAccountUsername = lastRevisionResponse.query.pages[ 0 ].revisions[ 0 ].user;
		// Create an account for later use in the tests.
		await CreateAccountPage.createAccount( createdAccountUsername, Util.getTestString() );
		// Wait until the account has definitely been created before trying to logout
		// from the account. Avoids these tests being flaky.
		await browser.waitUntil(
			async () => $( `h1*=${ createdAccountUsername }` ).isExisting(),
			{ timeout: 10 * 1000, timeoutMsg: 'Account was not created', interval: 100 }
		);
		// Logout of this newly created account
		await LogoutPage.logoutViaMenuItem();
		// Wait until the user has been actually logged out before proceeding to the
		// next step. Avoids these tests being flaky.
		await browser.waitUntil(
			async () => $( 'div*=You are now logged out' ).isExisting(),
			{ timeout: 10 * 1000, timeoutMsg: 'User was not logged out', interval: 100 }
		);
		// Then edit the page using a normal account
		await LoginAsCheckUser.loginAsCheckUser();
		const namedAccountEditTitle = Util.getTestString( 'ClientHintsAccountEditTest' );
		await EditPage.edit( namedAccountEditTitle, 'testing1234', 'test-edit-to-test-client-hints' );
		// Wait for the postEdit hook to be run, so that we have time
		// for Client Hints data to be posted to the API. The postEdit
		// hook is run when the 'postedit' notification is shown.
		await browser.waitUntil(
			async () => $( '.mw-notification.postedit' ).isExisting(),
			{ timeout: 10 * 1000, timeoutMsg: 'postEdit hook was not run in time', interval: 100 }
		);
		// Run jobs in case the Client Hints data is being inserted via a job.
		// We can't use RunJobs from wdio-mediawiki, because we need to run the jobs
		// synchronously to ensure that the Client Hints data jobs have all been run.
		childProcess.spawnSync(
			'php',
			[ 'maintenance/run.php', 'runJobs' ]
		);
	} );
	it( 'Verify edit sends Client Hints data', async () => {
		// Skip the tests if we are using a browser which does not support client hints,
		// because the tests need data to be sent to the API to e2e test.
		if ( !checkIfBrowserSupportsClientHints( driver.requestedCapabilities.browserName ) ) {
			return;
		}
		await CheckUserPage.open();
		await CheckUserPage.getActionsCheckTypeRadio.click();
		const checkUserUsername = LoginAsCheckUser.getCheckUserAccountDetails().username;
		await CheckUserPage.checkTarget.setValue( checkUserUsername );
		await CheckUserPage.checkReasonInput.setValue( 'Selenium browser testing' );
		await CheckUserPage.submit.click();
		await expect( await CheckUserPage.getActionsResults ).toExist();
		// Check that Client Hints data exists for the edit, by checking if the Client Hints
		// element class is present for the edit and the span contains content.
		const relevantResultLine = await CheckUserPage.getActionsResults.$( 'li*=test-edit-to-test-client-hints' );
		await expect( relevantResultLine ).toExist();
		const clientHints = await relevantResultLine.$( '.mw-checkuser-client-hints' ).getText();
		await expect( clientHints ).not.toBeFalsy();
	} );

	it( 'stores client hints data on successful logins', async () => {
		if ( !checkIfBrowserSupportsClientHints( driver.requestedCapabilities.browserName ) ) {
			return;
		}
		await CheckUserPage.open();
		await CheckUserPage.getActionsCheckTypeRadio.click();
		const checkUserUsername = LoginAsCheckUser.getCheckUserAccountDetails().username;
		await CheckUserPage.checkTarget.setValue( checkUserUsername );
		await CheckUserPage.checkReasonInput.setValue( 'Selenium browser testing' );
		await CheckUserPage.submit.click();
		await expect( await CheckUserPage.getActionsResults ).toExist();
		// Check that Client Hints data exists for the login, by checking if the Client Hints
		// element class is present for the log entry and the span contains content.
		const relevantResultLine = await CheckUserPage.getActionsResults.$( 'li*=Successfully logged in' );
		await expect( relevantResultLine ).toExist();
		const clientHints = await relevantResultLine.$( '.mw-checkuser-client-hints' ).getText();
		await expect( clientHints ).not.toBeFalsy();
	} );

	it( 'stores client hints data on account creation', async () => {
		if ( !checkIfBrowserSupportsClientHints( driver.requestedCapabilities.browserName ) ) {
			return;
		}
		await CheckUserPage.open();
		await CheckUserPage.getActionsCheckTypeRadio.click();
		await CheckUserPage.checkTarget.setValue( createdAccountUsername );
		await CheckUserPage.checkReasonInput.setValue( 'Selenium browser testing' );
		await CheckUserPage.submit.click();
		await expect( await CheckUserPage.getActionsResults ).toExist();
		// Check that Client Hints data exists for the login, by checking if the Client Hints
		// element class is present for the log entry and the span contains content.
		const relevantResultLine = await CheckUserPage.getActionsResults.$( `li*=${ createdAccountUsername } was created` );
		await expect( relevantResultLine ).toExist();
		const clientHints = await relevantResultLine.$( '.mw-checkuser-client-hints' ).getText();
		await expect( clientHints ).not.toBeFalsy();
	} );

	// Skipped on 2025-02-20 in 1121412 because of T385449
	it.skip( 'stores client hints data on logout', async () => {
		if ( !checkIfBrowserSupportsClientHints( driver.requestedCapabilities.browserName ) ) {
			return;
		}
		await CheckUserPage.open();
		await CheckUserPage.getActionsCheckTypeRadio.click();
		await CheckUserPage.checkTarget.setValue( createdAccountUsername );
		await CheckUserPage.checkReasonInput.setValue( 'Selenium browser testing' );
		await CheckUserPage.submit.click();
		await expect( await CheckUserPage.getActionsResults ).toExist();
		// Check that Client Hints data exists for the logout, by checking if the Client Hints
		// element class is present for the log entry and the span contains content.
		const relevantResultLine = await CheckUserPage.getActionsResults.$( 'li*=Successfully logged out' );
		await expect( relevantResultLine ).toExist();
		const clientHints = await relevantResultLine.$( '.mw-checkuser-client-hints' ).getText();
		await expect( clientHints ).not.toBeFalsy();
	} );

	it( 'stores client hints data on temporary account auto-creation', async () => {
		if ( !checkIfBrowserSupportsClientHints( driver.requestedCapabilities.browserName ) ) {
			return;
		}
		await CheckUserPage.open();
		await CheckUserPage.getActionsCheckTypeRadio.click();
		await CheckUserPage.checkTarget.setValue( tempAccountUsername );
		await CheckUserPage.checkReasonInput.setValue( 'Selenium browser testing' );
		await CheckUserPage.submit.click();
		await expect( await CheckUserPage.getActionsResults ).toExist();
		// Check that the temporary account auto-creation event appears and that it should be attached to the
		// public log event in Special:Log (wgNewUserLog is true by default and in CI).
		const relevantResultLine = await CheckUserPage.getActionsResults.$( 'li*=was created automatically' );
		await expect( relevantResultLine ).toExist();
		// Expect that Client Hints exist for the auto-creation event
		const clientHints = await relevantResultLine.$( '.mw-checkuser-client-hints' ).getText();
		await expect( clientHints ).not.toBeFalsy();
	} );
} );
