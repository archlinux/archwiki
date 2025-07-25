'use strict';

const BlockDetailsPopupButtonWidget = require(
	'../../../modules/ext.checkUser.tempAccounts/BlockDetailsPopupButtonWidget.js'
);

QUnit.module( 'ext.checkUser.tempAccounts.BlockDetailsPopupButtonWidget', QUnit.newMwEnvironment( {
	beforeEach: function () {
		this.server = this.sandbox.useFakeServer();
		this.server.respondImmediately = true;

		// Clear any cached response from previous tests that may have exercised this widget.
		BlockDetailsPopupButtonWidget.static.cachedBlockDetails = null;
	},
	afterEach: function () {
		this.server.restore();
	}
} ) );

QUnit.test( 'renders initial state', ( assert ) => {
	const widget = new BlockDetailsPopupButtonWidget();

	// Checking the presence of a class does not immediately imply
	// we're using it to store application state, especially in tests.
	// eslint-disable-next-line no-jquery/no-class-state
	assert.true( widget.$element.hasClass( 'oo-ui-popupButtonWidget' ), 'should be an OO.ui.PopupButtonWidget' );
	assert.strictEqual( widget.popup.$body.html(), '', 'popup should be empty' );
} );

QUnit.test( 'fetches block details when opened', function ( assert ) {
	const widget = new BlockDetailsPopupButtonWidget();

	this.server.respondWith(
		'GET', /api\.php\?action=query&format=json&meta=checkuserformattedblockinfo&formatversion=2$/,
		[
			200, { 'Content-Type': 'application/json' },
			JSON.stringify( {
				query: {
					checkuserformattedblockinfo: {
						details: '<div>Block details</div>'
					}
				}
			} )
		] );

	widget.emit( 'click' );

	assert.true(
		widget.popup.$body.html().includes( widget.loadingIndicator.$element.html() ),
		'popup should contain loading indicator'
	);

	return BlockDetailsPopupButtonWidget.static.cachedBlockDetails.then( () => {
		assert.false(
			widget.popup.$body.html().includes( widget.loadingIndicator.$element.html() ),
			'popup should not contain loading indicator'
		);
		assert.strictEqual(
			widget.popup.$body.html(),
			'<h4>(checkuser-tempaccount-reveal-blocked-header)</h4>' +
			'<p>(checkuser-tempaccount-reveal-blocked-description)</p>' +
			'<div>Block details</div>',
			'popup should contain block details'
		);
	} );
} );

QUnit.test( 'renders notice if no block details found', function ( assert ) {
	const widget = new BlockDetailsPopupButtonWidget();

	this.server.respondWith(
		'GET', /api\.php\?action=query&format=json&meta=checkuserformattedblockinfo&formatversion=2$/,
		[
			200, { 'Content-Type': 'application/json' },
			JSON.stringify( {
				query: {
					checkuserformattedblockinfo: null
				}
			} )
		] );

	widget.emit( 'click' );

	assert.true(
		widget.popup.$body.html().includes( widget.loadingIndicator.$element.html() ),
		'popup should contain loading indicator'
	);

	return BlockDetailsPopupButtonWidget.static.cachedBlockDetails.then( () => {
		assert.false(
			widget.popup.$body.html().includes( widget.loadingIndicator.$element.html() ),
			'popup should not contain loading indicator'
		);
		assert.strictEqual(
			widget.popup.$body.html(),
			BlockDetailsPopupButtonWidget.static.createMessageWidget(
				'success',
				mw.msg( 'checkuser-tempaccount-reveal-blocked-missingblock' )
			)[ 0 ].outerHTML,
			'popup should contain notice about missing block details'
		);
	} );
} );

QUnit.test( 'renders error message on API error', function ( assert ) {
	const widget = new BlockDetailsPopupButtonWidget();

	this.server.respondWith(
		'GET', /api\.php\?action=query&format=json&meta=checkuserformattedblockinfo&formatversion=2$/,
		[
			500, { 'Content-Type': 'application/json' },
			JSON.stringify( {} )
		] );

	widget.emit( 'click' );

	assert.true(
		widget.popup.$body.html().includes( widget.loadingIndicator.$element.html() ),
		'popup should contain loading indicator'
	);

	return BlockDetailsPopupButtonWidget.static.cachedBlockDetails.then( () => {
		assert.false(
			widget.popup.$body.html().includes( widget.loadingIndicator.$element.html() ),
			'popup should not contain loading indicator'
		);
		assert.strictEqual(
			widget.popup.$body.html(),
			BlockDetailsPopupButtonWidget.static.createMessageWidget(
				'error',
				mw.msg( 'checkuser-tempaccount-reveal-blocked-error' )
			)[ 0 ].outerHTML,
			'popup should contain error message'
		);
	} );
} );
