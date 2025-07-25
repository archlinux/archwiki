'use strict';

const initOnHook = require( '../../../modules/ext.checkUser.tempAccounts/initOnHook.js' );
const { makeTempUserLink, waitUntilElementCount } = require( './utils.js' );
const Utils = require( '../../../modules/ext.checkUser.tempAccounts/ipRevealUtils.js' );

const originalSetAutoRevealStatus = Utils.setAutoRevealStatus;
let server;

QUnit.module( 'ext.checkUser.tempAccounts.initOnHook', QUnit.newMwEnvironment( {
	beforeEach: function () {
		this.server = this.sandbox.useFakeServer();
		this.server.respondImmediately = true;
		server = this.server;
	},
	afterEach: function () {
		server.restore();
		Utils.setAutoRevealStatus = originalSetAutoRevealStatus;
	},
	config: {
		// Prevent initOnLoad.js from running automatically and then
		// calling enableMultiReveal on the document.
		wgCanonicalSpecialPageName: 'CheckUser',
		wgCheckUserTemporaryAccountMaxAge: 1500
	}
} ) );

QUnit.test( 'Test initOnHook when there are no temporary account user links on load', ( assert ) => {
	// eslint-disable-next-line no-jquery/no-global-selector
	const $qunitFixture = $( '#qunit-fixture' );
	assert.strictEqual(
		$qunitFixture.find( '.ext-checkuser-tempaccount-reveal-ip-button' ).length,
		0,
		'No IP reveal links before initOnHook call'
	);
	// Call initOnHook with the QUnit fixture as the document root
	initOnHook( $qunitFixture );
	assert.strictEqual(
		$qunitFixture.find( '.ext-checkuser-tempaccount-reveal-ip-button' ).length,
		0,
		'No IP reveal links after initOnHook call'
	);
} );

QUnit.test( 'Test initOnHook when temporary account links added after load', ( assert ) => {
	// eslint-disable-next-line no-jquery/no-global-selector
	const $qunitFixture = $( '#qunit-fixture' );
	// Call initOnHook with the QUnit fixture as the document root
	initOnHook( $qunitFixture );
	// Now add the temporary account links to the page and fire wikipage.content
	// on the newly added content.
	const $revisionLine = $( '<div>' ).attr( 'data-mw-revid', 1 );
	$qunitFixture.append( $revisionLine );
	// Add the temporary account username link for the revision line
	const $tempAccountUserLink = makeTempUserLink( '~12' );
	$revisionLine.append( $tempAccountUserLink );
	// Fire wikipage.content on the newly added content
	mw.hook( 'wikipage.content' ).fire( $qunitFixture );
	// Verify that a "Show IP" button has been added next to the newly added temporary account link.
	assert.strictEqual(
		// eslint-disable-next-line no-jquery/no-class-state
		$tempAccountUserLink.next().hasClass( 'ext-checkuser-tempaccount-reveal-ip-button' ), true,
		'Button is after temp user link added with wikipage.content hook fired'
	);
} );

/**
 * Sets up the document for a test where some temporary account user links are
 * automatically revealed, and then fires the "wikipage.content" hook on the
 * newly added content.
 *
 * @return {{
 * temporaryAccountUserLinksThatAreAutomaticallyRevealed: jQuery[],
 * temporaryAccountUserLinks: jQuery[]
 * }} The list of jQuery elements for the temporary account user links that were
 *    added to the document, split into two by those which are automatically revealed
 *    and those which are not.
 */
function setUpDocumentForTest() {
	// eslint-disable-next-line no-jquery/no-global-selector
	const $qunitFixture = $( '#qunit-fixture' );
	const temporaryAccountUserLinks = [];
	const temporaryAccountUserLinksThatAreAutomaticallyRevealed = [];
	// Add some testing revision lines
	const revisionLines = { 1: '~1', 2: '~1', 3: '~2' };
	Object.entries( revisionLines ).forEach( ( [ revId, username ] ) => {
		const $revisionLine = $( '<div>' ).attr( 'data-mw-revid', revId );
		$qunitFixture.append( $revisionLine );
		// Add the temporary account username link for the revision line
		const $tempAccountUserLink = makeTempUserLink( username );
		$revisionLine.append( $tempAccountUserLink );
		if ( Utils.getRevealedStatus( username ) ) {
			temporaryAccountUserLinksThatAreAutomaticallyRevealed.push( $tempAccountUserLink );
		} else {
			temporaryAccountUserLinks.push( $tempAccountUserLink );
		}
	} );
	// Fire wikipage.content on the QUnit test fixture
	mw.hook( 'wikipage.content' ).fire( $qunitFixture );
	return { temporaryAccountUserLinks, temporaryAccountUserLinksThatAreAutomaticallyRevealed };
}

