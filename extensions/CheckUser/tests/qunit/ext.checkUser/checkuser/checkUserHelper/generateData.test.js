'use strict';

const generateData = require( '../../../../../modules/ext.checkUser/checkuser/checkUserHelper/generateData.js' );

QUnit.module( 'ext.checkUser.checkuser.checkUserHelper.generateData', QUnit.newMwEnvironment() );

QUnit.test( 'Test that generateData returns the expected data', ( assert ) => {
	const cases = require( './cases/generateData.json' );

	cases.forEach( ( caseItem ) => {
		function performTest( clientHintsEnabled, expectedData, msg ) {
			let html = '<div id="checkuserresults"><ul>';
			caseItem.items.forEach( ( resultLine ) => {
				html += '<li>';
				html += `<span data-username="${
					resultLine.userLink }" class="mw-checkuser-user-link">`;
				if ( resultLine.linkUserPage ) {
					if ( resultLine.isExpired ) {
						html += '<a href="" class="mw-userlink mw-tempuserlink mw-tempuserlink-expired">';
					} else if ( resultLine.isTemp ) {
						html += '<a href="" class="mw-userlink mw-tempuserlink">';
					} else {
						html += '<a href="" class="mw-userlink">';
					}
				}

				html += resultLine.userLink;
				if ( resultLine.linkUserPage ) {
					html += '</a>';
				}
				html += '</span>';
				html += '<span class="mw-checkuser-agent">' + resultLine.userAgent + '</span>';
				if ( clientHintsEnabled ) {
					html += '<span class="mw-checkuser-client-hints">' + resultLine.clientHints + '</span>';
				}
				html += '<span class="mw-checkuser-ip">' + resultLine.IP + '</span>';
				if ( resultLine.XFFTrusted ) {
					html += '<span class="mw-checkuser-xff mw-checkuser-xff-trusted">';
				} else {
					html += '<span class="mw-checkuser-xff">';
				}
				html += resultLine.XFF + '</span>';
				html += '</li>';
			} );
			html += '</ul></div>';
			// eslint-disable-next-line no-jquery/no-global-selector
			$( '#qunit-fixture' ).html( html );
			generateData().then( ( data ) => {
				assert.deepEqual(
					data,
					expectedData,
					msg
				);
			} );
		}

		mw.config.set( 'wgCheckUserDisplayClientHints', false );
		performTest( false, caseItem.expectedData, caseItem.msg + '.' );

		mw.config.set( 'wgCheckUserDisplayClientHints', true );
		performTest(
			true,
			caseItem.expectedDataWhenClientHintsEnabled,
			caseItem.msg + ' with Client Hints display enabled.'
		);
	} );
} );
