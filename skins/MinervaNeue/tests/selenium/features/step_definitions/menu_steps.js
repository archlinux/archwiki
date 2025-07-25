'use strict';

const { ArticlePage } = require( '../support/world.js' );

const iSeeALinkToAboutPage = async () => {
	await expect( ArticlePage.menu_element.$( '*=About' ) ).toBeDisplayed();
};

const iClickOnTheMainNavigationButton = async () => {
	await ArticlePage.menu_button_element.waitForDisplayed();
	await ArticlePage.menu_button_element.click();
};

const iShouldSeeAUserPageLinkInMenu = async () => {
	await ArticlePage.menu_element.$( '.menu__item--user' );
};

const iShouldSeeLogoutLinkInMenu = async () => {
	await ArticlePage.menu_element.$( '.menu__item--logout' );
};

// TODO: When the assertion is reversed, nothing fails
// TODO: The assertion is used in tests/selenium/specs/mainmenu_loggedin.js
const iShouldSeeALinkInMenu = async ( text ) => {
	await expect( ArticlePage.menu_element.$( `span=${ text }` ) ).toBeDisplayed(
		{ message: `Link to ${ text } is visible.` }
	);
};

const iShouldSeeALinkToDisclaimer = async () => {
	await ArticlePage.menu_element.$( 'span=Disclaimers' ).waitForDisplayed();
	await expect( ArticlePage.menu_element.$( 'span=Disclaimers' ) ).toBeDisplayed();
};

module.exports = {
	iClickOnTheMainNavigationButton,
	iSeeALinkToAboutPage, iShouldSeeAUserPageLinkInMenu,
	iShouldSeeLogoutLinkInMenu,
	iShouldSeeALinkInMenu, iShouldSeeALinkToDisclaimer
};
