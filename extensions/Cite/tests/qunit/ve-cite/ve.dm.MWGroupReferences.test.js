'use strict';

QUnit.module( 've.dm.MWGroupReferences (Cite)', ve.test.utils.newMwEnvironment( {
	beforeEach: function () {
		const doc = ve.dm.citeExample.createExampleDocument( 'references' );
		const docRefs = ve.dm.MWDocumentReferences.static.refsForDoc( doc );
		this.plainGroupRefs = docRefs.getGroupRefs( '' );
		this.fooGroupRefs = docRefs.getGroupRefs( 'foo' );
		this.emptyGroupRefs = docRefs.getGroupRefs( 'doesnotexist' );
	}
} ) );

QUnit.test( 'isEmpty', function ( assert ) {
	assert.false( this.plainGroupRefs.isEmpty() );
	assert.false( this.fooGroupRefs.isEmpty() );
	assert.true( this.emptyGroupRefs.isEmpty() );
} );

QUnit.test( 'getAllRefsInReflistOrder', function ( assert ) {
	assert.deepEqual(
		this.plainGroupRefs.getAllRefsInReflistOrder().map( ( node ) => node.getAttribute( 'listKey' ) ),
		[
			'auto/0',
			'literal/bar',
			'literal/:3',
			'auto/1'
		]
	);
	assert.deepEqual(
		this.fooGroupRefs.getAllRefsInReflistOrder().map( ( node ) => node.getAttribute( 'listKey' ) ),
		[
			'auto/2'
		]
	);
	assert.deepEqual( this.emptyGroupRefs.getAllRefsInReflistOrder(), [] );
} );

QUnit.test( 'getTopLevelKeysInReflistOrder', function ( assert ) {
	assert.deepEqual(
		this.plainGroupRefs.getTopLevelKeysInReflistOrder(),
		[
			'auto/0',
			'literal/bar',
			'literal/:3',
			'auto/1'
		]
	);
	assert.deepEqual(
		this.fooGroupRefs.getTopLevelKeysInReflistOrder(),
		[
			'auto/2'
		]
	);
	assert.deepEqual( this.emptyGroupRefs.getTopLevelKeysInReflistOrder(), [] );
} );

QUnit.test( 'getRefNode', function ( assert ) {
	assert.strictEqual( this.plainGroupRefs.getRefNode( 'auto/0' ).getAttribute( 'listKey' ), 'auto/0' );
	assert.strictEqual( this.plainGroupRefs.getRefNode( 'doesnotexist' ), undefined );
} );

QUnit.test( 'getInternalModelNode', function ( assert ) {
	// TODO: assert something that makes sense
	// assert.strictEqual( this.plainGroupRefs.getInternalModelNode( 'auto/0' ), undefined );
	assert.strictEqual( this.plainGroupRefs.getInternalModelNode( 'doesnotexist' ), undefined );
} );

QUnit.test( 'getRefUsages', function ( assert ) {
	assert.deepEqual(
		this.plainGroupRefs.getRefUsages( 'literal/bar' ).map( ( node ) => node.getAttribute( 'listKey' ) ),
		[
			'literal/bar',
			'literal/bar'
		]
	);
	assert.deepEqual( this.plainGroupRefs.getRefUsages( 'doesnotexist' ), [] );
} );

QUnit.test( 'getTotalUsageCount', function ( assert ) {
	const mockListKey = 'literal/bar';

	// The total usage count should be the sum of main refs and subrefs
	assert.strictEqual(
		this.plainGroupRefs.getTotalUsageCount( mockListKey ),
		this.plainGroupRefs.getRefUsages( mockListKey ).length +
			this.plainGroupRefs.getSubrefs( mockListKey ).length
	);
} );

QUnit.test( 'sub-references', ( assert ) => {
	const subRefDoc = ve.dm.citeExample.createExampleDocument( 'subReferencing' );
	const groupRefs = ve.dm.MWDocumentReferences.static.refsForDoc( subRefDoc ).getGroupRefs( '' );

	assert.deepEqual(
		groupRefs.getAllRefsInReflistOrder().map( ( node ) => node.getAttribute( 'listKey' ) ),
		[
			'literal/ldr',
			'auto/0',
			'auto/1',
			'literal/orphaned'
		]
	);

	assert.deepEqual(
		groupRefs.getTopLevelKeysInReflistOrder(),
		[
			'literal/ldr',
			'auto/1',
			'literal/nonexistent'
		]
	);

	assert.deepEqual(
		groupRefs.getRefUsages( 'auto/0' ).map( ( node ) => node.getAttribute( 'listKey' ) ),
		[
			'auto/0'
		]
	);

	assert.deepEqual(
		groupRefs.getSubrefs( 'literal/ldr' ).map( ( node ) => node.getAttribute( 'listKey' ) ),
		[
			'auto/0'
		]
	);
} );