QUnit.test( 'Test initOnHook with recently revealed temp user links added after load', ( assert ) => {
	// Prevent the test being very slow if the wait for condition fails.
	assert.timeout( 1000 );
	// This assumes that the API request is for the revision API, however, this should occur
	// because only revision related temporary account "Show IP" buttons should exist in
	// the page.
	server.respond( ( request ) => {
		const response = request.url.endsWith( '/checkuser/v0/batch-temporaryaccount' ) ?
			{ '~1': { revIps: { 1: '127.0.0.1', 2: '127.0.0.1' }, logIps: null, lastUsedIp: null } } :
			{ ips: { 1: '127.0.0.1', 2: '127.0.0.1' } };
		request.respond( 200, { 'Content-Type': 'application/json' }, JSON.stringify( response ) );
	} );
	// eslint-disable-next-line no-jquery/no-global-selector
	const $qunitFixture = $( '#qunit-fixture' );
	// Call initOnHook with the QUnit fixture as the document root
	initOnHook( $qunitFixture );
	// Mark that the ~1 temporary account username has been revealed previously, and so
	// should be automatically revealed when the page loads.
	Utils.setRevealedStatus( '~1' );
	// Add the new content for the test.
	const {
		temporaryAccountUserLinks,
		temporaryAccountUserLinksThatAreAutomaticallyRevealed
	} = setUpDocumentForTest();
	// Wait until there are three IP reveal buttons
	const done = assert.async();
	waitUntilElementCount(
		'.ext-checkuser-tempaccount-reveal-ip',
		temporaryAccountUserLinksThatAreAutomaticallyRevealed.length
	).then( () => {
		// Verify that the Show IP button was added for all temporary user links that should not be
		// automatically revealed on load.
		temporaryAccountUserLinks.forEach( ( $element ) => {
			assert.strictEqual(
				// eslint-disable-next-line no-jquery/no-class-state
				$element.next().hasClass( 'ext-checkuser-tempaccount-reveal-ip-button' ), true,
				'Button is after temp user link for non-recently revealed temporary account'
			);
		} );
		// Verify that the relevant IP is shown for the temporary account user links that should be
		// automatically revealed on load.
		temporaryAccountUserLinksThatAreAutomaticallyRevealed.forEach( ( $element ) => {
			assert.strictEqual(
				$element.next().text(),
				'127.0.0.1',
				'IP is after temporary account user link for recently revealed temporary account'
			);
		} );
		// Remove the cookie after the test to avoid breaking other tests.
		mw.storage.remove( 'mw-checkuser-temp-~1' );
		done();
	} );
} );

QUnit.test( 'Test initOnHook when IP auto-reveal mode is toggled on and off', ( assert ) => {
	// Prevent the test being very slow if the wait for condition fails.
	assert.timeout( 1000 );
	// Handle requests to the temporary account IP lookup APIs responding with
	// mock data and a 200 HTTP status code.
	server.respond( ( request ) => {
		const response = request.url.endsWith( '/checkuser/v0/batch-temporaryaccount' ) ?
			{
				'~1': { revIps: { 1: '127.0.0.1', 2: '127.0.0.1' }, logIps: null, lastUsedIp: null },
				'~2': { revIps: { 3: '127.0.0.1' }, logIps: null, lastUsedIp: null },
				autoReveal: true
			} : {};
		request.respond( 200, { 'Content-Type': 'application/json' }, JSON.stringify( response ) );
	} );
	// eslint-disable-next-line no-jquery/no-global-selector
	const $qunitFixture = $( '#qunit-fixture' );
	// Call initOnHook with the QUnit fixture as the document root
	initOnHook( $qunitFixture );
	// Turn on IP auto-reveal mode.
	const expiry = Date.now() + 3600000;
	Utils.getAutoRevealStatus = function () {
		return $.Deferred().resolve( expiry );
	};
	// Add the new content for the test.
	const {
		temporaryAccountUserLinks
	} = setUpDocumentForTest();
	// Wait until there are IP reveal buttons are added
	const done = assert.async();
	waitUntilElementCount(
		'.ext-checkuser-tempaccount-reveal-ip',
		temporaryAccountUserLinks.length
	).then( () => {
		// Verify that the relevant IP is shown for the temporary account user links.
		temporaryAccountUserLinks.forEach( ( $element ) => {
			assert.strictEqual(
				$element.next().text(),
				'127.0.0.1',
				'IP is after temporary account user link when IP auto-reveal mode is on'
			);
		} );
		// Turn off IP auto-reveal mode.
		Utils.getAutoRevealStatus = function () {
			return $.Deferred().resolve( false );
		};
		initOnHook( $qunitFixture );
		// Verify that the IPs are hidden and the Show IP button is displayed.
		temporaryAccountUserLinks.forEach( ( $element ) => {
			assert.strictEqual(
				// eslint-disable-next-line no-jquery/no-class-state
				$element.next().hasClass( 'ext-checkuser-tempaccount-reveal-ip-button' ), true,
				'Show IP button is after temporary account user link when IP auto-reveal mode is off'
			);
		} );
		done();
	} );
} );
