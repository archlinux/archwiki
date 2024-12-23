'use strict';

( function () {
	QUnit.module( 've.dm.MWGroupReferences (Cite)', ve.test.utils.newMwEnvironment() );

	const doc = ve.dm.citeExample.createExampleDocument( 'references' );
	const docRefs = ve.dm.MWDocumentReferences.static.refsForDoc( doc );
	const plainGroupRefs = docRefs.getGroupRefs( '' );
	const fooGroupRefs = docRefs.getGroupRefs( 'foo' );
	const emptyGroupRefs = docRefs.getGroupRefs( 'doesnotexist' );

	QUnit.test( 'isEmpty', ( assert ) => {
		assert.false( plainGroupRefs.isEmpty() );
		assert.false( fooGroupRefs.isEmpty() );
		assert.true( emptyGroupRefs.isEmpty() );
	} );

	QUnit.test( 'getAllRefsInDocumentOrder', ( assert ) => {
		assert.deepEqual(
			plainGroupRefs.getAllRefsInDocumentOrder().map( ( node ) => node.getAttribute( 'listKey' ) ),
			[
				'auto/0',
				'literal/bar',
				'literal/:3',
				'auto/1'
			]
		);
		assert.deepEqual(
			fooGroupRefs.getAllRefsInDocumentOrder().map( ( node ) => node.getAttribute( 'listKey' ) ),
			[
				'auto/2'
			]
		);
		assert.deepEqual( emptyGroupRefs.getAllRefsInDocumentOrder(), [] );
	} );

	QUnit.test( 'getTopLevelKeysInReflistOrder', ( assert ) => {
		assert.deepEqual(
			plainGroupRefs.getTopLevelKeysInReflistOrder(),
			[
				'auto/0',
				'literal/bar',
				'literal/:3',
				'auto/1'
			]
		);
		assert.deepEqual(
			fooGroupRefs.getTopLevelKeysInReflistOrder(),
			[
				'auto/2'
			]
		);
		assert.deepEqual( emptyGroupRefs.getTopLevelKeysInReflistOrder(), [] );
	} );

	QUnit.test( 'getRefNode', ( assert ) => {
		assert.strictEqual( plainGroupRefs.getRefNode( 'auto/0' ).getAttribute( 'listKey' ), 'auto/0' );
		assert.strictEqual( plainGroupRefs.getRefNode( 'doesnotexist' ), undefined );
	} );

	QUnit.test( 'getInternalModelNode', ( assert ) => {
		// TODO: assert something that makes sense
		// assert.strictEqual( plainGroupRefs.getInternalModelNode( 'auto/0' ), undefined );
		assert.strictEqual( plainGroupRefs.getInternalModelNode( 'doesnotexist' ), undefined );
	} );

	QUnit.test( 'getRefUsages', ( assert ) => {
		assert.deepEqual(
			plainGroupRefs.getRefUsages( 'literal/bar' ).map( ( node ) => node.getAttribute( 'listKey' ) ),
			[
				'literal/bar',
				'literal/bar'
			]
		);
		assert.deepEqual( plainGroupRefs.getRefUsages( 'doesnotexist' ), [] );
	} );

	QUnit.test( 'getTotalUsageCount', ( assert ) => {
		const mockListKey = 'literal/bar';

		// The total usage count should be the sum of main refs and subrefs
		assert.strictEqual(
			plainGroupRefs.getTotalUsageCount( mockListKey ),
			plainGroupRefs.getRefUsages( mockListKey ).length + plainGroupRefs.getSubrefs( mockListKey ).length
		);
	} );

	QUnit.test( 'sub-references', ( assert ) => {
		const extendsDoc = ve.dm.citeExample.createExampleDocument( 'extends' );
		const extendsGroupRefs = ve.dm.MWDocumentReferences.static.refsForDoc( extendsDoc ).getGroupRefs( '' );

		assert.deepEqual(
			extendsGroupRefs.getAllRefsInDocumentOrder().map( ( node ) => node.getAttribute( 'listKey' ) ),
			[
				'literal/ldr',
				'auto/0',
				'auto/1',
				'literal/orphaned'
			]
		);

		assert.deepEqual(
			extendsGroupRefs.getTopLevelKeysInReflistOrder(),
			[
				'literal/ldr',
				'auto/1',
				'literal/nonexistent'
			]
		);

		assert.deepEqual(
			extendsGroupRefs.getRefUsages( 'auto/0' ).map( ( node ) => node.getAttribute( 'listKey' ) ),
			[
				'auto/0'
			]
		);

		assert.deepEqual(
			extendsGroupRefs.getSubrefs( 'literal/ldr' ).map( ( node ) => node.getAttribute( 'listKey' ) ),
			[
				'auto/0'
			]
		);
	} );
}() );
