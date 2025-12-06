'use strict';

const abuseLog = require( 'ext.checkUser.tempAccounts/AbuseLog.js' );

let server;

QUnit.module( 'ext.checkUser.tempAccounts.AbuseLog', QUnit.newMwEnvironment( {
	beforeEach: function () {
		this.server = this.sandbox.useFakeServer();
		this.server.respondImmediately = true;
		server = this.server;
	},
	afterEach: function () {
		server.restore();
	}
} ) );

QUnit.test( 'Test for an empty Special:AbuseLog page', ( assert ) => {
	mw.storage.remove( 'mw-checkuser-temp-~1' );

	// eslint-disable-next-line no-jquery/no-global-selector
	const $qunitFixture = $( '#qunit-fixture' );
	const $wrapper = $( '<div>', $qunitFixture );
	const $contentText = $( '<div>', $wrapper );

	$wrapper.attr( 'id', 'bodyContent' );
	$contentText.attr( 'id', 'mw-content-text' );
	$contentText.attr( 'class', 'mw-body-content' );

	abuseLog.onLoad( $qunitFixture );

	assert.strictEqual(
		$( '.ext-checkuser-tempaccount-reveal-ip-button', $qunitFixture ).length,
		0,
		'No IP reveal button added'
	);
} );
QUnit.test( 'Test that Special:AbuseLog entries for temp accounts get IP Reveal buttons added', ( assert ) => {
	mw.storage.remove( 'mw-checkuser-temp-~1' );

	const $tempUserLink = $( '<a>' )
		.attr( 'href', '/wiki/Special:Contributions/~mw-checkuser-temp-~1' )
		.attr( 'class', 'mw-userlink mw-tempuserlink' )
		.attr( 'title', 'Special:Contributions/mw-checkuser-temp-~1' )
		.attr( 'data-mw-target', 'mw-checkuser-temp-~1' );
	const $nonTempUserLink = $( '<a>' )
		.attr( 'href', '/wiki/User:username' )
		.attr( 'class', 'mw-userlink' )
		.attr( 'title', 'User:username' );

	$tempUserLink.append(
		$( '<bdi>' ).append( 'mw-checkuser-temp-~1' )
	);
	$nonTempUserLink.append(
		$( '<bdi>' ).append( 'username' )
	);

	const $logEntry1 = $( '<li>' ).attr( 'data-afl-log-id', 10 );
	$logEntry1.append( '08:38, 22 July 2025' );
	$logEntry1.append( $tempUserLink );
	$logEntry1.append( $( '<bdi>' ).text( '~2025-1' ) );

	const $logEntry2 = $( '<li>' ).attr( 'data-afl-log-id', 9 );
	$logEntry2.append( '08:38, 22 July 2025' );
	$logEntry2.append( $nonTempUserLink );
	$logEntry2.append( $( '<bdi>' ).text( 'username' ) );

	// eslint-disable-next-line no-jquery/no-global-selector
	const $qunitFixture = $( '#qunit-fixture' );
	const $wrapper = $( '<div>', $qunitFixture );
	$wrapper.attr( 'id', 'bodyContent' );

	const $form = $( '<form>', $wrapper );

	const $contributionsList = $( '<ul>', $form );
	$contributionsList.attr( 'class', 'plainlinks' );
	$contributionsList.append( $logEntry1 );
	$contributionsList.append( $logEntry2 );

	$form.append( $contributionsList );
	$wrapper.append( $form );
	$qunitFixture.append( $wrapper );

	abuseLog.onLoad( $qunitFixture );

	assert.strictEqual(
		$( '.ext-checkuser-tempaccount-reveal-ip-button', $qunitFixture ).length,
		1,
		'An IP reveal button gets added for the temp user'
	);

	assert.strictEqual(
		// eslint-disable-next-line no-jquery/no-class-state
		$qunitFixture.find( '.mw-tempuserlink' ).next()
			.hasClass( 'ext-checkuser-tempaccount-reveal-ip-button' ),
		true,
		'IP reveal button is added after the temp user link'
	);

	assert.strictEqual(
		$qunitFixture.find( '.mw-userlink:not(.mw-tempuserlink)' ).length,
		1,
		'The non-temp userlink selector matches the non-temp user'
	);
	assert.strictEqual(
		// eslint-disable-next-line no-jquery/no-class-state
		$qunitFixture.find( '.mw-userlink:not(.mw-tempuserlink)' ).next()
			.hasClass( 'ext-checkuser-tempaccount-reveal-ip-button' ),
		false,
		'IP reveal button is not added after the non-temp user link'
	);
} );
