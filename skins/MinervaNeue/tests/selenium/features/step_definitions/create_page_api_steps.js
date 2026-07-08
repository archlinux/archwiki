import { ArticlePage } from '../support/world.js';
import RunJobs from 'wdio-mediawiki/RunJobs.js';
import { createApiClient } from 'wdio-mediawiki/Api.js';
import Page from 'wdio-mediawiki/Page.js';
import {
	iAmOnPage,
	createPage } from './common_steps.js';

const iAmInAWikiThatHasCategories = async ( title ) => {
	const msg = 'This page is used by Selenium to test category related features.',
		wikitext = `
            ${ msg }

            [[Category:Test category]]
            [[Category:Selenium artifacts]]
            [[Category:Selenium hidden category]]
        `;

	const api = await createApiClient();
	await api.edit( 'Category:Selenium artifacts', msg );
	await api.edit( 'Category:Test category', msg );
	await api.edit( 'Category:Selenium hidden category', '__HIDDENCAT__' );

	await api.edit( title, wikitext );

	// The category overlay uses the category API
	// which will only return results if the job queue has completed.
	// Run before continuing!
	RunJobs.run();
};

const iAmOnAPageThatHasTheFollowingEdits = async function ( table ) {
	const randomString = Math.random().toString( 36 ).slice( 7 ),
		pageTitle = `Selenium_diff_test_${ randomString }`,
		edits = table.rawTable.map( ( row ) => [ pageTitle, row[ 0 ] ] );

	const api = await createApiClient();
	for ( const [ title, text ] of edits ) {
		await api.edit( title, text );
	}

	RunJobs.run();
	await ArticlePage.open( pageTitle );
};

const iGoToAPageThatHasLanguages = async () => {
	const wikitext = `This page is used by Selenium to test language related features.

	[[es:Selenium language test page]]
`;

	await createPage( 'Selenium language test page', wikitext );
	await iAmOnPage( 'Selenium language test page' );
};

const watch = async ( title ) => {
	// Ideally this would use the API but mwbot / Selenium's API can't do this right now
	// So we run the non-js workflow.
	const page = new Page();
	await page.openTitle( title, { action: 'watch' } );
	await $( '#mw-content-text button[type="submit"]' ).waitForDisplayed();
	await $( '#mw-content-text button[type="submit"]' ).click();
	await page.openTitle( title );
};

const iAmViewingAWatchedPage = async () => {
	const title = `I am on the "Selenium mobile watched page test ${ Date.now() }`;
	await createPage( title, 'watch test' );
	await watch( title );
	// navigate away from page
	await iAmOnPage( 'Main Page' );
	// and back to page
	await iAmOnPage( title );
};

const iAmViewingAnUnwatchedPage = async () => {
	// new pages are watchable but unwatched by default
	const title = 'I am on the "Selenium mobile unwatched test ' + new Date();
	await iAmOnPage( title );
};

const iAmOnATalkPageWithNoTalkTopics = async () => {
	const title = `Selenium talk test ${ new Date() }`;

	await createPage( title, 'Selenium' );
	await iAmOnPage( `Talk:${ title }` );
};

export {
	iAmOnAPageThatHasTheFollowingEdits,
	iAmOnATalkPageWithNoTalkTopics,
	iAmViewingAWatchedPage,
	iAmViewingAnUnwatchedPage,
	iAmInAWikiThatHasCategories,
	iGoToAPageThatHasLanguages
};
