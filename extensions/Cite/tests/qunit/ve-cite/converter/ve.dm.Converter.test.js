'use strict';

/*!
 * VisualEditor DataModel Cite-specific Converter tests.
 *
 * @copyright 2011-2018 VisualEditor Team's Cite sub-team and others; see AUTHORS.txt
 * @license MIT
 */

QUnit.module( 've.dm.Converter (Cite)', ve.test.utils.newMwEnvironment( {
	before: function () {
		this.prepareCases = ( caseItem ) => {
			caseItem.base = ve.dm.citeExample.baseUri;
			caseItem.mwConfig = {
				wgArticlePath: '/wiki/$1'
			};
			// TODO: Cite tests contain unescaped < in attrs, handle this upstream somehow
			caseItem.ignoreXmlWarnings = true;
			if ( caseItem.mwConfig ) {
				mw.config.set( caseItem.mwConfig );
			}
			return caseItem;
		};
	}
} ) );

QUnit.test( 'getModelFromDom', function ( assert ) {
	const cases = {
		...ve.dm.ConverterTestCases.cases,
		...ve.dm.ConverterIntegrationTestCases.cases,
		...ve.dm.ConverterSubReferenceTestCases.cases
	};

	for ( const msg in cases ) {
		const caseItem = this.prepareCases( ve.copy( cases[ msg ] ) );
		ve.test.utils.runGetModelFromDomTest( assert, caseItem, msg );
	}
} );

QUnit.test( 'getDomFromModel', function ( assert ) {
	const cases = {
		...ve.dm.ConverterTestCases.cases,
		...ve.dm.ConverterIntegrationTestCases.cases,
		...ve.dm.ConverterSubReferenceTestCases.cases
	};

	for ( const msg in cases ) {
		const caseItem = this.prepareCases( ve.copy( cases[ msg ] ) );
		ve.test.utils.runGetDomFromModelTest( assert, caseItem, msg );
	}
} );

QUnit.test( 'StoreTestCases', function ( assert ) {
	const cases = ve.dm.ConverterStoreTestCases.cases;

	for ( const msg in cases ) {
		const caseItem = this.prepareCases( ve.copy( cases[ msg ] ) );
		// FIXME: Some store cases do fail in that direction in CI see T400970.
		// ve.test.utils.runGetModelFromDomTest( assert, caseItem, msg );
		ve.test.utils.runGetDomFromModelTest( assert, caseItem, msg );
	}
} );
