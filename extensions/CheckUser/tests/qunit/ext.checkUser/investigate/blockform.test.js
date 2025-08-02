'use strict';

const addBlockForm = require( '../../../../modules/ext.checkUser/investigate/blockform.js' );

QUnit.module( 'ext.checkUser.investigate.blockform', QUnit.newMwEnvironment() );

function setUpDocumentForTest( targets ) {
	// eslint-disable-next-line no-jquery/no-global-selector
	const $qunitFixture = $( '#qunit-fixture' );

	// Set the javascript config used by blockform.js to get the targets and
	// excluded targets in the current check.
	mw.config.set( 'wgCheckUserInvestigateTargets', targets );
	mw.config.set( 'wgCheckUserInvestigateExcludeTargets', [] );

	// Create the barebones HTML structure added by SpecialInvestigate::addBlockForm
	const node = document.createElement( 'div' );
	node.className = 'ext-checkuser-investigate-subtitle-fieldset';
	// Add the accounts and ips block button, including adding infusion data (which is
	// hardcoded in the test because there isn't an easier way to generate this
	// in a QUnit context).
	// First adds the accounts block button.
	const $accountsBlockButton = new OO.ui.ButtonWidget( {
		label: 'checkuser-investigate-subtitle-block-button-label',
		classes: [
			'ext-checkuser-investigate-subtitle-block-button',
			'ext-checkuser-investigate-subtitle-block-accounts-button'
		]
	} ).$element;
	$accountsBlockButton.attr(
		'data-ooui',
		'{"_":"OO.ui.ButtonWidget","rel":["nofollow"],"label":"Block accounts","flags":' +
		'["primary","progressive"],"classes":["ext-checkuser-investigate-subtitle-block-' +
		'button","ext-checkuser-investigate-subtitle-block-accounts-button"]}'
	);
	node.appendChild( $accountsBlockButton[ 0 ] );
	// Next add the IPs block button
	const $ipsBlockButton = new OO.ui.ButtonWidget( {
		label: 'checkuser-investigate-subtitle-block-button-label',
		classes: [
			'ext-checkuser-investigate-subtitle-block-button',
			'ext-checkuser-investigate-subtitle-block-ips-button'
		]
	} ).$element;
	$ipsBlockButton.attr(
		'data-ooui',
		'{"_":"OO.ui.ButtonWidget","rel":["nofollow"],"label":"Block IPs","flags":' +
		'["primary","progressive"],"classes":["ext-checkuser-investigate-subtitle-' +
		'block-button","ext-checkuser-investigate-subtitle-block-ips-button"]}'
	);
	node.appendChild( $ipsBlockButton[ 0 ] );
	// Add a placeholder widget which gets replaced with the targets widget.
	const placeholderWidget = document.createElement( 'div' );
	placeholderWidget.className = 'ext-checkuser-investigate-subtitle-placeholder-widget';
	node.appendChild( placeholderWidget );
	// Add the barebones HTML structure to the QUnit fixture element.
	$qunitFixture.html( node );
	return $( node );
}

QUnit.test( 'Test visibility of block form elements on DOM load, after block click, and after cancel click', ( assert ) => {
	// We need the test to wait a small amount of time for the click events to finish.
	const done = assert.async();

	// Set the HTML that is added by Special:Investigate.
	const $actualHtmlElement = setUpDocumentForTest( [ 'Test' ] );

	// Call the function
	addBlockForm();

	// Assert the state when the DOM is loaded first.
	const cases = require( './cases/blockFormWidgetVisibility.json' );
	cases.forEach( ( caseItem ) => {
		const $elementForCase = $actualHtmlElement.find( caseItem.cssClass );
		assert.true(
			!!$elementForCase.length,
			caseItem.msg + ' exists on DOM load'
		);
		assert.strictEqual(
			// eslint-disable-next-line no-jquery/no-class-state
			!$elementForCase.hasClass( 'oo-ui-element-hidden' ),
			caseItem.visibleOnLoad,
			caseItem.msg + ' visibility state on DOM load'
		);
	} );

	// Click the block button
	$( '.ext-checkuser-investigate-subtitle-block-button a', $actualHtmlElement )[ 0 ].click();
	setTimeout( () => {
		// Assert the state after the block button is clicked.
		cases.forEach( ( caseItem ) => {
			const $elementForCase = $actualHtmlElement.find( caseItem.cssClass );
			assert.true(
				!!$elementForCase.length,
				caseItem.msg + ' exists after block button click'
			);
			assert.strictEqual(
				// eslint-disable-next-line no-jquery/no-class-state
				!$elementForCase.hasClass( 'oo-ui-element-hidden' ),
				caseItem.visibleAfterBlockClick,
				caseItem.msg + ' visibility state after block button click'
			);
		} );

		// Click the cancel button
		$( '.ext-checkuser-investigate-subtitle-cancel-button a', $actualHtmlElement )[ 0 ].click();

		setTimeout( () => {
			// Assert the state after the cancel button is clicked.
			cases.forEach( ( caseItem ) => {
				const $elementForCase = $actualHtmlElement.find( caseItem.cssClass );
				assert.true(
					!!$elementForCase.length,
					caseItem.msg + ' exists after cancel button click'
				);
				assert.strictEqual(
					// eslint-disable-next-line no-jquery/no-class-state
					!$elementForCase.hasClass( 'oo-ui-element-hidden' ),
					caseItem.visibleAfterCancelClick,
					caseItem.msg + ' visibility state after cancel button click'
				);
			} );

			// QUnit tests are now done, so we can call done.
			done();
		} );
	} );
} );

