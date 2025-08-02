// Licence: GPLv3 & GPLv2 (dual licensed)
// Original source: https://github.com/Ladsgroup/CheckUserHelper
'use strict';

const buildUserElement = require( './buildUserElement.js' );

/**
 * Adds table rows generated from the dictionary provided in the data parameter
 * to the summary table shown inside the collapse layout with the
 * label "See results in a table".
 *
 * The generated rows have columns where the first column is the associated user,
 * the second column is the IPs used and the third column is the User-Agent strings
 * used.
 *
 * This function fires the 'wikipage.content' hook on the summary table after the
 * rows have been added.
 *
 * @param {Object.<string, {
 *          ip: {},
 *          ua: {},
 *          sorted: {ip: string[], ua: string[]},
 *          linkUserPage: boolean,
 *          classes: string
 *        }>} data The result of generateData
 * @param {boolean} showCounts Whether to show the number of times each IP and
 *    User-Agent is used for a particular user.
 */
function createTable( data, showCounts ) {
	const tbl = document.getElementsByClassName( 'mw-checkuser-helper-table' ).item( 0 );
	if ( !tbl ) {
		return;
	}
	let user;
	for ( user in data ) {
		const userData = data[ user ];
		const tr = tbl.insertRow();

		let td = tr.insertCell();
		td.appendChild( buildUserElement( user, userData ) );

		const ips = document.createElement( 'ul' );
		ips.className = 'mw-checkuser-helper-ips';
		for ( let i = 0, len = userData.sorted.ip.length; i < len; i++ ) {
			const ipText = userData.sorted.ip[ i ];
			const xffs = Object.keys( userData.ip[ ipText ] );
			for ( let j = 0, xffLen = xffs.length; j < xffLen; j++ ) {
				const xffText = xffs[ j ];
				const xffTypes = Object.keys( userData.ip[ ipText ][ xffText ] );
				for ( let k = 0, xffTypesLen = xffTypes.length; k < xffTypesLen; k++ ) {
					const xffTrusted = xffTypes[ k ];
					const ip = document.createElement( 'li' );
					const ipElement = document.createElement( 'a' );
					ipElement.setAttribute(
						'href',
						mw.util.getUrl( 'Special:Contributions/' + ipText )
					);
					ipElement.textContent = ipText;
					ip.appendChild( ipElement );
					if ( xffText !== '' ) {
						const xffPrefix = document.createElement( 'span' );
						if ( xffTrusted === 'true' ) {
							xffPrefix.textContent = ' ' +
								mw.message( 'checkuser-helper-xff-trusted' ) + ' ';
						} else {
							xffPrefix.textContent = ' ' +
								mw.message( 'checkuser-helper-xff-untrusted' ) + ' ';
						}
						const xff = document.createElement( 'span' );
						xff.textContent = xffText;
						ip.appendChild( xffPrefix );
						ip.appendChild( xff );
					}
					if ( showCounts ) {
						const counter = document.createElement( 'span' );
						counter.className = 'mw-checkuser-helper-count';
						counter.textContent =
							userData.ip[ ipText ][ xffText ][ xffTrusted ];
						ip.appendChild( counter );
					}
					ips.appendChild( ip );
				}
			}
		}
		td = tr.insertCell();
		td.appendChild( ips );

		const uas = document.createElement( 'ul' );
		uas.className = 'mw-checkuser-helper-user-agents';
		for ( let i = 0, len = userData.sorted.ua.length; i < len; i++ ) {
			const uaText = userData.sorted.ua[ i ];
			const ua = document.createElement( 'li' );
			ua.textContent = uaText;
			if ( showCounts ) {
				const counter = document.createElement( 'span' );
				counter.className = 'mw-checkuser-helper-count';
				counter.textContent = userData.ua[ uaText ];
				ua.append( counter );
			}
			uas.appendChild( ua );
		}
		td = tr.insertCell();
		td.appendChild( uas );

		if ( mw.config.get( 'wgCheckUserDisplayClientHints' ) ) {
			const clientHints = document.createElement( 'ul' );
			clientHints.className = 'mw-checkuser-helper-client-hints';
			for ( let i = 0, len = userData.sorted.uach.length; i < len; i++ ) {
				const clientHintText = userData.sorted.uach[ i ];
				const clientHint = document.createElement( 'li' );
				clientHint.textContent = clientHintText;
				if ( showCounts ) {
					const counter = document.createElement( 'span' );
					counter.className = 'mw-checkuser-helper-count';
					counter.textContent = userData.uach[ clientHintText ];
					clientHint.append( counter );
				}
				clientHints.appendChild( clientHint );
			}
			td = tr.insertCell();
			td.appendChild( clientHints );
		}
	}
	mw.hook( 'wikipage.content' ).fire( $( '.mw-checkuser-helper-table' ) );
}

module.exports = createTable;
