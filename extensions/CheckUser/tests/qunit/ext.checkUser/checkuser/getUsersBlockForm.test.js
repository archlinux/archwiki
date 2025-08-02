'use strict';

const getUsersBlockForm = require( '../../../../modules/ext.checkUser/checkuser/getUsersBlockForm.js' );

QUnit.module( 'ext.checkUser.checkuser.getUsersBlockForm', QUnit.newMwEnvironment() );

/**
 * Set up the QUnit fixture for testing the getUsersBlockForm function.
 *
 * @param {Object.<string, boolean>} targets
 */
function setUpDocumentForTest( targets ) {
	// eslint-disable-next-line no-jquery/no-global-selector
	const $qunitFixture = $( '#qunit-fixture' );

	// Create the mock results list which will just contain the checkboxes.
	const resultsWrapper = document.createElement( 'div' );
	resultsWrapper.id = 'checkuserresults';
	resultsWrapper.className = 'mw-checkuser-get-users-results';

	let li;
	for ( const [ target, selected ] of Object.entries( targets ) ) {
		li = document.createElement( 'li' );
		const checkbox = document.createElement( 'input' );
		checkbox.type = 'checkbox';
		checkbox.name = 'users[]';
		checkbox.value = target;
		if ( selected ) {
			checkbox.click();
		}
		li.appendChild( checkbox );
		resultsWrapper.appendChild( li );
	}

	// Add an unrelated checkbox to test that it is ignored.
	li = document.createElement( 'li' );
	const unrelatedCheckbox = document.createElement( 'input' );
	unrelatedCheckbox.type = 'checkbox';
	unrelatedCheckbox.name = 'unrelated';
	unrelatedCheckbox.value = 'unrelated';
	unrelatedCheckbox.className = 'mw-checkuser-unrelated-checkbox';
	li.appendChild( unrelatedCheckbox );
	resultsWrapper.appendChild( li );

	// Add the checkboxes to the QUnit test fixture.
	$qunitFixture.html( resultsWrapper );

	// Create the block form fieldset which will contain the block accounts and block IPs buttons
	const blockForm = document.createElement( 'div' );
	blockForm.className = 'mw-checkuser-massblock';
	const fieldset = document.createElement( 'fieldset' );
	blockForm.appendChild( fieldset );

	// Add the block accounts button to the fieldset
	const accountsBlockButtonWrapper = document.createElement( 'div' );
	accountsBlockButtonWrapper.className = 'mw-checkuser-massblock-button mw-checkuser-massblock-accounts-button';
	accountsBlockButtonWrapper.appendChild( document.createElement( 'button' ) );
	fieldset.appendChild( accountsBlockButtonWrapper );

	// Add the block IPs button to the fieldset
	const ipsBlockButtonWrapper = document.createElement( 'div' );
	ipsBlockButtonWrapper.className = 'mw-checkuser-massblock-button mw-checkuser-massblock-ips-button';
	ipsBlockButtonWrapper.appendChild( document.createElement( 'button' ) );
	fieldset.appendChild( ipsBlockButtonWrapper );

	// Add the fieldset to the QUnit test fixture.
	$qunitFixture.append( blockForm );
}

function performBlockFormSubmitTest( assert, cssClass, $qunitFixture, expectedTargets, done ) {
	// Listen for any submits of the hidden form and prevent them to avoid opening a new tab when
	// running the tests. At the same time, if this event is triggered, then indicate that
	// the test passed.
	let formWasSubmitted;
	$( $qunitFixture ).on(
		'submit',
		'.ext-checkuser-hidden-block-form',
		( event ) => {
			event.preventDefault();
			formWasSubmitted = true;
			return false;
		}
	);

	// Click the appropriate block button
	$( cssClass + ' button', $qunitFixture )[ 0 ].click();
	setTimeout( () => {
		// Assert that the form element was correctly added
		const $formElement = $( '.ext-checkuser-hidden-block-form', $qunitFixture );
		assert.true(
			!!$formElement.length,
			'Form exists in the DOM after continue button was clicked'
		);
		assert.strictEqual(
			$formElement.attr( 'action' ),
			new mw.Title( 'Special:InvestigateBlock' ).getUrl(),
			'Form sends data to Special:InvestigateBlock'
		);
		// Assert that the targets are as expected.
		const $targetsInput = $formElement.find( 'input[name="wpTargets"]' );
		assert.true(
			!!$targetsInput.length,
			'Targets input exists in the DOM after continue button was clicked'
		);
		assert.strictEqual(
			$targetsInput.val(),
			expectedTargets.join( '\n' ),
			'Targets input value is set to the targets entered in the widget'
		);
		// Assert that the form was actually submitted.
		assert.true(
			formWasSubmitted,
			'Form was submitted when continue button was clicked'
		);
		// Clean up the form element from the DOM to avoid affecting other tests.
		$formElement.remove();
		// QUnit tests are now done, so we can call done.
		done();
	} );
}

