'use strict';

{
	const {
		MWReferenceModel,
		MWReferenceSearchWidget
	} = require( 'ext.cite.visualEditor' ).test;

	const emptyDoc = ve.dm.example.createExampleDocumentFromData( [
		{ type: 'paragraph' },
		{ type: '/paragraph' },
		{ type: 'internalList' },
		{ type: '/internalList' }
	] );
	const subRefDoc = ve.dm.citeExample.createExampleDocument( 'subReferencing' );

	QUnit.module( 've.ui.MWReferenceSearchWidget (Cite)', ve.test.utils.newMwEnvironment() );

	QUnit.test( 'buildIndex', ( assert ) => {
		const widget = new MWReferenceSearchWidget();
		widget.setInternalList( emptyDoc.getInternalList() );

		assert.strictEqual( widget.index, null );
		widget.buildIndex();
		assert.deepEqual( widget.index, [] );
	} );

	QUnit.test( 'buildSearchIndex when empty', ( assert ) => {
		const widget = new MWReferenceSearchWidget();
		widget.setInternalList( emptyDoc.getInternalList() );

		const index = widget.buildSearchIndex();
		assert.deepEqual( index, [] );
	} );

	QUnit.test( 'buildSearchIndex', ( assert ) => {
		const widget = new MWReferenceSearchWidget();
		widget.setInternalList( subRefDoc.getInternalList() );

		const index = widget.buildSearchIndex();
		assert.strictEqual( index.length, 5 );

		// normal ref
		assert.strictEqual( index[ 0 ].footnoteLabel, '1' );
		assert.strictEqual( index[ 0 ].name, 'ldr' );
		assert.strictEqual( index[ 0 ].searchableText, '[1] ldr list-defined' );
		assert.strictEqual( index[ 0 ].reference.getListKey(), 'literal/ldr' );

		// sub-ref
		assert.strictEqual( index[ 1 ].footnoteLabel, '1.1' );
		assert.strictEqual( index[ 1 ].name, '' );
		assert.strictEqual( index[ 1 ].searchableText, '[1.1] subref' );
		assert.strictEqual( index[ 1 ].reference.getListKey(), 'auto/0' );

		// main ref without a MWReferenceNode
		assert.strictEqual( index[ 3 ].footnoteLabel, '3' );
		assert.strictEqual( index[ 3 ].name, 'nonexistent' );
		assert.strictEqual( index[ 3 ].searchableText, '[3] nonexistent main no node' );
		assert.strictEqual( index[ 3 ].reference.getListKey(), 'literal/nonexistent' );
	} );

	QUnit.test( 'isIndexEmpty', ( assert ) => {
		const widget = new MWReferenceSearchWidget();
		widget.setInternalList( emptyDoc.getInternalList() );
		assert.true( widget.isIndexEmpty() );

		widget.setInternalList( subRefDoc.getInternalList() );
		assert.false( widget.isIndexEmpty() );
	} );

	QUnit.test( 'buildSearchResults', ( assert ) => {
		const widget = new MWReferenceSearchWidget();
		const reference = new MWReferenceModel();
		widget.index = [ { searchableText: 'a', reference }, { searchableText: 'b' } ];

		assert.strictEqual( widget.getResults().getItemCount(), 0 );
		const results = widget.buildSearchResults( 'A' );
		assert.strictEqual( results.length, 1 );
		assert.strictEqual( results[ 0 ].getData(), reference );
	} );
}
