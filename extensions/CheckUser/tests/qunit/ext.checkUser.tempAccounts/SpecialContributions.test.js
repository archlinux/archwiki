'use strict';

const specialContributions = require( '../../../modules/ext.checkUser.tempAccounts/SpecialContributions.js' ),
	{ waitUntilElementCount } = require( './utils.js' ),
	ipRevealUtils = require( '../../../modules/ext.checkUser.tempAccounts/ipRevealUtils.js' );

let server;

QUnit.module( 'ext.checkUser.tempAccounts.SpecialContributions', QUnit.newMwEnvironment( {
	beforeEach: function () {
		this.server = this.sandbox.useFakeServer();
		this.server.respondImmediately = true;
		server = this.server;
	},
	afterEach: function () {
		server.restore();
	},
	config: {
		// Prevent dispatcher.js from running SpecialContributions.js automatically.
		// We will call it in the tests at the right time.
		wgCanonicalSpecialPageName: 'CheckUser'
	}
} ) );

/**
 * Creates the bare-bones structure of the Special:Contributions page for testing and adds it
 * to the QUnit test fixture. This function does not add any revision lines.
 *
 * @param {string} target The value to use as wgRelevantUserName
 */
function setUpDocumentForTest( target ) {
	mw.config.set( 'wgRelevantUserName', target );
	const $container = $( '<div>' ).attr( 'id', 'bodyContent' );
	$container.append( $( '<div>' ).addClass( 'mw-contributions-list' ) );
	// eslint-disable-next-line no-jquery/no-global-selector
	const $qunitFixture = $( '#qunit-fixture' );
	$qunitFixture.append( $container );
}

QUnit.test( 'Test for an empty Special:Contributions page for temp account', ( assert ) => {
	setUpDocumentForTest( '~1' );
	// eslint-disable-next-line no-jquery/no-global-selector
	const $qunitFixture = $( '#qunit-fixture' );
	// Call the method under test.
	specialContributions( $qunitFixture, 'Contributions' );
	assert.strictEqual(
		$( '.ext-checkuser-tempaccount-reveal-ip-button', $qunitFixture ).length,
		0,
		'No IP reveal button added'
	);
} );

/**
 * Adds revision lines to the QUnit test fixture for testing.
 *
 * @param {string} pageTitle Either 'Contributions' or 'DeletedContributions'.
 *   Determines what element to append on the revision line.
 * @return {jQuery[]} The jQuery objects for the revision lines that were added.
 */
function addRevisionLinesForTest( pageTitle ) {
	// eslint-disable-next-line no-jquery/no-global-selector
	const $qunitFixture = $( '#qunit-fixture' );
	const $contributionsList = $( '.mw-contributions-list', $qunitFixture );
	const revisionLines = [ 1, 3, 6 ];
	const revisionLineElements = [];
	revisionLines.forEach( ( revId ) => {
		const $revisionLine = $( '<div>' ).attr( 'data-mw-revid', revId );
		if ( pageTitle === 'Contributions' ) {
			// Add the .mw-diff-bytes element to the revision line
			$revisionLine.append( $( '<span>' ).addClass( 'mw-diff-bytes' ) );
		} else if ( pageTitle === 'DeletedContributions' ) {
			// Add the .mw-deletedcontribs-tools element to the revision line
			$revisionLine.append( $( '<span>' ).addClass( 'mw-deletedcontribs-tools' ) );
		}
		$contributionsList.append( $revisionLine );
		revisionLineElements.push( $revisionLine );
	} );
	return revisionLineElements;
}

QUnit.test( 'Test for a Special:Contributions page for unrevealed temp account', ( assert ) => {
	mw.storage.remove( 'mw-checkuser-temp-~1' );
	setUpDocumentForTest( '~1' );
	// Add the testing revision lines
	const revisionLines = addRevisionLinesForTest( 'Contributions' );
	// Call the method under test.
	// eslint-disable-next-line no-jquery/no-global-selector
	const $qunitFixture = $( '#qunit-fixture' );
	specialContributions( $qunitFixture, 'Contributions' );
	revisionLines.forEach( ( $element ) => {
		assert.strictEqual(
			// eslint-disable-next-line no-jquery/no-class-state
			$element.find( '.mw-diff-bytes' ).next().next().hasClass( 'ext-checkuser-tempaccount-reveal-ip-button' ),
			true,
			'IP reveal button after added bytes in revision line'
		);
	} );
} );

QUnit.test( 'Test for a Special:Contributions page for revealed temp account', ( assert ) => {
	assert.timeout( 1000 );
	// This assumes that the API request is for the revision API, however, this should occur
	// because only revision related temporary account "Show IP" buttons should exist in
	// the page.
	server.respond( ( request ) => {
		request.respond(
			200,
			{ 'Content-Type': 'application/json' },
			'{"ips":{"1":"127.0.0.1","3":"127.0.0.1","6":"127.0.0.1"}}'
		);
	} );
	setUpDocumentForTest( '~1' );
	// Add the testing revision lines
	const revisionLines = addRevisionLinesForTest( 'Contributions' );
	// Set that the temporary account has been revealed recently.
	ipRevealUtils.setRevealedStatus( '~1' );
	// Call the method under test.
	// eslint-disable-next-line no-jquery/no-global-selector
	const $qunitFixture = $( '#qunit-fixture' );
	specialContributions( $qunitFixture, 'Contributions' );
	// Wait until all the IPs have been revealed.
	const done = assert.async();
	waitUntilElementCount( '.ext-checkuser-tempaccount-reveal-ip', revisionLines.length ).then( () => {
		revisionLines.forEach( ( $element ) => {
			// Check that the revealed IP is present after the element with the mw-diff-bytes class
			assert.strictEqual(
				// eslint-disable-next-line no-jquery/no-class-state
				$element.find( '.mw-diff-bytes' ).next().next().hasClass( 'ext-checkuser-tempaccount-reveal-ip' ),
				true,
				'Revealed IP after added bytes in revision line'
			);
			// Verify the revealed IP is as expected.
			assert.strictEqual(
				$element.find( '.mw-diff-bytes' ).next().next().text(),
				'127.0.0.1',
				'Revealed IP is as expected'
			);
		} );
		// Remove the cookie after the test to avoid breaking other tests.
		mw.storage.remove( 'mw-checkuser-temp-~1' );
		done();
	} );
} );

/*
 * This is the same test as for Special:Contributions, to ensure that the button loads on
 * Special:DeletedContributions too.
 */
QUnit.test( 'Test for a Special:DeletedContributions page for unrevealed temp account', ( assert ) => {
	mw.storage.remove( 'mw-checkuser-temp-~1' );
	setUpDocumentForTest( '~1' );
	// Add the testing revision lines
	const revisionLines = addRevisionLinesForTest( 'DeletedContributions' );
	// Call the method under test.
	// eslint-disable-next-line no-jquery/no-global-selector
	const $qunitFixture = $( '#qunit-fixture' );
	specialContributions( $qunitFixture, 'DeletedContributions' );
	revisionLines.forEach( ( $element ) => {
		assert.strictEqual(
			// eslint-disable-next-line no-jquery/no-class-state
			$element.find( '.mw-deletedcontribs-tools' ).next().next().hasClass( 'ext-checkuser-tempaccount-reveal-ip-button' ),
			true,
			'IP reveal button after added bytes in revision line'
		);
	} );
} );
