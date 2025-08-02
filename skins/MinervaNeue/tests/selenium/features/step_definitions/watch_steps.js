'use strict';

const { ArticlePage } = require( '../support/world.js' );
const Util = require( 'wdio-mediawiki/Util' );

const theWatchstarShouldBeSelected = async () => {
	await ArticlePage.watched_element.waitForExist();
	const watchstar = await ArticlePage.watched_element;
	await expect( watchstar ).toBeDisplayed();
};

const iClickTheWatchstar = async () => {
	await Util.waitForModuleState( 'skins.minerva.scripts' );
	await ArticlePage.watch_element.waitForExist();
	await ArticlePage.watch_element.click();
};

const iClickTheUnwatchStar = async () => {
	await Util.waitForModuleState( 'skins.minerva.scripts' );
	await ArticlePage.watched_element.waitForExist();
	await ArticlePage.watched_element.click();
};

module.exports = {
	theWatchstarShouldBeSelected,
	iClickTheWatchstar, iClickTheUnwatchStar };
