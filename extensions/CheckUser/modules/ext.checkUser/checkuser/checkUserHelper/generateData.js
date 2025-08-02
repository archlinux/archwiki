// Licence: GPLv3 & GPLv2 (dual licensed)
// Original source: https://github.com/Ladsgroup/CheckUserHelper
'use strict';

const Utils = require( './utils.js' );

/**
 * Generates a dictionary of data that summarises the
 * results from Special:CheckUser.
 *
 * @return {Promise<Object.<string,{ip: {}, ua: {}, uach: {}, sorted:
 *   {ip: string[], ua: string[], uach: string[]}}>>}
 */
function generateData() {
	const $checkUserResults = $( '#checkuserresults li' );
	function processRows( data, currentPosition ) {
		let endPosition = currentPosition + 50;
		if ( endPosition > $checkUserResults.length ) {
			endPosition = $checkUserResults.length;
		}
		$checkUserResults.slice( currentPosition, endPosition ).each( function () {
			let user = $( '.mw-checkuser-user-link', this ).data( 'username' );
			if ( !user ) {
				return;
			}

			user = user.trim();
			if ( !data[ user ] ) {
				data[ user ] = {
					ip: {},
					ua: {},
					uach: {},
					linkUserPage: false,
					classes: null
				};
			}
			// Only link the userpage in the summary table if it was linked in the results.
			const linkUserPage = $( '.mw-checkuser-user-link', this ).has( 'a' ).length > 0;
			if ( !data[ user ].linkUserPage && linkUserPage ) {
				data[ user ].linkUserPage = true;

				/* eslint-disable no-jquery/no-class-state */
				const $mwUserLink = $( '.mw-checkuser-user-link .mw-userlink', this );
				if ( $mwUserLink.hasClass( 'mw-tempuserlink-expired' ) ) {
					data[ user ].classes = 'mw-userlink mw-tempuserlink mw-tempuserlink-expired';
				} else if ( $mwUserLink.hasClass( 'mw-tempuserlink' ) ) {
					data[ user ].classes = 'mw-userlink mw-tempuserlink';
				} else {
					data[ user ].classes = 'mw-userlink';
				}
				/* eslint-enable no-jquery/no-class-state */
			}

			$( '.mw-checkuser-agent', this ).each( function () {
				const uaText = $( this ).text().trim();
				if ( uaText !== '' ) {
					data[ user ].ua[ uaText ] = data[ user ].ua[ uaText ] || 0;
					data[ user ].ua[ uaText ] += 1;
				}
			} );
			$( '.mw-checkuser-client-hints', this ).each( function () {
				const clientHintsText = $( this ).text().trim();
				if ( clientHintsText !== '' ) {
					data[ user ].uach[ clientHintsText ] =
						data[ user ].uach[ clientHintsText ] || 0;
					data[ user ].uach[ clientHintsText ] += 1;
				}
			} );
			$( '.mw-checkuser-ip', this ).each( function () {
				const ipText = $( this ).text().trim();
				let $xff;
				let xffTrusted;
				if ( $( this ).is( 'li' ) ) {
					$xff = $( '.mw-checkuser-xff', this );
				} else {
					$xff = $( this ).closest( 'li' ).find( '.mw-checkuser-xff' );
				}
				// eslint-disable-next-line no-jquery/no-class-state
				if ( $xff.hasClass( 'mw-checkuser-xff-trusted' ) ) {
					xffTrusted = 'true';
				} else {
					xffTrusted = 'false';
				}
				const xffText = $xff.text().trim();
				if ( ipText !== '' ) {
					if ( !data[ user ].ip[ ipText ] ) {
						data[ user ].ip[ ipText ] = {};
					}
					if ( !data[ user ].ip[ ipText ][ xffText ] ) {
						data[ user ].ip[ ipText ][ xffText ] = {};
					}
					data[ user ].ip[ ipText ][ xffText ][ xffTrusted ] =
						data[ user ].ip[ ipText ][ xffText ][ xffTrusted ] || 0;
					data[ user ].ip[ ipText ][ xffText ][ xffTrusted ] += 1;
				}
			} );
		} );
		currentPosition = endPosition;
		if ( currentPosition < $checkUserResults.length ) {
			return new Promise(
				( resolve ) => {
					// Wait a bit to prevent UI freeze.
					setTimeout( () => {
						processRows( data, currentPosition )
							.then( ( dataFromChild ) => {
								resolve( dataFromChild );
							} );
					}, 10 );
				}
			);
		} else {
			if ( Object.keys( data ).length === 0 ) {
				return Promise.resolve( data );
			}
			// sort IPs and UAs
			// eslint-disable-next-line
			$.each( data, function ( user ) {
				const ip = Object.keys( data[ user ].ip );
				ip.sort( Utils.compareIPs );
				const ua = Object.keys( data[ user ].ua );
				ua.sort(); // NOSONAR
				const uach = Object.keys( data[ user ].uach );
				uach.sort(); // NOSONAR
				data[ user ].sorted = {
					ip: ip,
					ua: ua,
					uach: uach
				};
			} );
			return Promise.resolve( data );
		}
	}

	return processRows( {}, 0 );
}

module.exports = generateData;
