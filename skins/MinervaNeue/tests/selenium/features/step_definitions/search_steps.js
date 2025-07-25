'use strict';

const { ArticlePage } = require( '../support/world.js' );
const ArticlePageWithOverlay = require( '../support/pages/article_page_with_overlay' );

const iClickTheSearchIcon = async () => {
	await ArticlePage.search_icon_element.waitForDisplayed();
	await ArticlePage.search_icon_element.click();
};

const iTypeIntoTheSearchBox = async ( term ) => {
	const input = await ArticlePageWithOverlay.overlay_element
		.$( 'input' );
	await input.waitForExist();
	await input.setValue( term );
};

const iSeeSearchResults = async () => {
	await ArticlePageWithOverlay.overlay_element
		.$( '.page-list' ).waitForExist( 5000 );
};

const iClickASearchWatchstar = async () => {
	await iSeeSearchResults();
	const watchThisArticle = ArticlePageWithOverlay.overlay_element
		.$( '.watch-this-article' );
	await watchThisArticle.waitForExist( 5000 );
	await watchThisArticle.click();
};

module.exports = {
	iClickTheSearchIcon,
	iTypeIntoTheSearchBox,
	iClickASearchWatchstar
};
