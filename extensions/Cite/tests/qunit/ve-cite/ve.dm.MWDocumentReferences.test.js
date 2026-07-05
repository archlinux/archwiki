'use strict';

{
	const { MWDocumentReferences } = require( 'ext.cite.visualEditor' ).test;

	QUnit.module( 've.dm.MWDocumentReferences (Cite)', ve.test.utils.newMwEnvironment() );

	QUnit.test( 'first simple test', ( assert ) => {
		const doc = ve.dm.citeExample.createExampleDocument( 'references' );
		const docRefs = MWDocumentReferences.static.refsForDoc( doc );

		const group = docRefs.getGroupRefs( 'mwReference/' );
		assert.strictEqual( group.getIndexLabel( 0 ), '1' );
		assert.strictEqual( group.getIndexLabel( 1 ), '2' );
		assert.strictEqual( group.getIndexLabel( 2 ), '3' );
		assert.strictEqual( group.getIndexLabel( 3 ), '4' );
		assert.strictEqual( docRefs.getGroupRefs( 'mwReference/foo' ).getIndexLabel( 4 ), '1' );

		const doesNotExist = -1;
		assert.strictEqual( group.getIndexLabel( doesNotExist ), '…' );
	} );

	QUnit.test( 'sub-references', ( assert ) => {
		const doc = ve.dm.citeExample.createExampleDocument( 'subReferencing' );
		const docRefs = MWDocumentReferences.static.refsForDoc( doc );

		const group = docRefs.getGroupRefs( 'mwReference/' );
		assert.strictEqual( group.getIndexLabel( 0 ), '1.1' );
		assert.strictEqual( group.getIndexLabel( 2 ), '2' );
		assert.strictEqual( group.getIndexLabel( 3 ), '3.1' );
		assert.strictEqual( group.getIndexLabel( 1 ), '1' );
	} );
}
