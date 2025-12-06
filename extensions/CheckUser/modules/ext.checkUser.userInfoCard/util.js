'use strict';

/**
 * Process edit count data to include only the last 60 days and fill in missing dates with 0
 *
 * @param {Object} editCountByDay Raw edit count data from API { "YYYY-MM-DD": count, ... }
 * @return {Object} Object with processed edit count data and total edit count
 *   { processedData: [{ date: Date, count: number }], totalEdits: number }
 */
function processEditCountByDay( editCountByDay ) {
	const rawData = editCountByDay || {};

	// Calculate 60 days ago using native Date
	const sixtyDaysAgo = new Date();
	sixtyDaysAgo.setDate( sixtyDaysAgo.getDate() - 60 );

	const processedData = [];
	let totalEdits = 0;

	for ( let i = 0; i <= 60; i++ ) {
		// Create a new date for each day
		const date = new Date( sixtyDaysAgo );
		date.setDate( sixtyDaysAgo.getDate() + i );

		// Format date as YYYY-MM-DD using native methods
		const year = date.getFullYear();
		const month = String( date.getMonth() + 1 ).padStart( 2, '0' );
		const day = String( date.getDate() ).padStart( 2, '0' );
		const dateStr = `${ year }-${ month }-${ day }`;

		if ( rawData[ dateStr ] ) {
			processedData.push( { date: date, count: rawData[ dateStr ] } );
			totalEdits += rawData[ dateStr ];
		} else {
			processedData.push( { date: date, count: 0 } );
		}
	}

	return { processedData, totalEdits };
}

/**
 * Parse MediaWiki timestamp format (YYYYMMDDHHmmss) to JavaScript Date
 *
 * @param {string} timestamp MediaWiki timestamp in format YYYYMMDDHHmmss
 * @return {Date|null} JavaScript Date object or null if invalid
 */
function parseMediaWikiTimestamp( timestamp ) {
	if ( !timestamp || timestamp.length !== 14 ) {
		return null;
	}

	const year = parseInt( timestamp.slice( 0, 4 ), 10 );
	const month = parseInt( timestamp.slice( 4, 6 ), 10 ) - 1; // Month is 0-indexed
	const day = parseInt( timestamp.slice( 6, 8 ), 10 );
	const hour = parseInt( timestamp.slice( 8, 10 ), 10 );
	const minute = parseInt( timestamp.slice( 10, 12 ), 10 );
	const second = parseInt( timestamp.slice( 12, 14 ), 10 );

	// This creates a Date object with UTC value as if it was in the local timezone
	const dateUtc = new Date( year, month, day, hour, minute, second );
	const offsetMins = dateUtc.getTimezoneOffset();
	dateUtc.setHours( dateUtc.getHours(), dateUtc.getMinutes() - offsetMins );
	return dateUtc;
}

/**
 * Provides a hash string for a given username suitable to be used for
 * component IDs. This is required because usernames may contain whitespaces,
 * but HTML component IDs cannot.
 *
 * This function is an adaptation of generateHashId() from Codex.
 *
 * @param {?string} username Username to hash
 * @return {string} The hash as a string
 */
function hashUsername( username ) {
	if ( username === null || username === '' ) {
		return '';
	}

	const mask = 4294967295;

	/* eslint-disable no-bitwise */
	let numericHash = Array.from( username ).reduce(
		( acc, char ) => acc * 31 + char.charCodeAt( 0 ) & mask,
		0
	);

	numericHash = numericHash >>> 0;
	/* eslint-enable no-bitwise */

	return numericHash.toString( 36 );
}

module.exports = {
	processEditCountByDay,
	parseMediaWikiTimestamp,
	hashUsername
};
