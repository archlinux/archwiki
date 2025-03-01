'use strict';

QUnit.module( 've.dm.MWDocumentReferences (Cite)', ve.test.utils.newMwEnvironment() );

QUnit.test( 'first simple test', ( assert ) => {
	const doc = ve.dm.citeExample.createExampleDocument( 'references' );
	const docRefs = ve.dm.MWDocumentReferences.static.refsForDoc( doc );

	assert.strictEqual( docRefs.getIndexLabel( '', 'auto/0' ), '1' );
	assert.strictEqual( docRefs.getIndexLabel( '', 'literal/bar' ), '2' );
	assert.strictEqual( docRefs.getIndexLabel( '', 'literal/:3' ), '3' );
	assert.strictEqual( docRefs.getIndexLabel( '', 'auto/1' ), '4' );
	assert.strictEqual( docRefs.getIndexLabel( 'foo', 'auto/2' ), '1' );
} );

QUnit.test( 'extends test', ( assert ) => {
	const doc = ve.dm.citeExample.createExampleDocument( 'extends' );
	const docRefs = ve.dm.MWDocumentReferences.static.refsForDoc( doc );

	assert.strictEqual( docRefs.getIndexLabel( '', 'auto/0' ), '1.1' );
	assert.strictEqual( docRefs.getIndexLabel( '', 'auto/1' ), '2' );
	assert.strictEqual( docRefs.getIndexLabel( '', 'literal/orphaned' ), '3.1' );
	assert.strictEqual( docRefs.getIndexLabel( '', 'literal/ldr' ), '1' );
} );
