'use strict';

/*!
 * VisualEditor DataModel Cite-specific Transaction tests.
 *
 * @copyright 2011-2018 VisualEditor Team's Cite sub-team and others; see AUTHORS.txt
 * @license MIT
 */
{
	QUnit.module( 've.dm.Transaction (Cite)', ve.test.utils.newMwEnvironment() );

	// FIXME: Duplicates test runner; should be using a data provider
	QUnit.test( 'newFromDocumentInsertion with references', ( assert ) => {
		// Fixtures
		const complexDoc = ve.dm.citeExample.createExampleDocument( 'complexInternalData' );
		const simpleRefDocData = ve.dm.citeExample.simpleRef;

		// Test cases
		const cases = [
			{
				msg: 'inserting a brand new document; internal lists are merged and items renumbered',
				doc: 'complexInternalData',
				offset: 7,
				newDocData: simpleRefDocData,
				expectedOps: [
					{ type: 'retain', length: 7 },
					{
						type: 'replace',
						remove: [],
						insert: simpleRefDocData.slice( 0, 4 )
							// Renumber listIndex from 0 to 2
							// Renumber listKey from auto/0 to auto/1
							.concat( [
								ve.extendObject( true, {}, simpleRefDocData[ 4 ],
									{ attributes: { listIndex: 2, listKey: 'auto/1' } }
								)
							] )
							.concat( simpleRefDocData.slice( 5, 7 ) )
					},
					{ type: 'retain', length: 1 },
					{
						type: 'replace',
						remove: complexDoc.getData( new ve.Range( 8, 32 ) ),
						insert: complexDoc.getData( new ve.Range( 8, 32 ) )
							.concat( simpleRefDocData.slice( 8, 15 ) )
					},
					{ type: 'retain', length: 1 }
				]
			}
		];

		// Test runner
		cases.forEach( ( caseItem ) => {
			const originalDoc = ve.dm.citeExample.createExampleDocument( caseItem.doc );
			let otherDoc;
			if ( caseItem.newDocData ) {
				otherDoc = new ve.dm.Document( caseItem.newDocData );
			}

			// Build transaction
			const tx = ve.dm.TransactionBuilder.static.newFromDocumentInsertion(
				originalDoc, caseItem.offset, otherDoc
			);

			assert.deepEqualWithDomElements(
				tx.getOperations(), caseItem.expectedOps, caseItem.msg + ': transaction'
			);
		} );
	} );
}
