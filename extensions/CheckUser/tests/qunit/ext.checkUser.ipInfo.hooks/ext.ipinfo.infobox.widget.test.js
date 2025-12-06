'use strict';
const addSpecialGlobalContributionsLink = require( '../../../modules/ext.checkUser.ipInfo.hooks/infobox.js' );

QUnit.module( 'ext.checkUser.ipInfo.hooks', QUnit.newMwEnvironment( {
	beforeEach: function () {
		// simulate setting wgAutoCreateTempUser to { enabled: true, matchPattern: '~$1' }
		// (setting it in mw.config has no effect, so we need to
		// overwrite mw.util.isTemporaryUser())
		this.realIsTemporaryUser = mw.util.isTemporaryUser;
		mw.util.isTemporaryUser = function ( username ) {
			return username.startsWith( '~' );
		};
	},
	afterEach: function () {
		mw.util.isTemporaryUser = this.realIsTemporaryUser;
	}
} ) );

function setUpDocumentForTest() {
	// Stub out the attribute container from IPInfo box for the function under test to act on
	const $el = $( '<div>' ).append( $( '<div>' ).attr( 'data-property', 'edits' ) );
	return $el;
}

/**
 * Pass-through function to mimic IPInfo's generatePropertyMarkup function that's passed to
 * CheckUser normally in order to generate standard markup for an IPInfo property
 *
 * @param {string} _$title
 * @param {Object} $content
 * @return {Object}
 */
function generateMarkup( _$title, $content ) {
	return $content;
}

/**
 * Stub out mock data returned from IPInfoHandler with results modified via CheckUser
 *
 * @return {Object}
 */
function returnData() {
	return {
		data: {
			'ipinfo-source-checkuser': {
				globalContributionsCount: 1
			}
		}
	};
}

QUnit.test( 'Special:GC link not added if conditions not met when hook is fired', ( assert ) => {
	let $body = setUpDocumentForTest();
	addSpecialGlobalContributionsLink( $body, returnData(), generateMarkup, 'Registered User', 'Contributions' );
	assert.strictEqual(
		$body.find( '.ext-ipinfo-global-contribution-link' ).length,
		0,
		'No Special:GC link added to page with invalid conditions'
	);

	$body = setUpDocumentForTest();
	addSpecialGlobalContributionsLink( $body, returnData(), generateMarkup, '1.2.3.4', 'Contributions' );
	assert.strictEqual(
		$body.find( '.ext-ipinfo-global-contribution-link' ).length,
		0,
		'No Special:GC link added to page with invalid conditions'
	);
} );

QUnit.test( 'Test Special:GC link added on ext.ipinfo.infobox.widget hook', ( assert ) => {
	let $body = setUpDocumentForTest( '~1', 'Contributions' );
	addSpecialGlobalContributionsLink( $body, returnData(), generateMarkup, '~1', 'Contributions' );
	assert.strictEqual(
		$body.find( '.ext-ipinfo-global-contribution-link' ).length,
		1,
		'Special:GC link added to temporary account\'s contributions page'
	);

	$body = setUpDocumentForTest( '~1', 'DeletedContributions' );
	addSpecialGlobalContributionsLink( $body, returnData(), generateMarkup, '~1', 'Contributions' );
	assert.strictEqual(
		$body.find( '.ext-ipinfo-global-contribution-link' ).length,
		1,
		'Special:GC link added to temporary account\'s deleted contributions page'
	);

	$body = setUpDocumentForTest( '1.2.3.4', 'IPContributions' );
	addSpecialGlobalContributionsLink( $body, returnData(), generateMarkup, '1.2.3.4', 'IPContributions' );
	assert.strictEqual(
		$body.find( '.ext-ipinfo-global-contribution-link' ).length,
		1,
		'Special:GC link added to IP\'s contributions page'
	);
} );
