'use strict';

const { ArticlePage } = require( '../support/world' ),
	RunJobs = require( 'wdio-mediawiki/RunJobs' ),
	Api = require( 'wdio-mediawiki/Api' ),
	Page = require( 'wdio-mediawiki/Page' ),
	MWBot = require( 'mwbot' ),
	{
		iAmOnPage,
		createPages,
		createPage
	} = require( './common_steps' );

const iAmInAWikiThatHasCategories = async ( title ) => {
	const msg = 'This page is used by Selenium to test category related features.',
		wikitext = `
            ${ msg }

            [[Category:Test category]]
            [[Category:Selenium artifacts]]
            [[Category:Selenium hidden category]]
        `;

	await createPages( [
		[ 'create', 'Category:Selenium artifacts', msg ],
		[ 'create', 'Category:Test category', msg ],
		[ 'create', 'Category:Selenium hidden category', '__HIDDENCAT__' ]
	] );

	const bot = await Api.bot();
	await bot.edit( title, wikitext );

	// The category overlay uses the category API
	// which will only return results if the job queue has completed.
	// Run before continuing!
	RunJobs.run();
};

const iAmOnAPageThatHasTheFollowingEdits = async function ( table ) {
	const randomString = Math.random().toString( 36 ).slice( 7 ),
		pageTitle = `Selenium_diff_test_${ randomString }`,
		edits = table.rawTable.map( ( row, i ) => [ i === 0 ? 'create' : 'edit', pageTitle, row[ 0 ] ] );

	const bot = new MWBot();
	await bot.loginGetEditToken( {
		username: browser.options.username,
		password: browser.options.password,
		apiUrl: `${ browser.options.baseUrl }/api.php`
	} );

	await bot.batch( edits );

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

module.exports = {
	iAmOnAPageThatHasTheFollowingEdits,
	iAmOnATalkPageWithNoTalkTopics,
	iAmViewingAWatchedPage,
	iAmViewingAnUnwatchedPage,
	iAmInAWikiThatHasCategories,
	iGoToAPageThatHasLanguages
};
