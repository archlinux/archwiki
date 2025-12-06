import { ArticlePage } from '../support/world.js';
import { waitForModuleState } from 'wdio-mediawiki/Util.js';

const theWatchstarShouldBeSelected = async () => {
	await ArticlePage.watched_element.waitForExist();
	const watchstar = await ArticlePage.watched_element;
	await expect( watchstar ).toBeDisplayed();
};

const iClickTheWatchstar = async () => {
	await waitForModuleState( 'skins.minerva.scripts' );
	await ArticlePage.watch_element.waitForExist();
	await ArticlePage.watch_element.click();
};

const iClickTheUnwatchStar = async () => {
	await waitForModuleState( 'skins.minerva.scripts' );
	await ArticlePage.watched_element.waitForExist();
	await ArticlePage.watched_element.click();
};

export {
	theWatchstarShouldBeSelected,
	iClickTheWatchstar, iClickTheUnwatchStar };
