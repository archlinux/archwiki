// Licence: GPLv3 & GPLv2 (dual licensed)
// Original source: https://github.com/Ladsgroup/CheckUserHelper
'use strict';

/**
 * Compare IP addresses in a way that is
 * compatible with Array.sort.
 *
 * @param {string} a The first IP
 * @param {string} b The second IP
 * @return {number} An integer that can be used by Array.sort
 */
function compareIPs( a, b ) {
	return calculateIPNumber( a ) - calculateIPNumber( b );
}

/**
 * Converts a string IP address to an integer
 * representation such that IP addresses can
 * be numerically compared.
 *
 * @param {string} ip
 * @return {number}
 */
function calculateIPNumber( ip ) {
	return ip.includes( '.' ) ?
		Number(
			ip.split( '.' ).map(
				( num ) => ( '000' + num ).slice( -3 )
			).join( '' )
		) : Number(
			'0x' + ip.split( ':' ).map(
				( num ) => ( '0000' + num ).slice( -4 )
			).join( '' )
		);
}

module.exports = {
	compareIPs: compareIPs,
	calculateIPNumber: calculateIPNumber
};
