'use strict';

const specialBlock = require( '../../../modules/ext.checkUser.tempAccounts/SpecialBlock.js' );
const { waitUntilElementDisappears, waitUntilElementAppears } = require( './utils.js' );

let server;

QUnit.module( 'ext.checkUser.tempAccounts.SpecialBlock', QUnit.newMwEnvironment( {
	beforeEach: function () {
		this.server = this.sandbox.useFakeServer();
		this.server.respondImmediately = true;
		server = this.server;
		// simulate setting wgAutoCreateTempUser to { enabled: true, matchPattern: '~$1' }
		// (setting it in mw.config has no effect, so we need to
		// overwrite mw.util.isTemporaryUser())
		this.realIsTemporaryUser = mw.util.isTemporaryUser;
		mw.util.isTemporaryUser = function ( username ) {
			return username.startsWith( '~' );
		};
		// Simulate mw.Title, but keep the real mw.Title so that we can undo our mock at the
		// end of our tests.
		this.realTitleClass = mw.Title;
		class Title {
			constructor( title ) {
				this.title = title;
			}

			getUrl() {
				return 'https://www.example.com/wiki/' + this.title;
			}
		}
		mw.Title = Title;
	},
	afterEach: function () {
		server.restore();
		// Remove the 'change' listener for the block target widget to stop it
		// causing problems in other tests.
		// eslint-disable-next-line no-jquery/no-global-selector
		const $blockTargetWidget = $( '#mw-bi-target' );
		if ( $blockTargetWidget.length ) {
			$blockTargetWidget.off( 'change' );
		}
		mw.util.isTemporaryUser = this.realIsTemporaryUser;
		mw.Title = this.realTitleClass;
	},
	config: {
		// Prevent dispatcher.js calling the code we are testing. We will call it
		// manually when we need to.
		wgCanonicalSpecialPageName: 'CheckUser',
		// Set max age as the default (3 months)
		wgCUDMaxAge: 7776000
	}
} ) );

QUnit.test( 'Test createButton creates expected button', ( assert ) => {
	// Call createButton (the method under test).
	const button = specialBlock.createButton();
	// Verify that the button has the correct text and classes.
	assert.strictEqual(
		button.$element.text(),
		'(checkuser-tempaccount-reveal-ip-button-label)',
		'Button text'
	);
	assert.strictEqual(
		// eslint-disable-next-line no-jquery/no-class-state
		button.$element.hasClass( 'ext-checkuser-tempaccount-specialblock-ips-link' ),
		true,
		'Button class'
	);
} );

/**
 * Adds the block target input to the QUnit fixture.
 *
 * @param {string} targetValue The initial value of the block target input
 */
function addBlockInputToQUnitTextFixture( targetValue ) {
	const $blockTargetInput = new mw.widgets.UserInputWidget( {
		label: 'test', classes: [], value: targetValue, id: 'mw-bi-target'
	} ).$element;
	// We have to hardcode the infusion data, as there isn't an easier way to get it
	// in a QUnit context.
	$blockTargetInput.attr(
		'data-ooui',
		'{"_":"mw.widgets.UserInputWidget","$overlay":true,"placeholder":"UserName, ' +
		'1.1.1.42, or 1.1.1.42/16","autofocus":true,"name":"wpTarget","inputId":"ooui-php-1"' +
		',"indicator":"required","required":true}'
	);
	const $container = $( '<div>' ).attr( 'id', 'mw-htmlform-target' );
	$container.append( $blockTargetInput );
	// eslint-disable-next-line no-jquery/no-global-selector
	const $qunitFixture = $( '#qunit-fixture' );
	$qunitFixture.append( $container );
}

QUnit.test( 'Test onLoad for a user which is not a temporary account', ( assert ) => {
	assert.timeout( 1000 );
	const done = assert.async();
	// Add the target input with a username that is not a temporary account
	addBlockInputToQUnitTextFixture( 'Test' );
	// eslint-disable-next-line no-jquery/no-global-selector
	const $qunitFixture = $( '#qunit-fixture' );
	// Add a fake SpecialBlock "Show IPs" button to the DOM to verify that it is removed
	// when calling onLoad when the target is a not a temporary account.
	$qunitFixture.append( $( '<div>' ).addClass( 'ext-checkuser-tempaccount-specialblock-ips' ) );
	// Call the method under test
	specialBlock.onLoad();
	waitUntilElementDisappears( '.ext-checkuser-tempaccount-specialblock-ips' ).then( () => {
		// Verify that the fake "Show IPs" button was removed
		assert.strictEqual(
			$( '.ext-checkuser-tempaccount-specialblock-ips', $qunitFixture ).length,
			0,
			'IP reveal button removed'
		);
		done();
	} );
} );

