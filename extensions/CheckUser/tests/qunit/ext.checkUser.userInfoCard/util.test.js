'use strict';

const {
	processEditCountByDay,
	parseMediaWikiTimestamp
} = require( 'ext.checkUser.userInfoCard/modules/ext.checkUser.userInfoCard/util.js' );

QUnit.module( 'ext.checkUser.userInfoCard.util', QUnit.newMwEnvironment() );

QUnit.test( 'processEditCountByDay handles empty input data', ( assert ) => {
	const result = processEditCountByDay( {} );

	assert.strictEqual( result.totalEdits, 0, 'Total edits should be 0 for empty data' );
	assert.strictEqual( result.processedData.length, 61, 'Should return 61 days of data (0-60 inclusive)' );

	// All entries should have count: 0
	result.processedData.forEach( ( entry, index ) => {
		assert.strictEqual( entry.count, 0, `Day ${ index } should have count 0` );
		assert.true( entry.date instanceof Date, `Day ${ index } should have a valid Date object` );
	} );
} );

QUnit.test( 'processEditCountByDay handles null/undefined input data', ( assert ) => {
	const resultNull = processEditCountByDay( null );
	const resultUndefined = processEditCountByDay( undefined );

	assert.strictEqual( resultNull.totalEdits, 0, 'Total edits should be 0 for null data' );
	assert.strictEqual( resultNull.processedData.length, 61, 'Should return 61 days for null data' );

	assert.strictEqual( resultUndefined.totalEdits, 0, 'Total edits should be 0 for undefined data' );
	assert.strictEqual( resultUndefined.processedData.length, 61, 'Should return 61 days for undefined data' );
} );

QUnit.test( 'processEditCountByDay processes partial data correctly', ( assert ) => {
	// Create test data for today and yesterday
	const today = new Date();
	const yesterday = new Date( today );
	yesterday.setDate( today.getDate() - 1 );

	const todayStr = `${ today.getFullYear() }-${ String( today.getMonth() + 1 ).padStart( 2, '0' ) }-${ String( today.getDate() ).padStart( 2, '0' ) }`;
	const yesterdayStr = `${ yesterday.getFullYear() }-${ String( yesterday.getMonth() + 1 ).padStart( 2, '0' ) }-${ String( yesterday.getDate() ).padStart( 2, '0' ) }`;

	const testData = {};
	testData[ todayStr ] = 5;
	testData[ yesterdayStr ] = 3;

	const result = processEditCountByDay( testData );

	assert.strictEqual( result.totalEdits, 8, 'Total edits should be sum of provided data' );
	assert.strictEqual( result.processedData.length, 61, 'Should return 61 days of data' );

	// Check that some days have the expected counts and others have 0
	let foundTodayCount = false;
	let foundYesterdayCount = false;
	let zeroCountDays = 0;

	result.processedData.forEach( ( entry ) => {
		const entryDateStr = `${ entry.date.getFullYear() }-${ String( entry.date.getMonth() + 1 ).padStart( 2, '0' ) }-${ String( entry.date.getDate() ).padStart( 2, '0' ) }`;

		if ( entryDateStr === todayStr ) {
			assert.strictEqual( entry.count, 5, 'Today should have count 5' );
			foundTodayCount = true;
		} else if ( entryDateStr === yesterdayStr ) {
			assert.strictEqual( entry.count, 3, 'Yesterday should have count 3' );
			foundYesterdayCount = true;
		} else {
			assert.strictEqual( entry.count, 0, `Day ${ entryDateStr } should have count 0` );
			zeroCountDays++;
		}
	} );

	assert.true( foundTodayCount, 'Should find today\'s data in results' );
	assert.true( foundYesterdayCount, 'Should find yesterday\'s data in results' );
	assert.strictEqual( zeroCountDays, 59, 'Should have 59 days with zero count' );
} );

QUnit.test( 'processEditCountByDay processes full 60 days of data', ( assert ) => {
	// Create test data for all 61 days (0-60)
	const testData = {};
	const sixtyDaysAgo = new Date();
	sixtyDaysAgo.setDate( sixtyDaysAgo.getDate() - 60 );

	let expectedTotal = 0;
	for ( let i = 0; i <= 60; i++ ) {
		const date = new Date( sixtyDaysAgo );
		date.setDate( sixtyDaysAgo.getDate() + i );

		const dateStr = `${ date.getFullYear() }-${ String( date.getMonth() + 1 ).padStart( 2, '0' ) }-${ String( date.getDate() ).padStart( 2, '0' ) }`;
		const count = i + 1; // Use i+1 as count to make each day unique
		testData[ dateStr ] = count;
		expectedTotal += count;
	}

	const result = processEditCountByDay( testData );

	assert.strictEqual( result.totalEdits, expectedTotal, 'Total edits should match sum of all provided data' );
	assert.strictEqual( result.processedData.length, 61, 'Should return 61 days of data' );

	// Verify all days have non-zero counts
	result.processedData.forEach( ( entry, index ) => {
		assert.true( entry.count > 0, `Day ${ index } should have a positive count` );
		assert.true( entry.date instanceof Date, `Day ${ index } should have a valid Date object` );
	} );
} );