// performBlockFormSubmitTest resolves the done async callback, but eslint doesn't detect this.
// eslint-disable-next-line qunit/resolve-async
QUnit.test( 'Test blocking accounts', ( assert ) => {
	// We need the test to wait a small amount of time for the click events to finish.
	const done = assert.async();

	// Set the HTML that is added by Special:CheckUser.
	setUpDocumentForTest( { Test: true, '1.2.3.4': true, Test2: false, '4.5.6.0/24': false } );

	// Call the function, specifying the QUnit fixture as the document root to avoid the
	// form being kept in the DOM for other JavaScript tests.
	// eslint-disable-next-line no-jquery/no-global-selector
	const $qunitFixture = $( '#qunit-fixture' );
	assert.strictEqual( getUsersBlockForm( $qunitFixture ), true );

	performBlockFormSubmitTest(
		assert,
		'.mw-checkuser-massblock-accounts-button',
		$qunitFixture,
		[ 'Test' ],
		done
	);
} );

// performBlockFormSubmitTest resolves the done async callback, but eslint doesn't detect this.
// eslint-disable-next-line qunit/resolve-async
QUnit.test( 'Test blocking IPs', ( assert ) => {
	// We need the test to wait a small amount of time for the click events to finish.
	const done = assert.async();

	// Set the HTML that is added by Special:CheckUser.
	setUpDocumentForTest( { Test: true, '1.2.3.4': true, Test2: false, '4.5.6.0/24': true } );

	// Call the function, specifying the QUnit fixture as the document root to avoid the
	// form being kept in the DOM for other JavaScript tests.
	// eslint-disable-next-line no-jquery/no-global-selector
	const $qunitFixture = $( '#qunit-fixture' );
	assert.strictEqual( getUsersBlockForm( $qunitFixture ), true );

	performBlockFormSubmitTest(
		assert,
		'.mw-checkuser-massblock-ips-button',
		$qunitFixture,
		[ '1.2.3.4', '4.5.6.0/24' ],
		done
	);
} );

QUnit.test( 'Test MultiLock link', ( assert ) => {
	// Set wgCUCAMultiLockCentral to a URL. It must be set for the test to work.
	mw.config.set( 'wgCUCAMultiLockCentral', 'https://example.com/wiki/Special:MultiLock' );

	// Set the HTML that is added by Special:CheckUser.
	setUpDocumentForTest( { Test: true, '1.2.3.4': true, Test2: false, '4.5.6.0/24': false } );

	// Call the function, specifying the QUnit fixture as the document root.
	// eslint-disable-next-line no-jquery/no-global-selector
	const $qunitFixture = $( '#qunit-fixture' );
	assert.strictEqual( getUsersBlockForm( $qunitFixture ), true );

	// Assert that the MultiLock URL is as expected
	const $linkElement = $( '.mw-checkuser-multilock-link', $qunitFixture );
	assert.true(
		!!$linkElement.length,
		'Link exists in the DOM after checkbox was clicked'
	);
	assert.strictEqual(
		$linkElement.attr( 'href' ),
		'https://example.com/wiki/Special:MultiLock?wpTarget=Test',
		'URL to Special:MultiLock is correctly set'
	);
	assert.strictEqual(
		$linkElement.text(),
		'(checkuser-centralauth-multilock)',
		'Link text for MultiLock link is correctly set'
	);
} );

QUnit.test( 'Test load without block buttons or MultiLock URL', ( assert ) => {
	// eslint-disable-next-line no-jquery/no-global-selector
	const $qunitFixture = $( '#qunit-fixture' );

	// Call the function and expect it to return false.
	assert.strictEqual( getUsersBlockForm( $qunitFixture ), false );
} );

QUnit.test( 'Test load without block buttons, but MultiLock URL defined', ( assert ) => {
	// Set wgCUCAMultiLockCentral to a URL. It must be set for the test to work.
	mw.config.set( 'wgCUCAMultiLockCentral', 'https://example.com/wiki/Special:MultiLock' );

	// Set the HTML that is added by Special:CheckUser.
	setUpDocumentForTest( { Test: true, '1.2.3.4': true, Test2: false, '4.5.6.0/24': false } );

	// eslint-disable-next-line no-jquery/no-global-selector
	const $qunitFixture = $( '#qunit-fixture' );

	// Remove the block buttons for the test.
	$( '.mw-checkuser-massblock-accounts-button', $qunitFixture ).remove();
	$( '.mw-checkuser-massblock-ips-button', $qunitFixture ).remove();

	// Call the function and expect it to return false.
	assert.strictEqual( getUsersBlockForm( $qunitFixture ), false );
} );
