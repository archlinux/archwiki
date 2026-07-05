'use strict';

{
	const { MWDocumentReferences } = require( 'ext.cite.visualEditor' ).test;

	QUnit.module( 've.dm.MWGroupReferences (Cite)', ve.test.utils.newMwEnvironment( {
		beforeEach: function () {
			const doc = ve.dm.citeExample.createExampleDocument( 'references' );
			const docRefs = MWDocumentReferences.static.refsForDoc( doc );
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

	QUnit.test( 'getRefUsages', function ( assert ) {
		const listIndex = 1;
		assert.deepEqual(
			this.plainGroupRefs.getRefUsages( listIndex ).map( ( node ) => node.getAttribute( 'listIndex' ) ),
			[ 1, 1 ]
		);
		const doesNotExist = -1;
		assert.deepEqual( this.plainGroupRefs.getRefUsages( doesNotExist ), [] );
	} );

	QUnit.test( 'getAllReusesByListIndex', function ( assert ) {
		assert.deepEqual(
			this.plainGroupRefs.getAllReusesByListIndex( 1 ).map( ( node ) => node.getAttribute( 'listIndex' ) ),
			[ 1, 1 ]
		);
		const doesNotExist = -1;
		assert.deepEqual( this.plainGroupRefs.getAllReusesByListIndex( doesNotExist ), [] );
	} );

	QUnit.test( 'getTotalUsageCount', function ( assert ) {
		assert.strictEqual( this.plainGroupRefs.getTotalUsageCount( 0 ), 1 );
		assert.strictEqual( this.plainGroupRefs.getTotalUsageCount( 1 ), 2 );
		assert.strictEqual( this.plainGroupRefs.getTotalUsageCount( 2 ), 1 );
		assert.strictEqual( this.plainGroupRefs.getTotalUsageCount( 3 ), 1 );
	} );

	QUnit.test( 'sub-references', ( assert ) => {
		const subRefDoc = ve.dm.citeExample.createExampleDocument( 'subReferencing' );
		const groupRefs = MWDocumentReferences.static.refsForDoc( subRefDoc ).getGroupRefs( '' );

		const listIndex = 0;
		assert.deepEqual(
			groupRefs.getRefUsages( listIndex ).map( ( node ) => node.getAttribute( 'listIndex' ) ),
			[ 0 ]
		);
	} );
}