/**
 * Check if the request is a query for whether the temporary account exists.
 *
 * @param {Object} request The request to check
 * @return {boolean}
 */
function isUsUsersApiQuery( request ) {
	// The 'base' argument is required, but is not affect the test.
	const url = new URL( request.url, 'https://www.example.com' );
	return (
		url.searchParams.get( 'action' ) === 'query' && url.searchParams.get( 'list' ) === 'users' &&
		url.searchParams.get( 'ususers' ) === '~2024-1' && request.method === 'GET'
	);
}

QUnit.test( 'Test onLoad for a user which matches temporary account format but does not exist', ( assert ) => {
	assert.timeout( 1000 );
	const done = assert.async( 2 );
	// Add the target input with a username that matches the temporary account format,
	// but does not exist.
	addBlockInputToQUnitTextFixture( '~2024-1' );
	// Respond to the 'users' list API query with no data to indicate that the user does not exist
	server.respond( ( request ) => {
		if ( isUsUsersApiQuery( request ) ) {
			request.respond(
				200,
				{ 'Content-Type': 'application/json' },
				'{"query":{"users":[{"name":"~2024-1","missing":true}]}}'
			);
			// By calling done() here, we assert in the test that the API request was made.
			// If no requests are made, then the test will time out.
			done();
		}
	} );
	// eslint-disable-next-line no-jquery/no-global-selector
	const $qunitFixture = $( '#qunit-fixture' );
	// Add a fake SpecialBlock "Show IPs" button to the DOM to verify that it is
	// removed when calling onLoad when the target is a non-existent user.
	$qunitFixture.append( $( '<div>' ).addClass( 'ext-checkuser-tempaccount-specialblock-ips' ) );
	// Call the method under test
	specialBlock.onLoad();
	waitUntilElementDisappears( '.ext-checkuser-tempaccount-specialblock-ips' ).then( () => {
		// Verify that the fake "Show IPs" button was removed
		assert.strictEqual(
			$( '.ext-checkuser-tempaccount-specialblock-ips', $qunitFixture ).length,
			0,
			'IP reveal button removed'
		);
		done();
	} );
} );

/**
 * Call the onLoad method, click the button that is created and then verify the text that replaces
 * the button is as expected.
 *
 * @param {string} expectedText The expected text of the element that replaces the button
 * @param {Object} assert The QUnit assert object
 * @param {*} done Method to call to indicate this method has completed assertions
 */
async function performOnLoadTestWhenButtonClicked( expectedText, assert, done ) {
	// Call the method under test
	specialBlock.onLoad();
	// eslint-disable-next-line no-jquery/no-global-selector
	const $qunitFixture = $( '#qunit-fixture' );
	await waitUntilElementAppears( '.ext-checkuser-tempaccount-specialblock-ips' ).then( () => {
		// Verify that the "Show IP" button is present.
		assert.strictEqual(
			$( '.ext-checkuser-tempaccount-specialblock-ips-link', $qunitFixture ).length,
			1,
			'IP reveal button appears'
		);
		// Click the button.
		$( '.ext-checkuser-tempaccount-specialblock-ips-link a', $qunitFixture )[ 0 ].click();
		return waitUntilElementDisappears( '.ext-checkuser-tempaccount-specialblock-ips-link' )
			.then( () => new Promise( ( resolve ) => {
				setTimeout( () => {
					// Verify that the button has gone and was replaced with the IP address.
					assert.strictEqual(
						$( '.ext-checkuser-tempaccount-specialblock-ips', $qunitFixture ).length,
						1,
						'Container still present after button click'
					);
					assert.strictEqual(
						$( '.ext-checkuser-tempaccount-specialblock-ips', $qunitFixture ).text(),
						expectedText,
						'Text of element that replaced button'
					);
					done();
					resolve();
				} );
			} ) );
	} );
}

