'use strict';

{
	QUnit.module( 've.ui.MWReferenceSearchWidget (Cite)', ve.test.utils.newMwEnvironment() );

	/**
	 * @param {boolean} [hasNode=false]
	 * @return {ve.dm.MWDocumentReferences}
	 */
	const getDocumentReferencesMock = ( hasNode ) => {
		const listKey = 'literal/foo';
		const node = hasNode ? {
			getAttribute: ( name ) => ( { listKey }[ name ] ),
			getAttributes: () => ( {} ),
			getInternalItem: () => ( {} ),
			getDocument: () => new ve.dm.Document(),
			setGroupIndex: () => {}
		} : undefined;
		const nodeGroup = new ve.dm.InternalListNodeGroup();
		nodeGroup.appendNode( listKey, node );
		return {
			getAllGroupNames: () => [ 'mwReference/' ],
			getIndexLabel: () => '1',
			getItemNode: () => node,
			getGroupRefs: () => ve.dm.MWGroupReferences.static.makeGroupRefs( nodeGroup ),
			hasRefs: () => !!hasNode
		};
	};

	QUnit.test( 'buildIndex', ( assert ) => {
		const widget = new ve.ui.MWReferenceSearchWidget();
		widget.setDocumentRefs( getDocumentReferencesMock() );

		assert.strictEqual( widget.index, null );
		widget.buildIndex();
		assert.deepEqual( widget.index, [] );
	} );

	QUnit.test( 'buildSearchIndex when empty', ( assert ) => {
		const widget = new ve.ui.MWReferenceSearchWidget();
		widget.setDocumentRefs( getDocumentReferencesMock() );

		const index = widget.buildSearchIndex();
		assert.deepEqual( index, [] );
	} );

	QUnit.test( 'buildSearchIndex', ( assert ) => {
		const widget = new ve.ui.MWReferenceSearchWidget();
		widget.setDocumentRefs( getDocumentReferencesMock( true ) );

		const index = widget.buildSearchIndex();
		assert.deepEqual( index.length, 1 );
		assert.deepEqual( index[ 0 ].footnoteLabel, '1' );
		assert.deepEqual( index[ 0 ].name, 'foo' );
		assert.deepEqual( index[ 0 ].searchableText, '1 foo' );
	} );

	QUnit.test( 'isIndexEmpty', ( assert ) => {
		const widget = new ve.ui.MWReferenceSearchWidget();
		widget.setDocumentRefs( getDocumentReferencesMock() );
		assert.true( widget.isIndexEmpty() );

		widget.setDocumentRefs( getDocumentReferencesMock( true ) );
		assert.false( widget.isIndexEmpty() );
	} );

	QUnit.test( 'buildSearchResults', ( assert ) => {
		const widget = new ve.ui.MWReferenceSearchWidget();
		widget.index = [ { searchableText: 'a', reference: 'model-a' }, { searchableText: 'b' } ];

		assert.strictEqual( widget.getResults().getItemCount(), 0 );
		const results = widget.buildSearchResults( 'A' );
		assert.strictEqual( results.length, 1 );
		assert.strictEqual( results[ 0 ].getData(), 'model-a' );
	} );
}
