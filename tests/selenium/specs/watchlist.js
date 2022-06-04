'use strict';

const assert = require( 'assert' );
const Api = require( 'wdio-mediawiki/Api' );
const WatchlistPage = require( '../pageobjects/watchlist.page' );
const WatchablePage = require( '../pageobjects/watchable.page' );
const LoginPage = require( 'wdio-mediawiki/LoginPage' );
const Util = require( 'wdio-mediawiki/Util' );

describe( 'Special:Watchlist', function () {
	let bot;

	before( async () => {
		// Default bot is the admin that we also use for viewing via LoginPage.loginAdmin()
		bot = await Api.bot();
	} );

	beforeEach( function () {
		LoginPage.loginAdmin();
	} );

	it( 'should show page with new edit', async function () {
		const title = Util.getTestString( 'Title-' );

		// Don't try to run wikitext-specific tests if the test namespace isn't wikitext by default.
		if ( await Util.isTargetNotWikitext( title ) ) {
			this.skip();
		}

		// create
		await bot.edit( title, Util.getTestString() );

		await WatchablePage.watch( title );

		// edit
		await bot.edit( title, Util.getTestString() );

		await WatchlistPage.open();

		// We are viewing Special:Watchlist with the same account that made the edit,
		// but by default Special:Watchlist includes both seen and unseen changes, so
		// it'll show up anyway. The title we just edited will be first because the edit
		// was the most recent.
		assert.strictEqual( await WatchlistPage.titles[ 0 ].getText(), title );
	} );

} );
