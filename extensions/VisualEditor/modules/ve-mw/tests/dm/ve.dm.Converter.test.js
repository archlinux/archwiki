/*!
 * VisualEditor DataModel MediaWiki-specific Converter tests.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

QUnit.module( 've.dm.Converter (MW)', ve.test.utils.newMwEnvironment() );

QUnit.test( 'getModelFromDom', ( assert ) => {
	const cases = ve.dm.mwExample.domToDataCases;

	for ( const msg in cases ) {
		const caseItem = ve.copy( cases[ msg ] );
		if ( caseItem.mwConfig ) {
			mw.config.set( caseItem.mwConfig );
		}

		ve.test.utils.runGetModelFromDomTest( assert, caseItem, msg );
	}
} );

QUnit.test( 'getDomFromModel', ( assert ) => {
	const cases = ve.dm.mwExample.domToDataCases;

	for ( const msg in cases ) {
		const caseItem = ve.copy( cases[ msg ] );
		if ( caseItem.mwConfig ) {
			mw.config.set( caseItem.mwConfig );
		}

		ve.test.utils.runGetDomFromModelTest( assert, caseItem, msg );
	}
} );
