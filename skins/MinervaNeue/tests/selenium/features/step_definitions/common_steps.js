'use strict';

const MWBot = require( 'mwbot' ),
	Api = require( 'wdio-mediawiki/Api' ),
	ArticlePageWithOverlay = require( '../support/pages/article_page_with_overlay' ),
	Util = require( 'wdio-mediawiki/Util' ),
	{ ArticlePage, UserLoginPage } = require( '../support/world.js' );

const createPages = async ( pages ) => {
	const summary = 'edit by selenium test';
	const bot = new MWBot();
	await bot.loginGetEditToken( {
		username: browser.options.username,
		password: browser.options.password,
		apiUrl: `${ browser.options.baseUrl }/api.php`
	} );

	try {
		await bot.batch(
			pages.map( ( page ) => [ 'create' ].concat( page ).concat( [ summary ] ) )
		);
	} catch ( err ) {
		if ( err.code === 'articleexists' ) {
			return;
		}
		throw err;
	}
};

const createPage = async ( title, wikitext ) => {
	const bot = await Api.bot();
	await bot.edit( title, wikitext );
};

const iAmUsingTheMobileSite = async () => {
	await ArticlePage.setMobileMode();
};

const iAmInBetaMode = async () => {
	await ArticlePage.setBetaMode();
};

const iAmOnPage = async ( article ) => {
	await ArticlePage.open( article );
	// Make sure the article opened and JS loaded.
	await Util.waitForModuleState( 'skins.minerva.scripts' );
};

const iAmLoggedIn = async () => {
	await UserLoginPage.open();
	await UserLoginPage.loginAdmin();
	// The order here is important as logging in on mobile on beta cluster
	// to workaround https://phabricator.wikimedia.org/T389889 where login
	// sometimes results in `Error: Cannot submit login form`
	// (No active login attempt is in progress for your session)
	await iAmUsingTheMobileSite();
	// A new navigation is needed to make sure we are in mobile and logged in
	await ArticlePage.open( 'Main_Page' );
	await expect( ArticlePage.is_authenticated_element ).toExist();
};

const iAmLoggedIntoTheMobileWebsite = async () => {
	await iAmLoggedIn();
};

const pageExists = async ( title ) => {
	await createPage( title, 'Page created by Selenium browser test.' );
};

const pageExistsWithText = async ( title, text ) => {
	await createPage( title, text );
};

const iAmOnAPageThatDoesNotExist = () => iAmOnPage( `NewPage ${ new Date() }` );

const iShouldSeeAToastNotification = async () => {
	await ArticlePage.notification_element.waitForDisplayed();
};

const iShouldSeeAToastNotificationWithMessage = async ( msg ) => {
	await iShouldSeeAToastNotification();
	const notificationBody = await ArticlePage.notification_element.$( '.mw-notification-content' );
	await expect( notificationBody ).toHaveTextContaining( msg );
};

const iClickTheBrowserBackButton = async () => {
	await browser.back();
};

const iClickTheOverlayCloseButton = async () => {
	await ArticlePageWithOverlay.overlay_close_element.waitForDisplayed();
	await ArticlePageWithOverlay.overlay_close_element.click();
};

const iAmUsingMobileScreenResolution = async () => {
	await browser.setWindowSize( 320, 480 );
};

module.exports = {
	iAmUsingMobileScreenResolution,
	iClickTheOverlayCloseButton,
	iClickTheBrowserBackButton,
	createPage, createPages,
	pageExistsWithText,
	pageExists, iAmOnAPageThatDoesNotExist, iShouldSeeAToastNotification,
	iShouldSeeAToastNotificationWithMessage,
	iAmLoggedIntoTheMobileWebsite,
	iAmUsingTheMobileSite,
	iAmLoggedIn, iAmOnPage, iAmInBetaMode
};