QUnit.test( 'Test onLoad for an existing temporary account with IP data', async ( assert ) => {
	assert.timeout( 1000 );
	const done = assert.async( 4 );
	// Add the target input with a username that matches the temporary account format,
	// but does not exist.
	addBlockInputToQUnitTextFixture( '~2024-1' );
	server.respond( ( request ) => {
		if ( isUsUsersApiQuery( request ) ) {
			// Handle a request to check that the temporary user exists, and return that it does.
			request.respond(
				200,
				{ 'Content-Type': 'application/json' },
				'{"query":{"users":[{"name":"~2024-1","userid":1}]}}'
			);
			// By calling done() here, we assert in the test that the API request was made.
			// If not all the required requests are made, then the test will time out.
			done();
		} else if (
			request.url.includes( 'checkuser/v0/temporaryaccount/~2024-1' ) &&
			request.method === 'POST'
		) {
			// Handle a request to the temporary account API
			request.respond( 200, { 'Content-Type': 'application/json' }, '{"ips":["172.20.0.1","1.2.3.4"]}' );
			// By calling done() here, we assert in the test that the API request was made.
			// If not all the required requests are made, then the test will time out.
			done();
		}
	} );
	await performOnLoadTestWhenButtonClicked(
		'(checkuser-tempaccount-specialblock-ips: 2, 172.20.0.1(and)(word-separator)1.2.3.4)',
		assert, done
	);
	// Check that the IPs in the element that replaced the button are
	// links to Special:IPContributions for the IP.
	// eslint-disable-next-line no-jquery/no-global-selector
	const $qunitFixture = $( '#qunit-fixture' );
	const $container = $( '.ext-checkuser-tempaccount-specialblock-ips', $qunitFixture );
	const $linkElements = $container.find( 'a' );
	$linkElements.each( function () {
		const ip = $( this ).text();
		assert.strictEqual(
			$( this ).attr( 'href' ),
			'https://www.example.com/wiki/Special:IPContributions/' + ip,
			'IP address in element which replaced button has correct URL'
		);
	} );
	done();
} );

QUnit.test( 'Test onLoad for an existing temporary account without IP data', ( assert ) => {
	assert.timeout( 1000 );
	const done = assert.async( 3 );
	// Add the target input with a username that matches the temporary account format,
	// but does not exist.
	addBlockInputToQUnitTextFixture( '~2024-1' );
	server.respond( ( request ) => {
		if ( isUsUsersApiQuery( request ) ) {
			// Handle a request to check that the temporary user exists, and return that it does.
			request.respond(
				200,
				{ 'Content-Type': 'application/json' },
				'{"query":{"users":[{"name":"~2024-1","userid":1}]}}'
			);
			// By calling done() here, we assert in the test that the API request was made.
			// If not all the required requests are made, then the test will time out.
			done();
		} else if (
			request.url.includes( 'checkuser/v0/temporaryaccount/~2024-1' ) &&
			request.method === 'POST'
		) {
			// Handle a request to the temporary account API
			request.respond( 200, { 'Content-Type': 'application/json' }, '{"ips":[]}' );
			// By calling done() here, we assert in the test that the API request was made.
			// If not all the required requests are made, then the test will time out.
			done();
		}
	} );
	performOnLoadTestWhenButtonClicked(
		'(checkuser-tempaccount-no-ip-results: 90)', assert, done
	);
} );

QUnit.test( 'Test onLoad for an existing temporary account but IP data call fails', ( assert ) => {
	assert.timeout( 1000 );
	const done = assert.async( 3 );
	// Add the target input with a username that matches the temporary account format,
	// but does not exist.
	addBlockInputToQUnitTextFixture( '~2024-1' );
	server.respond( ( request ) => {
		if ( isUsUsersApiQuery( request ) ) {
			// Handle a request to check that the temporary user exists, and return that it does.
			request.respond(
				200,
				{ 'Content-Type': 'application/json' },
				'{"query":{"users":[{"name":"~2024-1","userid":1}]}}'
			);
			// By calling done() here, we assert in the test that the API request was made.
			// If not all the required requests are made, then the test will time out.
			done();
		} else if (
			request.url.includes( 'checkuser/v0/temporaryaccount/~2024-1' ) &&
			request.method === 'POST'
		) {
			// Handle a request to the temporary account API by returning a 500 error
			request.respond( 500, { 'Content-Type': 'application/json' }, '' );
			// By calling done() here, we assert in the test that the API request was made.
			// If not all the required requests are made, then the test will time out.
			done();
		}
	} );
	performOnLoadTestWhenButtonClicked(
		'(checkuser-tempaccount-reveal-ip-error)', assert, done
	);
} );

QUnit.test( 'Test onLoad when Codex Special:Block is enabled', ( assert ) => {
	mw.config.set( 'wgUseCodexSpecialBlock', true );
	// Add a mock block target input to simulate that the page is the block page.
	const $blockTargetInput = $( '<div>' ).attr( 'id', 'mw-bi-target' );
	// eslint-disable-next-line no-jquery/no-global-selector
	const $qunitFixture = $( '#qunit-fixture' );
	$qunitFixture.append( $blockTargetInput );
	// Call the method under test
	specialBlock.onLoad();
	// Fire the 'codex.userlookup' hook with a mock Vue ref and then expect
	// that the Show IP button is added to this mock Vue ref.
	const customComponents = { value: [] };
	mw.hook( 'codex.userlookup' ).fire( customComponents );
	assert.strictEqual( customComponents.value.length, 1, 'Component was added' );
	assert.strictEqual(
		customComponents.value[ 0 ].name,
		'ShowIPButton',
		'Show IP button component was added'
	);
} );