function performBlockFormSubmitTest(
	assert, cssClass, $actualHtmlElement, $qunitFixture, expectedTargets, done
) {
	// Listen for any submits of the hidden form and prevent them to avoid opening
	// a new tab when running the tests.
	// At the same time, if this event is triggered, then indicate that the test passed.
	let formWasSubmitted;
	$( $qunitFixture ).on(
		'submit',
		'.ext-checkuser-investigate-hidden-block-form',
		( event ) => {
			event.preventDefault();
			formWasSubmitted = true;
			return false;
		}
	);

	// Click the appropriate block button
	$( cssClass + ' a', $actualHtmlElement )[ 0 ].click();
	setTimeout( () => {
		// Click the continue button
		$( '.ext-checkuser-investigate-subtitle-continue-button a', $actualHtmlElement )[ 0 ].click();
		setTimeout( () => {
			// Assert that the form element was correctly added
			const $formElement = $( '.ext-checkuser-investigate-hidden-block-form', $qunitFixture );
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
	} );
}

// performBlockFormSubmitTest resolves the done async callback, but eslint doesn't detect this.
// eslint-disable-next-line qunit/resolve-async
QUnit.test( 'Test blocking accounts', ( assert ) => {
	// We need the test to wait a small amount of time for the click events to finish.
	const done = assert.async();

	// Set the HTML that is added by Special:Investigate.
	const $actualHtmlElement = setUpDocumentForTest( [ 'Test', '1.2.3.4', 'Test2', '4.5.6.0/24' ] );

	// Call the function, specifying the QUnit fixture as the document root
	// to avoid the form being kept in the DOM for other JavaScript tests.
	// eslint-disable-next-line no-jquery/no-global-selector
	const $qunitFixture = $( '#qunit-fixture' );
	addBlockForm( $qunitFixture );

	performBlockFormSubmitTest(
		assert,
		'.ext-checkuser-investigate-subtitle-block-accounts-button',
		$actualHtmlElement,
		$qunitFixture,
		[ 'Test', 'Test2' ],
		done
	);
} );

// performBlockFormSubmitTest resolves the done async callback, but eslint doesn't detect this.
// eslint-disable-next-line qunit/resolve-async
QUnit.test( 'Test blocking IPs', ( assert ) => {
	// We need the test to wait a small amount of time for the click events to finish.
	const done = assert.async();

	// Set the HTML that is added by Special:Investigate.
	const $actualHtmlElement = setUpDocumentForTest( [ 'Test', '1.2.3.4', 'Test2', '4.5.6.0/24' ] );

	// Call the function, specifying the QUnit fixture as the document root
	// to avoid the form being kept in the DOM for other JavaScript tests.
	// eslint-disable-next-line no-jquery/no-global-selector
	const $qunitFixture = $( '#qunit-fixture' );
	addBlockForm( $qunitFixture );

	performBlockFormSubmitTest(
		assert,
		'.ext-checkuser-investigate-subtitle-block-ips-button',
		$actualHtmlElement,
		$qunitFixture,
		[ '1.2.3.4', '4.5.6.0/24' ],
		done
	);
} );
