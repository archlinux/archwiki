'use strict';

const { ArticlePage } = require( './../support/world' );

const iClickOnTheMask = async () => {
	await ArticlePage.drawer_mask_element.waitForDisplayed();
	await ArticlePage.drawer_mask_element.click();
};

const iShouldSeeNotTheReferenceDrawer = async () => {
	await browser.waitUntil( async () => !( await ArticlePage.drawer_element.isDisplayed() ) );
};

const iClickOnAReference = async () => {
	await ArticlePage.reference_element.waitForDisplayed();
	await ArticlePage.reference_element.click();
};

const iClickOnANestedReference = async () => {
	await ArticlePage.drawer_reference_element.waitForClickable();
	// Wait for a short while to allow event listeners to be registered on the nested reference.
	// eslint-disable-next-line wdio/no-pause
	await browser.pause( 100 );
	await ArticlePage.drawer_reference_element.click();
};

const iShouldSeeDrawerWithText = async ( text ) => {
	await ArticlePage.drawer_element.waitForDisplayed();
	await browser.waitUntil( async () => ( await ArticlePage.drawer_element.getText() ).includes( text ) );
};

module.exports = {
	iClickOnAReference,
	iClickOnTheMask,
	iShouldSeeNotTheReferenceDrawer,
	iClickOnANestedReference,
	iShouldSeeDrawerWithText
};