QUnit.test( 'processEditCountByDay maintains correct date sequence', ( assert ) => {
	const result = processEditCountByDay( {} );

	// Verify dates are in ascending order
	for ( let i = 1; i < result.processedData.length; i++ ) {
		const prevDate = result.processedData[ i - 1 ].date;
		const currDate = result.processedData[ i ].date;

		assert.true( currDate > prevDate, `Date at index ${ i } should be after date at index ${ i - 1 }` );

		// Verify dates are exactly one day apart
		const diffInMs = currDate.getTime() - prevDate.getTime();
		const diffInDays = diffInMs / ( 1000 * 60 * 60 * 24 );
		assert.strictEqual( Math.round( diffInDays ), 1, 'Dates should be exactly one day apart' );
	}
} );

QUnit.test( 'parseMediaWikiTimestamp parses valid MediaWiki timestamp', ( assert ) => {
	const timestamp = '20240315142530'; // March 15, 2024, 14:25:30
	const result = parseMediaWikiTimestamp( timestamp );

	assert.true( result instanceof Date, 'Should return a Date object' );

	// Undo the timezone adjustment, so that the test can pass in non-UTC environments
	result.setHours( result.getHours(), result.getMinutes() + result.getTimezoneOffset() );

	assert.strictEqual( result.getFullYear(), 2024, 'Year should be 2024' );
	assert.strictEqual( result.getMonth(), 2, 'Month should be 2 (March, 0-indexed)' );
	assert.strictEqual( result.getDate(), 15, 'Date should be 15' );
	assert.strictEqual( result.getHours(), 14, 'Hours should be 14' );
	assert.strictEqual( result.getMinutes(), 25, 'Minutes should be 25' );
	assert.strictEqual( result.getSeconds(), 30, 'Seconds should be 30' );
} );

QUnit.test( 'parseMediaWikiTimestamp parses timestamp with zeros', ( assert ) => {
	const timestamp = '20200101000000'; // January 1, 2020, 00:00:00
	const result = parseMediaWikiTimestamp( timestamp );

	assert.true( result instanceof Date, 'Should return a Date object' );

	// Undo the timezone adjustment, so that the test can pass in non-UTC environments
	result.setHours( result.getHours(), result.getMinutes() + result.getTimezoneOffset() );

	assert.strictEqual( result.getFullYear(), 2020, 'Year should be 2020' );
	assert.strictEqual( result.getMonth(), 0, 'Month should be 0 (January)' );
	assert.strictEqual( result.getDate(), 1, 'Date should be 1' );
	assert.strictEqual( result.getHours(), 0, 'Hours should be 0' );
	assert.strictEqual( result.getMinutes(), 0, 'Minutes should be 0' );
	assert.strictEqual( result.getSeconds(), 0, 'Seconds should be 0' );
} );

QUnit.test( 'parseMediaWikiTimestamp parses end of year timestamp', ( assert ) => {
	const timestamp = '20231231235959'; // December 31, 2023, 23:59:59
	const result = parseMediaWikiTimestamp( timestamp );

	assert.true( result instanceof Date, 'Should return a Date object' );

	// Undo the timezone adjustment, so that the test can pass in non-UTC environments
	result.setHours( result.getHours(), result.getMinutes() + result.getTimezoneOffset() );

	assert.strictEqual( result.getFullYear(), 2023, 'Year should be 2023' );
	assert.strictEqual( result.getMonth(), 11, 'Month should be 11 (December)' );
	assert.strictEqual( result.getDate(), 31, 'Date should be 31' );
	assert.strictEqual( result.getHours(), 23, 'Hours should be 23' );
	assert.strictEqual( result.getMinutes(), 59, 'Minutes should be 59' );
	assert.strictEqual( result.getSeconds(), 59, 'Seconds should be 59' );
} );

QUnit.test( 'parseMediaWikiTimestamp returns null for invalid timestamp length', ( assert ) => {
	assert.strictEqual( parseMediaWikiTimestamp( '2024' ), null, 'Should return null for short timestamp' );
	assert.strictEqual( parseMediaWikiTimestamp( '202403151425301' ), null, 'Should return null for long timestamp' );
	assert.strictEqual( parseMediaWikiTimestamp( '' ), null, 'Should return null for empty string' );
} );

QUnit.test( 'parseMediaWikiTimestamp returns null for null/undefined input', ( assert ) => {
	assert.strictEqual( parseMediaWikiTimestamp( null ), null, 'Should return null for null input' );
	assert.strictEqual( parseMediaWikiTimestamp( undefined ), null, 'Should return null for undefined input' );
} );

QUnit.test( 'parseMediaWikiTimestamp handles malformed timestamp gracefully', ( assert ) => {
	// Invalid month
	const invalidMonth = parseMediaWikiTimestamp( '20241315142530' );
	assert.true( invalidMonth instanceof Date, 'Should still return Date object for invalid month' );

	// Invalid day
	const invalidDay = parseMediaWikiTimestamp( '20240132142530' );
	assert.true( invalidDay instanceof Date, 'Should still return Date object for invalid day' );

	// Non-numeric characters (should still parse due to parseInt)
	const nonNumeric = parseMediaWikiTimestamp( '2024ab15142530' );
	assert.true( nonNumeric instanceof Date, 'Should handle non-numeric characters' );
} );

QUnit.test( 'parseMediaWikiTimestamp handles leap year correctly', ( assert ) => {
	const leapYear = parseMediaWikiTimestamp( '20240229120000' ); // Feb 29, 2024 (leap year)
	assert.true( leapYear instanceof Date, 'Should return Date object for leap year date' );
	assert.strictEqual( leapYear.getFullYear(), 2024, 'Year should be 2024' );
	assert.strictEqual( leapYear.getMonth(), 1, 'Month should be 1 (February)' );
	assert.strictEqual( leapYear.getDate(), 29, 'Date should be 29' );
} );
