'use strict';

( function () {
	QUnit.module( 've.ui.MWReferenceSearchWidget (Cite)', ve.test.utils.newMwEnvironment() );

	function getDocRefsMock( hasNode ) {
		const listKey = 'literal/foo';
		const node = hasNode ? {
			getAttribute: ( name ) => {
				switch ( name ) {
					case 'listKey': return listKey;
					default: return undefined;
				}
			},
			getAttributes: () => ( {} ),
			getInternalItem: () => ( {} ),
			getDocument: () => ( new ve.dm.Document() )
		} : {};
		const groups = hasNode ? {
			'mwReference/': {
				indexOrder: [ 0 ],
				firstNodes: [ node ],
				keyedNodes: { [ listKey ]: [ node ] }
			}
		} : {};
		const docRefsMock = {
			getAllGroupNames: () => ( Object.keys( groups ) ),
			getIndexLabel: () => ( '1' ),
			getItemNode: () => ( node ),
			getGroupRefs: ( groupName ) => ( ve.dm.MWGroupReferences.static.makeGroupRefs( groups[ groupName ] ) ),
			hasRefs: () => ( !!hasNode )
		};

		return docRefsMock;
	}

	QUnit.test( 'buildIndex', ( assert ) => {
		const widget = new ve.ui.MWReferenceSearchWidget();
		widget.setDocumentRefs( getDocRefsMock() );

		assert.strictEqual( widget.index, null );
		widget.buildIndex();
		assert.deepEqual( widget.index, [] );
	} );

	QUnit.test( 'buildSearchIndex when empty', ( assert ) => {
		const widget = new ve.ui.MWReferenceSearchWidget();
		widget.setDocumentRefs( getDocRefsMock() );

		const index = widget.buildSearchIndex();
		assert.deepEqual( index, [] );
	} );

	QUnit.test( 'buildSearchIndex', ( assert ) => {
		const widget = new ve.ui.MWReferenceSearchWidget();
		widget.setDocumentRefs( getDocRefsMock( true ) );

		const index = widget.buildSearchIndex();
		assert.deepEqual( index.length, 1 );
		assert.deepEqual( index[ 0 ].footnoteLabel, '1' );
		assert.deepEqual( index[ 0 ].name, 'foo' );
		assert.deepEqual( index[ 0 ].searchableText, '1 foo' );
	} );

	QUnit.test( 'isIndexEmpty', ( assert ) => {
		const widget = new ve.ui.MWReferenceSearchWidget();
		widget.setDocumentRefs( getDocRefsMock() );
		assert.true( widget.isIndexEmpty() );

		widget.setDocumentRefs( getDocRefsMock( true ) );
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
}() );
